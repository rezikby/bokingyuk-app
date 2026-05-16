<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentHistory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentHistoryService
{
    /**
     * Catat event baru ke riwayat pembayaran.
     */
    public function record(
        Payment $payment,
        string $event,
        ?string $gatewayStatus = null,
        ?array $gatewayPayload = null,
        ?string $note = null
    ): PaymentHistory {
        return PaymentHistory::create([
            'payment_id'      => $payment->id,
            'booking_id'      => $payment->booking_id,
            'user_id'         => $payment->booking->user_id,
            'event'           => $event,
            'gateway_status'  => $gatewayStatus,
            'amount'          => $payment->amount,
            'payment_method'  => $payment->method,
            'gateway_payload' => $gatewayPayload,
            'note'            => $note,
        ]);
    }

    /**
     * Riwayat pembayaran milik user tertentu (paginasi).
     */
    public function getByUser(int $userId, array $filters = []): LengthAwarePaginator
    {
        return PaymentHistory::with(['booking.field'])
            ->where('user_id', $userId)
            ->when(isset($filters['event']), fn($q) => $q->where('event', $filters['event']))
            ->when(isset($filters['from']), fn($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Riwayat pembayaran untuk booking tertentu.
     */
    public function getByBooking(int $bookingId): \Illuminate\Support\Collection
    {
        return PaymentHistory::where('booking_id', $bookingId)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Semua riwayat pembayaran (paginasi).
     *
     * @param array $filters  Bisa berisi: event, user_id, from, to, per_page, admin_id
     *                        admin_id → hanya payment dari booking yang masuk ke field milik admin tsb.
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        return PaymentHistory::with(['booking.field', 'user'])
            ->when(isset($filters['event']), fn($q) => $q->where('event', $filters['event']))
            ->when(isset($filters['user_id']), fn($q) => $q->where('user_id', $filters['user_id']))
            ->when(isset($filters['from']), fn($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->when(
                isset($filters['admin_id']),
                fn($q) => $q->whereHas('booking.field', fn($f) => $f->where('admin_id', $filters['admin_id']))
            )
            ->latest()
            ->paginate($filters['per_page'] ?? 20);
    }
}
