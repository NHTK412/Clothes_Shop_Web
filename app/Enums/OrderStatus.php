<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING_PAYMENT = 'PENDING_PAYMENT';
    case CONFIRMED = 'CONFIRMED';
    case SHIPPING = 'SHIPPING';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
    case RETURNED = 'RETURNED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function canBeCancelled(): bool
    {
        return in_array($this, [self::PENDING_PAYMENT, self::CONFIRMED], true);
    }
}
