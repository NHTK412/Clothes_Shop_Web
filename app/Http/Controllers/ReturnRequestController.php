<?php

namespace App\Http\Controllers;

use App\Enums\ReturnRequestStatus;
use App\Http\Services\ReturnRequestService;
use App\Models\ReturnRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReturnRequestController extends Controller
{
    public function __construct(private readonly ReturnRequestService $returnRequestService) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(ReturnRequestStatus::values())],
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = ReturnRequest::query()
            ->with(['order.payment', 'refundRequest'])
            ->where('user_id', $request->user()->id);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        return $this->paginatedResponse(
            $query->latest()->paginate(
                $validated['per_page'] ?? 15,
                ['*'],
                'page',
                $validated['page'] ?? 1
            ),
            false
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|integer',
            'reason' => 'required|string|max:2000',
        ]);

        $returnRequest = $this->returnRequestService->create(
            $request->user(),
            $validated['order_id'],
            $validated['reason']
        );

        return $this->itemResponse($returnRequest, 201, false);
    }

    public function show(Request $request, ReturnRequest $returnRequest)
    {
        abort_unless($returnRequest->user_id === $request->user()->id, 404);

        return $this->itemResponse(
            $returnRequest->load(['order.payment', 'refundRequest']),
            200,
            false
        );
    }

    public function cancel(Request $request, ReturnRequest $returnRequest)
    {
        return $this->itemResponse(
            $this->returnRequestService->cancel($request->user(), $returnRequest),
            200,
            false
        );
    }

    public function adminIndex(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(ReturnRequestStatus::values())],
            'order_id' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = ReturnRequest::query()->with(['order.payment', 'user', 'refundRequest']);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['order_id'])) {
            $query->where('order_id', $validated['order_id']);
        }

        return $this->paginatedResponse(
            $query->latest()->paginate(
                $validated['per_page'] ?? 15,
                ['*'],
                'page',
                $validated['page'] ?? 1
            ),
            true
        );
    }

    public function adminShow(ReturnRequest $returnRequest)
    {
        return $this->itemResponse(
            $returnRequest->load(['order.payment', 'user', 'refundRequest']),
            200,
            true
        );
    }

    public function updateStatus(Request $request, ReturnRequest $returnRequest)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                ReturnRequestStatus::APPROVED->value,
                ReturnRequestStatus::REJECTED->value,
            ])],
            'note' => 'nullable|string|max:5000',
        ]);

        $updated = $this->returnRequestService->updateStatus(
            $returnRequest,
            ReturnRequestStatus::from($validated['status']),
            $validated['note'] ?? null
        );

        return $this->itemResponse($updated, 200, true);
    }

    private function itemResponse(ReturnRequest $returnRequest, int $status, bool $admin)
    {
        return response()->json([
            'status' => $status,
            'success' => true,
            'message' => null,
            'data' => $this->format($returnRequest, $admin),
        ], $status);
    }

    private function paginatedResponse($paginator, bool $admin)
    {
        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'items' => collect($paginator->items())
                    ->map(fn (ReturnRequest $item) => $this->format($item, $admin))
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

    private function format(ReturnRequest $returnRequest, bool $admin): array
    {
        $data = [
            'id' => $returnRequest->id,
            'order_id' => $returnRequest->order_id,
            'reason' => $returnRequest->reason,
            'status' => $returnRequest->status->value,
            'note' => $returnRequest->admin_note,
            'ghn_order_code' => $returnRequest->ghn_order_code,
            'expected_delivery_at' => $returnRequest->expected_delivery_at?->toISOString(),
            'refund_id' => $returnRequest->refundRequest?->id,
            'refund_status' => $returnRequest->refundRequest?->status,
            'created_at' => $returnRequest->created_at?->toISOString(),
            'updated_at' => $returnRequest->updated_at?->toISOString(),
            'order' => $returnRequest->order ? [
                'id' => $returnRequest->order->id,
                'status' => $returnRequest->order->status,
                'full_name' => $returnRequest->order->full_name,
                'phone' => $returnRequest->order->phone,
                'payment_method' => $returnRequest->order->payment?->method,
                'payment_status' => $returnRequest->order->payment?->status,
            ] : null,
        ];

        if ($admin) {
            $data['customer'] = ($returnRequest->order || $returnRequest->user) ? [
                'id' => $returnRequest->user_id,
                'name' => $returnRequest->order?->full_name,
                'email' => $returnRequest->user?->email,
                'phone' => $returnRequest->order?->phone,
            ] : null;
            $data['ghn_fee'] = $returnRequest->ghn_fee;
            $data['approved_at'] = $returnRequest->approved_at?->toISOString();
            $data['rejected_at'] = $returnRequest->rejected_at?->toISOString();
            $data['cancelled_at'] = $returnRequest->cancelled_at?->toISOString();
        }

        return $data;
    }
}
