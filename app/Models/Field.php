<?php
namespace App\Models;
use App\Enums\FieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Field extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'admin_id',
        'name',
        'type',
        'description',
        'price_per_hour',
        'is_active',
        'image',
        'facilities',
        'address',
        'maps_url',
        'latitude',
        'longitude',
    ];

    protected $appends = ['image_url']; // ← tambah ini

    protected $casts = [
        'type'           => FieldType::class,
        'price_per_hour' => 'integer',
        'is_active'      => 'boolean',
        'facilities'     => 'array',
        'latitude'       => 'float',
        'longitude'      => 'float',
    ];

    // ─── Accessor ──────────────────────────────────────────────────────────
    public function getImageUrlAttribute(): ?string  // ← tambah ini
    {
        if (!$this->image) return null;
        return url('storage/' . $this->image);
    }

    // ─── Relations ─────────────────────────────────────────────────────────
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(FieldSchedule::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(FieldRating::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(FieldMaintenance::class);
    }

    // ─── Scopes ────────────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, FieldType $type)
    {
        return $query->where('type', $type->value);
    }

    public function scopeOwnedBy($query, int $adminId)
    {
        return $query->where('admin_id', $adminId);
    }
}