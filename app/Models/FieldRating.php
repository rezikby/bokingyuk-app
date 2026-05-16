<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldRating extends Model
{
    protected $fillable = [
        'field_id',
        'user_id',
        'booking_id',
        'rating',
        'review',
        'is_visible',
    ];

    protected $casts = [
        'rating'     => 'integer',
        'is_visible' => 'boolean',
    ];

    // ─── Relations ─────────────────────────────────────────────────────────────

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }
}
