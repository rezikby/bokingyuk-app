<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SiteSettingService
{
    private const CACHE_KEY = 'site_settings_all';
    private const CACHE_TTL = 3600;

    public function getAll(): Collection
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn() =>
            SiteSetting::orderBy('group')->orderBy('key')->get()
        );
    }

    public function getByGroup(string $group): Collection
    {
        return $this->getAll()->where('group', $group)->values();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $setting = $this->getAll()->firstWhere('key', $key);
        return $setting ? $setting->typedValue() : $default;
    }

    public function updateMany(array $data): void
    {
        foreach ($data as $key => $value) {
            SiteSetting::where('key', $key)->update(['value' => $value]);
        }
        Cache::forget(self::CACHE_KEY);
    }

    public function update(string $key, mixed $value): SiteSetting
    {
        $setting = SiteSetting::where('key', $key)->firstOrFail();
        $setting->update(['value' => $value]);
        Cache::forget(self::CACHE_KEY);
        return $setting->fresh();
    }

    public function seedDefaults(): void
    {
        $defaults = [
            // General — identitas website
            ['key' => 'app_name',             'value' => 'BokinYuk',           'type' => 'string',  'group' => 'general',      'label' => 'Nama Aplikasi'],
            ['key' => 'app_logo_url',         'value' => '',                   'type' => 'string',  'group' => 'general',      'label' => 'URL Logo'],
            ['key' => 'contact_email',        'value' => 'admin@bokingyuk.id', 'type' => 'string',  'group' => 'general',      'label' => 'Email Kontak'],
            ['key' => 'contact_phone',        'value' => '',                   'type' => 'string',  'group' => 'general',      'label' => 'Telepon Kontak'],
            ['key' => 'address',              'value' => '',                   'type' => 'string',  'group' => 'general',      'label' => 'Alamat Kantor'],
            ['key' => 'maintenance_mode',     'value' => '0',                  'type' => 'boolean', 'group' => 'general',      'label' => 'Mode Maintenance'],

            // Payment — konfigurasi pembayaran
            ['key' => 'payment_expiry_hours', 'value' => '2',                  'type' => 'integer', 'group' => 'payment',      'label' => 'Kedaluwarsa Pembayaran (Jam)'],
            ['key' => 'midtrans_env',         'value' => 'sandbox',            'type' => 'string',  'group' => 'payment',      'label' => 'Midtrans Environment'],
            ['key' => 'refund_enabled',       'value' => '1',                  'type' => 'boolean', 'group' => 'payment',      'label' => 'Refund Aktif'],

            // Notification — notifikasi global
            ['key' => 'notif_booking_create', 'value' => '1',                  'type' => 'boolean', 'group' => 'notification', 'label' => 'Notif. Booking Dibuat'],
            ['key' => 'notif_payment_success','value' => '1',                  'type' => 'boolean', 'group' => 'notification', 'label' => 'Notif. Pembayaran Sukses'],
            ['key' => 'notif_maintenance',    'value' => '1',                  'type' => 'boolean', 'group' => 'notification', 'label' => 'Notif. Maintenance'],
            ['key' => 'whatsapp_enabled',     'value' => '1',                  'type' => 'boolean', 'group' => 'notification', 'label' => 'WhatsApp Notif. Aktif'],
        ];

        foreach ($defaults as $setting) {
            SiteSetting::firstOrCreate(
                ['key' => $setting['key']],
                array_merge($setting, ['description' => null])
            );
        }

        // Hapus data booking yang sudah tidak relevan di super admin
        SiteSetting::whereIn('key', [
            'booking_open_hour',
            'booking_close_hour',
            'max_advance_days',
            'min_duration_hours',
            'cancellation_hours',
        ])->delete();
    }
}