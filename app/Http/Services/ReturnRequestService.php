<?php

namespace App\Http\Services;

use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Enums\ReturnRequestStatus;
use App\Models\Order;
use App\Models\RefundRequest;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnRequestService
{
    public function __construct(private readonly GhnReturnService $ghnReturnService) {}

    public function create(User $user, int $orderId, string $reason): ReturnRequest
    {
        return DB::transaction(function () use ($user, $orderId, $reason) {
            $order = $user->orders()->whereKey($orderId)->lockForUpdate()->firstOrFail();

            if ($order->status !== OrderStatus::COMPLETED->value) {
                throw ValidationException::withMessages([
                    'order_id' => 'Only completed orders can be returned.',
                ]);
            }

            if ($order->returnRequest()->exists()) {
                throw ValidationException::withMessages([
                    'order_id' => 'A return request already exists for this order.',
                ]);
            }

            $returnRequest = $order->returnRequest()->create([
                'user_id' => $user->id,
                'reason' => $reason,
                'status' => ReturnRequestStatus::PENDING->value,
            ]);

            $order->update(['status' => OrderStatus::RETURNED->value]);

            return $returnRequest->load(['order.payment', 'refundRequest']);
        });
    }

    public function cancel(User $user, ReturnRequest $returnRequest): ReturnRequest
    {
        abort_unless($returnRequest->user_id === $user->id, 404);

        return DB::transaction(function () use ($returnRequest) {
            $locked = ReturnRequest::query()
                ->with('order')
                ->whereKey($returnRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== ReturnRequestStatus::PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending return requests can be cancelled.',
                ]);
            }

            $locked->update([
                'status' => ReturnRequestStatus::CANCELLED->value,
                'cancelled_at' => now(),
            ]);

            if ($locked->order?->status === OrderStatus::RETURNED->value) {
                $locked->order->update(['status' => OrderStatus::COMPLETED->value]);
            }

            return $locked->fresh(['order.payment', 'refundRequest']);
        });
    }

    public function updateStatus(
        ReturnRequest $returnRequest,
        ReturnRequestStatus $status,
        ?string $adminNote
    ): ReturnRequest {
        if (! in_array($status, [ReturnRequestStatus::APPROVED, ReturnRequestStatus::REJECTED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Admin can only approve or reject a return request.',
            ]);
        }

        return DB::transaction(function () use ($returnRequest, $status, $adminNote) {
            $locked = ReturnRequest::query()
                ->with('order')
                ->whereKey($returnRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === $status) {
                if ($adminNote !== null) {
                    $locked->update(['admin_note' => $adminNote]);
                }

                if (
                    $status === ReturnRequestStatus::APPROVED
                    && $locked->order->status !== OrderStatus::RETURNED->value
                ) {
                    $locked->order->update(['status' => OrderStatus::RETURNED->value]);
                }

                return $locked->fresh(['order.payment', 'user', 'refundRequest']);
            }

            if ($locked->status !== ReturnRequestStatus::PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending return requests can be processed.',
                ]);
            }

            if ($status === ReturnRequestStatus::REJECTED) {
                $locked->update([
                    'status' => $status->value,
                    'admin_note' => $adminNote,
                    'rejected_at' => now(),
                ]);

                if ($locked->order->status === OrderStatus::RETURNED->value) {
                    $locked->order->update(['status' => OrderStatus::COMPLETED->value]);
                }

                return $locked->fresh(['order.payment', 'user', 'refundRequest']);
            }

            $ghnResponse = $this->ghnReturnService->create($locked->order);
            $ghnData = $ghnResponse['data'];

            $locked->order->update(['status' => OrderStatus::RETURNED->value]);

            $locked->update([
                'status' => $status->value,
                'admin_note' => $adminNote,
                'ghn_order_code' => $ghnData['order_code'],
                'ghn_fee' => $ghnData['total_fee'] ?? null,
                'ghn_response' => $ghnResponse,
                'expected_delivery_at' => $ghnData['expected_delivery_time'] ?? null,
                'approved_at' => now(),
            ]);

            $this->createRefund(
                $locked->order,
                'Hoàn tiền cho yêu cầu trả hàng đã được duyệt',
                $adminNote,
                $locked
            );

            return $locked->fresh(['order.payment', 'user', 'refundRequest']);
        });
    }

    public function createRefundForGhnReturn(Order $order, ?string $ghnStatus = null): RefundRequest
    {
        return $this->createRefund(
            $order,
            'Hoàn tiền do GHN chuyển đơn hàng sang trạng thái trả hàng',
            'GHN status: '.($ghnStatus ?: 'return')
        );
    }

    private function createRefund(
        Order $order,
        string $reason,
        ?string $note = null,
        ?ReturnRequest $returnRequest = null
    ): RefundRequest {
        $refund = RefundRequest::firstOrCreate(
            ['order_id' => $order->id],
            [
                'user_id' => $order->user_id,
                'return_request_id' => $returnRequest?->id,
                'reason' => $reason,
                'status' => RefundStatus::PENDING->value,
                'amount' => 0,
                'note' => $note,
            ]
        );

        if ($returnRequest && $refund->return_request_id === null) {
            $refund->update(['return_request_id' => $returnRequest->id]);
        }

        return $refund;
    }
}
