<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'amount',
        'method',
        'gateway_transaction_id',
        'gateway_response',
        'status',
        'paid_at',
        'expired_at',
        'snap_token',
        'payment_url',
    ];

    protected $casts = [
        'amount'            => 'integer',
        'status'            => PaymentStatus::class,
        'gateway_response'  => 'array',
        'paid_at'           => 'datetime',
        'expired_at'        => 'datetime',
    ];

    // ─── Relations ─────────────────────────────────────────────────────────────

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopePaid($query)
    {
        return $query->where('status', PaymentStatus::Paid->value);
    }

    public function scopeByMethod($query, string $method)
    {
        return $query->where('method', $method);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Paid;
    }

    public function isExpired(): bool
    {
        return $this->status === PaymentStatus::Expired
            || ($this->expired_at && $this->expired_at->isPast());
    }
}
