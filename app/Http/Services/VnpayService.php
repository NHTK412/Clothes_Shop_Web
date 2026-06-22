<?php

namespace App\Http\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Validation\ValidationException;

class VnpayService
{
    public function createPaymentUrl(Order $order, string $ipAddress, ?string $bankCode = null, ?string $locale = null): array
    {
        $paymentUrl = config('services.vnpay.payment_url');
        $tmnCode = config('services.vnpay.tmn_code');
        $hashSecret = config('services.vnpay.hash_secret');
        $returnUrl = config('services.vnpay.return_url');

        if (! $paymentUrl || ! $tmnCode || ! $hashSecret || ! $returnUrl) {
            throw ValidationException::withMessages([
                'vnpay' => 'VNPAY configuration is incomplete.',
            ]);
        }

        if ((float) $order->final_price <= 0) {
            throw ValidationException::withMessages([
                'order' => 'Order payment amount must be greater than 0.',
            ]);
        }

        if ($order->payment && $order->payment->method !== 'VNPAY') {
            throw ValidationException::withMessages([
                'order' => 'This order is not using VNPAY payment method.',
            ]);
        }

        if ($order->payment && $order->payment->status === 'PAID') {
            throw ValidationException::withMessages([
                'order' => 'This order has already been paid.',
            ]);
        }

        $now = now('Asia/Ho_Chi_Minh');
        $txnRef = $order->id.$now->format('YmdHis');

        $params = [
            'vnp_Version' => config('services.vnpay.version', '2.1.0'),
            'vnp_Command' => config('services.vnpay.command', 'pay'),
            'vnp_TmnCode' => $tmnCode,
            'vnp_Amount' => (int) round((float) $order->final_price * 100),
            'vnp_CreateDate' => $now->format('YmdHis'),
            'vnp_CurrCode' => config('services.vnpay.currency', 'VND'),
            'vnp_IpAddr' => $ipAddress,
            'vnp_Locale' => $locale ?: config('services.vnpay.locale', 'vn'),
            'vnp_OrderInfo' => "Thanh toan don hang {$order->id}",
            'vnp_OrderType' => config('services.vnpay.order_type', 'other'),
            'vnp_ReturnUrl' => $returnUrl,
            'vnp_ExpireDate' => $now->copy()->addMinutes((int) config('services.vnpay.expire_minutes', 15))->format('YmdHis'),
            'vnp_TxnRef' => $txnRef,
        ];

        // if ($bankCode) {
        //     $params['vnp_BankCode'] = $bankCode;
        // }

        ksort($params);

        $hashData = [];
        $query = [];

        foreach ($params as $key => $value) {
            $hashData[] = urlencode($key).'='.urlencode($value);
            $query[] = urlencode($key).'='.urlencode($value);
        }

        $secureHash = hash_hmac('sha512', implode('&', $hashData), $hashSecret);
        $query[] = 'vnp_SecureHash='.$secureHash;

        $order->payment()->updateOrCreate(
            ['order_id' => $order->id],
            [
                'method' => 'VNPAY',
                'status' => 'UNPAID',
                'transaction_id' => $txnRef,
                'payment_details' => [
                    'vnp_TxnRef' => $txnRef,
                    'vnp_Amount' => $params['vnp_Amount'],
                    'vnp_CreateDate' => $params['vnp_CreateDate'],
                    'vnp_ExpireDate' => $params['vnp_ExpireDate'],
                    'vnp_BankCode' => $bankCode,
                ],
            ]
        );

        return [
            'payment_url' => $paymentUrl.'?'.implode('&', $query),
            'txn_ref' => $txnRef,
            'amount' => (float) $order->final_price,
            'expire_at' => $params['vnp_ExpireDate'],
        ];
    }

    public function handleReturn(array $params): array
    {
        if (! $this->isValidSignature($params)) {
            throw ValidationException::withMessages([
                'vnpay' => 'Invalid VNPAY secure hash.',
            ]);
        }

        $txnRef = $params['vnp_TxnRef'] ?? null;

        if (! $txnRef) {
            throw ValidationException::withMessages([
                'vnp_TxnRef' => 'Transaction reference is required.',
            ]);
        }

        $payment = Payment::with('order')->where('transaction_id', $txnRef)->firstOrFail();
        $order = $payment->order;
        $expectedAmount = (int) round((float) $order->final_price * 100);
        $paidAmount = (int) ($params['vnp_Amount'] ?? 0);

        if ($expectedAmount !== $paidAmount) {
            throw ValidationException::withMessages([
                'vnp_Amount' => 'Payment amount does not match order amount.',
            ]);
        }

        $isPaid = ($params['vnp_ResponseCode'] ?? null) === '00'
            && ($params['vnp_TransactionStatus'] ?? null) === '00';

        $paymentDetails = array_merge($payment->payment_details ?? [], [
            'vnp_ResponseCode' => $params['vnp_ResponseCode'] ?? null,
            'vnp_TransactionStatus' => $params['vnp_TransactionStatus'] ?? null,
            'vnp_TransactionNo' => $params['vnp_TransactionNo'] ?? null,
            'vnp_BankCode' => $params['vnp_BankCode'] ?? null,
            'vnp_BankTranNo' => $params['vnp_BankTranNo'] ?? null,
            'vnp_CardType' => $params['vnp_CardType'] ?? null,
            'vnp_PayDate' => $params['vnp_PayDate'] ?? null,
        ]);

        if ($isPaid) {
            $payment->update([
                'status' => 'PAID',
                'payment_details' => $paymentDetails,
            ]);

            $order->update([
                'status' => 'CONFIRMED',
            ]);
        } else {
            $payment->update([
                'status' => 'UNPAID',
                'payment_details' => $paymentDetails,
            ]);
        }

        return [
            'order_id' => $order->id,
            'order_status' => $order->fresh()->status,
            'payment_status' => $payment->fresh()->status,
            'transaction_id' => $payment->transaction_id,
            'response_code' => $params['vnp_ResponseCode'] ?? null,
            'transaction_status' => $params['vnp_TransactionStatus'] ?? null,
        ];
    }

    private function isValidSignature(array $params): bool
    {
        $secureHash = $params['vnp_SecureHash'] ?? null;

        if (! $secureHash) {
            return false;
        }

        unset($params['vnp_SecureHash'], $params['vnp_SecureHashType']);
        ksort($params);

        $hashData = [];

        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $hashData[] = urlencode($key).'='.urlencode($value);
        }

        $hashSecret = config('services.vnpay.hash_secret');
        $calculatedHash = hash_hmac('sha512', implode('&', $hashData), $hashSecret);

        return hash_equals($calculatedHash, $secureHash);
    }
}
