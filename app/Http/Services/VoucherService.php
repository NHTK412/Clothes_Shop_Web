<?php

namespace App\Http\Services;

use App\Models\Voucher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class VoucherService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Voucher::query()
            ->when(! empty($filters['q']), function ($query) use ($filters) {
                $keyword = $filters['q'];

                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery->where('code', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%");
                });
            })
            ->when(array_key_exists('is_active', $filters), function ($query) use ($filters) {
                $query->where('is_active', $filters['is_active']);
            })
            ->when(! empty($filters['discount_type']), function ($query) use ($filters) {
                $query->where('discount_type', $filters['discount_type']);
            })
            ->when(($filters['status'] ?? null) === 'valid', function ($query) {
                $query->where('is_active', true)
                    ->where('usage_limit', '>', 0)
                    ->whereDate('expiry_date', '>=', today());
            })
            ->when(($filters['status'] ?? null) === 'expired', function ($query) {
                $query->whereDate('expiry_date', '<', today());
            })
            ->orderByDesc('created_at')
            ->paginate(
                (int) ($filters['per_page'] ?? 20),
                ['*'],
                'page',
                (int) ($filters['page'] ?? 1)
            );
    }

    public function create(array $data): Voucher
    {
        $data['code'] = $data['code'] ?? $this->generateUniqueCode();

        return Voucher::create($data);
    }

    public function update(Voucher $voucher, array $data): Voucher
    {
        $voucher->update($data);

        return $voucher->fresh();
    }

    public function delete(Voucher $voucher): void
    {
        $voucher->update(['is_active' => false]);
    }

    public function getVoucherByCode(string $code): ?Voucher
    {
        return Voucher::where('code', Str::upper($code))
            ->where('is_active', true)
            ->where('usage_limit', '>', 0)
            ->whereDate('expiry_date', '>=', today())
            ->first();
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = Str::upper(Str::random(10));
        } while (Voucher::where('code', $code)->exists());

        return $code;
    }
}
