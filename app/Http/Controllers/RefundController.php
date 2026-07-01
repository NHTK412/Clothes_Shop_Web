<?php

namespace App\Http\Controllers;

use App\Enums\RefundStatus;
use App\Http\Services\RefundService;
use App\Models\RefundRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RefundController extends Controller
{
    public function __construct(private readonly RefundService $refundService) {}

    public function index(Request $request)
    {
        return $this->list($request, false);
    }

    public function adminIndex(Request $request)
    {
        return $this->list($request, true);
    }

    public function updateStatus(Request $request, RefundRequest $refund)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                RefundStatus::APPROVED->value,
                RefundStatus::REJECTED->value,
            ])],
        ]);

        $updated = $this->refundService->updateStatus(
            $refund,
            RefundStatus::from($validated['status'])
        );

        return $this->itemResponse($updated, true);
    }

    public function update(Request $request, RefundRequest $refund)
    {
        $validated = $request->validate([
            'note' => 'sometimes|nullable|string|max:5000',
            'transfer_image' => 'sometimes|nullable|string|max:2048',
        ]);

        if ($validated === []) {
            throw ValidationException::withMessages([
                'refund' => 'Provide note or transfer_image to update.',
            ]);
        }

        return $this->itemResponse(
            $this->refundService->updateDetails($refund, $validated),
            true
        );
    }

    private function list(Request $request, bool $admin)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(RefundStatus::values())],
            'order_id' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = RefundRequest::query()->with(['order.payment', 'returnRequest']);

        if (! $admin) {
            $query->where('user_id', $request->user()->id);
        } else {
            $query->with('user');
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['order_id'])) {
            $query->where('order_id', $validated['order_id']);
        }

        $paginator = $query->latest()->paginate(
            $validated['per_page'] ?? 15,
            ['*'],
            'page',
            $validated['page'] ?? 1
        );

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'items' => collect($paginator->items())
                    ->map(fn (RefundRequest $refund) => $this->format($refund, $admin))
                    ->values(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    private function itemResponse(RefundRequest $refund, bool $admin)
    {
        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => $this->format($refund, $admin),
        ]);
    }

    private function format(RefundRequest $refund, bool $admin): array
    {
        $data = [
            'id' => $refund->id,
            'order_id' => $refund->order_id,
            'return_request_id' => $refund->return_request_id,
            'reason' => $refund->reason,
            'status' => $refund->status,
            'note' => $refund->note,
            'transfer_image' => $refund->transfer_image,
            'completed_at' => $refund->completed_at?->toISOString(),
            'created_at' => $refund->created_at?->toISOString(),
            'updated_at' => $refund->updated_at?->toISOString(),
            'order' => $refund->order ? [
                'id' => $refund->order->id,
                'status' => $refund->order->status,
                'payment_method' => $refund->order->payment?->method,
                'payment_status' => $refund->order->payment?->status,
            ] : null,
        ];

        if ($admin) {
            $data['customer'] = ($refund->order || $refund->user) ? [
                'id' => $refund->user_id,
                'name' => $refund->order?->full_name,
                'email' => $refund->user?->email,
                'phone' => $refund->order?->phone,
            ] : null;
        }

        return $data;
    }
}
