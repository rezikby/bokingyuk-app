<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentHistory extends Model
{
    protected $fillable = [
        'payment_id',
        'booking_id',
        'user_id',
        'event',
        'gateway_status',
        'amount',
        'payment_method',
        'gateway_payload',
        'note',
    ];

    protected $casts = [
        'amount'           => 'integer',
        'gateway_payload'  => 'array',
    ];

    // ─── Relations ─────────────────────────────────────────────────────────────

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    public function eventLabel(): string
    {
        return match($this->event) {
            'initiated' => 'Pembayaran Dimulai',
            'paid'      => 'Pembayaran Berhasil',
            'expired'   => 'Pembayaran Kedaluwarsa',
            'refunded'  => 'Refund Diproses',
            'failed'    => 'Pembayaran Gagal',
            default     => ucfirst($this->event),
        };
    }
}
