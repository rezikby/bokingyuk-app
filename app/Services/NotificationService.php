<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\FieldMaintenance;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    // ─── Core ──────────────────────────────────────────────────────────────────

    /**
     * Kirim notifikasi ke satu user.
     */
    public function send(int $userId, string $type, string $title, string $body, array $data = []): Notification
    {
        return Notification::create([
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'body'    => $body,
            'data'    => $data,
        ]);
    }

    /**
     * Kirim notifikasi ke banyak user sekaligus (bulk insert).
     */
    public function sendBulk(array $userIds, string $type, string $title, string $body, array $data = []): void
    {
        $now  = now();
        $rows = collect($userIds)->map(fn($uid) => [
            'user_id'    => $uid,
            'type'       => $type,
            'title'      => $title,
            'body'       => $body,
            'data'       => json_encode($data),
            'created_at' => $now,
            'updated_at' => $now,
        ])->toArray();

        Notification::insert($rows);
    }

    // ─── Booking Notifications ─────────────────────────────────────────────────

    public function notifyBookingCreated(Booking $booking): void
    {
        $this->send(
            $booking->user_id,
            'booking',
            'Booking Berhasil Dibuat',
            "Booking {$booking->booking_code} untuk lapangan {$booking->field->name} berhasil dibuat. Segera lakukan pembayaran.",
            ['booking_code' => $booking->booking_code, 'booking_id' => $booking->id]
        );
    }

    public function notifyPaymentSuccess(Booking $booking): void
    {
        $this->send(
            $booking->user_id,
            'payment',
            'Pembayaran Berhasil',
            "Pembayaran untuk booking {$booking->booking_code} telah dikonfirmasi. Selamat bermain!",
            ['booking_code' => $booking->booking_code, 'booking_id' => $booking->id]
        );
    }

    public function notifyPaymentExpired(Booking $booking): void
    {
        $this->send(
            $booking->user_id,
            'payment',
            'Pembayaran Kedaluwarsa',
            "Pembayaran untuk booking {$booking->booking_code} telah kedaluwarsa. Booking otomatis dibatalkan.",
            ['booking_code' => $booking->booking_code, 'booking_id' => $booking->id]
        );
    }

    public function notifyBookingCancelled(Booking $booking, string $reason = ''): void
    {
        $body = "Booking {$booking->booking_code} telah dibatalkan.";
        if ($reason) {
            $body .= " Alasan: {$reason}";
        }

        $this->send(
            $booking->user_id,
            'booking',
            'Booking Dibatalkan',
            $body,
            ['booking_code' => $booking->booking_code, 'booking_id' => $booking->id]
        );
    }

    public function notifyCheckIn(Booking $booking): void
    {
        $this->send(
            $booking->user_id,
            'booking',
            'Check-In Berhasil',
            "Selamat datang! Check-in untuk booking {$booking->booking_code} di lapangan {$booking->field->name} berhasil.",
            ['booking_code' => $booking->booking_code, 'booking_id' => $booking->id]
        );
    }

    // ─── Maintenance Notifications ─────────────────────────────────────────────

    /**
     * Notifikasi ke semua customer yang punya booking di slot maintenance.
     */
    public function notifyMaintenanceScheduled(FieldMaintenance $maintenance): void
    {
        // Cari booking yang terpengaruh
        $affectedUserIds = Booking::where('field_id', $maintenance->field_id)
            ->whereDate('booking_date', $maintenance->maintenance_date)
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->pluck('user_id')
            ->unique()
            ->toArray();

        if (empty($affectedUserIds)) {
            return;
        }

        $this->sendBulk(
            $affectedUserIds,
            'maintenance',
            'Jadwal Maintenance Lapangan',
            "Lapangan {$maintenance->field->name} akan menjalani maintenance pada {$maintenance->maintenance_date->format('d M Y')} pukul {$maintenance->start_time} – {$maintenance->end_time}. Mohon maaf atas ketidaknyamanannya.",
            ['maintenance_id' => $maintenance->id, 'field_id' => $maintenance->field_id]
        );
    }

    // ─── System Notifications ──────────────────────────────────────────────────

    /**
     * Broadcast notifikasi sistem ke semua user aktif.
     */
    public function broadcastSystem(string $title, string $body, array $data = []): void
    {
        $userIds = User::where('is_active', true)->pluck('id')->toArray();
        $this->sendBulk($userIds, 'system', $title, $body, $data);
    }

    // ─── Read Management ───────────────────────────────────────────────────────

    public function getByUser(int $userId, array $filters = []): LengthAwarePaginator
    {
        return Notification::where('user_id', $userId)
            ->when(isset($filters['type']), fn($q) => $q->where('type', $filters['type']))
            ->when(isset($filters['unread']), fn($q) => $q->whereNull('read_at'))
            ->latest()
            ->paginate($filters['per_page'] ?? 20);
    }

    public function markAsRead(int $notificationId, int $userId): bool
    {
        return (bool) Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function markAllAsRead(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function getUnreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function deleteOld(int $daysOld = 30): int
    {
        return Notification::where('created_at', '<', now()->subDays($daysOld))
            ->whereNotNull('read_at')
            ->delete();
    }
}
