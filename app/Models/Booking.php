<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_code',
        'user_id',
        'field_id',
        'booking_date',
        'start_time',
        'end_time',
        'duration_hours',
        'total_price',
        'status',
        'payment_status',
        'qr_token',
        'checked_in_at',
        'notes',
    ];

    protected $casts = [
        'booking_date'  => 'date',
        'start_time'    => 'datetime:H:i',
        'end_time'      => 'datetime:H:i',
        'total_price'   => 'integer',
        'duration_hours'=> 'integer',
        'status'        => BookingStatus::class,
        'payment_status'=> PaymentStatus::class,
        'checked_in_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $booking) {
            $booking->booking_code = strtoupper('BKY-' . now()->format('Ymd') . '-' . Str::random(6));
            $booking->qr_token     = Str::uuid()->toString();
        });
    }

    // ─── Relations ─────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopeByStatus($query, BookingStatus $status)
    {
        return $query->where('status', $status->value);
    }

    public function scopeByDate($query, string $date)
    {
        return $query->whereDate('booking_date', $date);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('booking_date', '>=', now()->toDateString())
                     ->where('status', BookingStatus::Confirmed->value);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    public function isCheckedIn(): bool
    {
        return $this->status === BookingStatus::CheckedIn;
    }

    public function canCheckIn(): bool
    {
        return $this->status === BookingStatus::Confirmed
            && $this->payment_status === PaymentStatus::Paid;
    }
}
