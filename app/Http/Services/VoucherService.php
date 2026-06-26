<?php

namespace App\Http\Services;

use App\Models\Voucher;

class VoucherService
{
    public function getVoucherByCode($code)
    {
        return Voucher::where('code', $code)
            ->where('is_active', true)
            ->where('expiry_date', '>=', now())
            ->first();
    }
}
