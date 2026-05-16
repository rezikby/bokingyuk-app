<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldSchedule extends Model
{
    protected $fillable = [
        'field_id',
        'day_of_week',   // 0=Sun, 1=Mon … 6=Sat; null = specific date
        'specific_date',
        'open_time',
        'close_time',
        'price_per_hour',
        'is_closed',
    ];

    protected $casts = [
        'specific_date' => 'date',
        'price_per_hour'=> 'integer',
        'is_closed'     => 'boolean',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }
}
