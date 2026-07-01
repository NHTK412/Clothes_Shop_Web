<?php

namespace App\Http\Services;

use App\Enums\RefundStatus;
use App\Models\RefundRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundService
{
    public function updateStatus(RefundRequest $refund, RefundStatus $status): RefundRequest
    {
        return DB::transaction(function () use ($refund, $status) {
            $locked = RefundRequest::query()
                ->with('order.payment')
                ->whereKey($refund->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === $status->value) {
                return $locked;
            }

            if ($locked->status !== RefundStatus::PENDING->value) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending refunds can be approved or rejected.',
                ]);
            }

            $locked->update([
                'status' => $status->value,
                'completed_at' => $status === RefundStatus::APPROVED ? now() : null,
            ]);

            if ($status === RefundStatus::APPROVED && $locked->order?->payment) {
                $locked->order->payment->update(['status' => 'REFUNDED']);
            }

            return $locked->fresh(['order.payment', 'user', 'returnRequest']);
        });
    }

    public function updateDetails(
        RefundRequest $refund,
        array $attributes
    ): RefundRequest {
        $refund->update($attributes);

        return $refund->fresh(['order.payment', 'user', 'returnRequest']);
    }
}
