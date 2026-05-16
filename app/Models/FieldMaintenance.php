<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldMaintenance extends Model
{
    protected $fillable = [
        'field_id',
        'created_by',
        'maintenance_date',
        'start_time',
        'end_time',
        'title',
        'description',
        'status',
    ];

    protected $casts = [
        'maintenance_date' => 'date',
    ];

    // ─── Relations ─────────────────────────────────────────────────────────────

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('maintenance_date', '>=', now()->toDateString())
                     ->where('status', 'scheduled');
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    public function statusLabel(): string
    {
        return match($this->status) {
            'scheduled'   => 'Terjadwal',
            'in_progress' => 'Sedang Berlangsung',
            'completed'   => 'Selesai',
            'cancelled'   => 'Dibatalkan',
            default       => ucfirst($this->status),
        };
    }

    /**
     * Apakah jadwal maintenance ini bertabrakan dengan slot booking tertentu?
     */
    public function conflictsWith(string $start, string $end): bool
    {
        return $this->start_time < $end && $this->end_time > $start;
    }
}
