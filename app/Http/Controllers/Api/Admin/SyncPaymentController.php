<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\NotificationService;
use App\Services\PaymentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncPaymentController extends Controller
{
    use ApiResponse;

    private string $serverKey;
    private string $apiBase;

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly PaymentService $paymentService,
    ) {
        $this->serverKey = config('services.midtrans.server_key', '');

        // FIX: pakai Midtrans Transaction API (bukan Snap API)
        // Snap API   = app.midtrans.com/snap/v1      → untuk buat token
        // Status API = api.midtrans.com/v2/{order_id}/status → untuk cek status
        $this->apiBase = config('services.midtrans.is_production', false)
            ? 'https://api.midtrans.com/v2'
            : 'https://api.sandbox.midtrans.com/v2';
    }

    /**
     * GET /v1/admin/bookings/sync-payments
     *
     * Dipanggil frontend (BookingContext) setiap 5 detik.
     * Cek status ke Midtrans pakai midtrans_order_id yang tersimpan di DB.
     * Jika sudah dibayar → otomatis Confirmed + Lunas + kirim notifikasi.
     */
    public function sync(Request $request): JsonResponse
    {
        $adminId = auth()->id();

        // Ambil booking milik lapangan admin ini yang masih unpaid
        $unpaidBookings = Booking::whereHas('field', fn($q) => $q->where('admin_id', $adminId))
            ->where('payment_status', PaymentStatus::Unpaid->value)
            ->whereNotIn('status', [
                BookingStatus::Cancelled->value,
                BookingStatus::Completed->value,
            ])
            ->with(['payment', 'user', 'field'])
            ->get();

        if ($unpaidBookings->isEmpty()) {
            return $this->success([
                'confirmed' => [],
                'checked'   => 0,
            ], 'Tidak ada booking yang perlu dicek.');
        }

        if (empty($this->serverKey)) {
            return $this->success([
                'confirmed' => [],
                'checked'   => 0,
                'note'      => 'MIDTRANS_SERVER_KEY belum diset di .env',
            ]);
        }

        $confirmed = [];

        foreach ($unpaidBookings as $booking) {
            $payment = $booking->payment;

            // FIX: pakai midtrans_order_id yang sudah disimpan saat initiate()
            // Jika tidak ada → skip (booking belum pernah hit Midtrans)
            if (! $payment || ! $payment->midtrans_order_id) {
                continue;
            }

            $paid = $this->checkMidtransStatus(
                $payment->midtrans_order_id,
                $booking->booking_code
            );

            if ($paid) {
                $this->confirmBooking($booking, $adminId);
                $confirmed[] = $booking->booking_code;
            }
        }

        $msg = count($confirmed) > 0
            ? count($confirmed) . ' booking berhasil dikonfirmasi otomatis.'
            : 'Semua booking dicek, belum ada yang baru lunas.';

        return $this->success([
            'confirmed' => $confirmed,
            'checked'   => $unpaidBookings->count(),
        ], $msg);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Cek status transaksi ke Midtrans Transaction API.
     * Endpoint: GET /v2/{order_id}/status
     * Auth: Basic auth dengan server_key sebagai username, password kosong.
     */
    private function checkMidtransStatus(string $orderId, string $bookingCode): bool
    {
        try {
            $response = Http::withBasicAuth($this->serverKey, '')
                ->acceptJson()
                ->timeout(8)
                ->get("{$this->apiBase}/{$orderId}/status");

            // 404 = transaksi belum ada di Midtrans (belum dibuka user)
            if ($response->status() === 404) {
                return false;
            }

            if (! $response->successful()) {
                Log::warning('SyncPayment: Midtrans non-200', [
                    'order_id'     => $orderId,
                    'booking_code' => $bookingCode,
                    'http_status'  => $response->status(),
                    'body'         => $response->body(),
                ]);
                return false;
            }

            $body              = $response->json();
            $transactionStatus = $body['transaction_status'] ?? '';
            $fraudStatus       = $body['fraud_status'] ?? null;

            $status = $this->paymentService->resolveStatus($transactionStatus, $fraudStatus);

            return $status === PaymentStatus::Paid;

        } catch (\Throwable $e) {
            Log::warning('SyncPayment: Midtrans check exception', [
                'order_id'     => $orderId,
                'booking_code' => $bookingCode,
                'error'        => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Konfirmasi booking: update status, update payment, kirim notifikasi.
     */
    private function confirmBooking(Booking $booking, int $adminId): void
    {
        // Update booking
        $booking->update([
            'status'         => BookingStatus::Confirmed->value,
            'payment_status' => PaymentStatus::Paid->value,
        ]);

        // Update payment
        if ($booking->payment) {
            $booking->payment->update([
                'status'  => PaymentStatus::Paid->value,
                'paid_at' => now(),
            ]);
        }

        $booking->load(['field', 'user', 'payment']);

        // notif user + qr code
        $this->notificationService->notifyPaymentSuccess($booking);

    //   notif admin
        $this->notificationService->send(
            $adminId,
            'payment',
            'Pembayaran Terkonfirmasi Otomatis',
            "Booking {$booking->booking_code} dari {$booking->user->name} telah lunas via Midtrans.",
            [
                'booking_code' => $booking->booking_code,
                'booking_id'   => $booking->id,
            ]
        );

        Log::info('SyncPayment: booking auto-confirmed', [
            'booking_code' => $booking->booking_code,
            'user'         => $booking->user->name ?? '-',
            'field'        => $booking->field->name ?? '-',
        ]);
    }
}