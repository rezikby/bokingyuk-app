<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
    ];

    // ─── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Ambil nilai setting dengan tipe yang benar.
     */
    public function typedValue(): mixed
    {
        return match($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'json'    => json_decode($this->value, true),
            default   => $this->value,
        };
    }

    /**
     * Ambil nilai setting berdasarkan key. Kembalikan $default jika tidak ada.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->typedValue() : $default;
    }

    /**
     * Set nilai setting berdasarkan key.
     */
    public static function set(string $key, mixed $value): void
    {
        static::where('key', $key)->update(['value' => $value]);
    }
}
