<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogger
{
    /**
     * Catat aktivitas user.
     *
     * @param  int         $userId
     * @param  string      $type         login | logout | booking_create | booking_cancel | profile_update | pengajuan_admin | pengaduan
     * @param  string      $description  Teks yang ditampilkan ke user
     * @param  array|null  $metadata     Data tambahan (opsional)
     * @param  Request|null $request     Untuk ambil IP & user-agent
     */
    public static function log(
        int $userId,
        string $type,
        string $description,
        ?array $metadata = null,
        ?Request $request = null
    ): void {
        $ip     = $request?->ip();
        $device = $request ? self::parseDevice($request->userAgent()) : null;

        ActivityLog::create([
            'user_id'     => $userId,
            'type'        => $type,
            'description' => $description,
            'ip_address'  => $ip,
            'device'      => $device,
            'metadata'    => $metadata,
        ]);
    }

    private static function parseDevice(?string $ua): ?string
    {
        if (!$ua) return null;

        if (str_contains($ua, 'Mobile') || str_contains($ua, 'Android')) {
            return 'Mobile';
        }
        if (str_contains($ua, 'Tablet') || str_contains($ua, 'iPad')) {
            return 'Tablet';
        }
        return 'Desktop';
    }
}