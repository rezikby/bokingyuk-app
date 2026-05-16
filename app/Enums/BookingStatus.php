<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending   = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Pending   => 'Menunggu Pembayaran',
            self::Confirmed => 'Dikonfirmasi',
            self::CheckedIn => 'Check-In',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match($this) {
            self::Pending   => in_array($next, [self::Confirmed, self::Cancelled]),
            self::Confirmed => in_array($next, [self::CheckedIn, self::Cancelled]),
            self::CheckedIn => in_array($next, [self::Completed]),
            default         => false,
        };
    }
}
