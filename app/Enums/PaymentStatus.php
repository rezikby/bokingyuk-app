<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid  = 'unpaid';
    case Paid    = 'paid';
    case Expired = 'expired';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::Unpaid   => 'Belum Dibayar',
            self::Paid     => 'Sudah Dibayar',
            self::Expired  => 'Kadaluarsa',
            self::Refunded => 'Dikembalikan',
        };
    }
}
