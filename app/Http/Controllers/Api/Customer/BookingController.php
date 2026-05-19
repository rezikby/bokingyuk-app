<?php

namespace App\Http\Controllers\Api\Customer;

use App\Exceptions\BookingConflictException;
use App\Exceptions\FieldNotFoundException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\PaymentResource;
use App\Repositories\Interfaces\BookingRepositoryInterface;
use App\Services\ActivityLogger;
use App\Services\BookingService;
use App\Services\PaymentService;
use App\Services\NotificationService;
use App\Enums\PaymentStatus;
use App\Enums\BookingStatus;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly BookingService $bookingService,
        private readonly BookingRepositoryInterface $bookingRepo,
        private readonly PaymentService $paymentService,
        private readonly NotificationService $notificationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $bookings = $this->bookingRepo->getByUser(
            auth()->id(),
            $request->only(['status', 'date', 'per_page'])
        );

        return $this->success(BookingResource::collection($bookings)->response()->getData(true));
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        try {
            $booking = $this->bookingService->createBooking(auth()->user(), $request->validated());

            ActivityLogger::log(
                auth()->id(),
                'booking_create',
                'Membuat booking baru: ' . $booking->booking_code,
                ['booking_code' => $booking->booking_code],
                $request
            );

            return $this->created(
                new BookingResource($booking),
                'Booking berhasil dibuat. Silakan lakukan pembayaran.'
            );
        } catch (FieldNotFoundException $e) {
            return $this->notFound($e->getMessage());
        } catch (BookingConflictException $e) {
            return $this->error($e->getMessage(), 409);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function show(string $code): JsonResponse
    {
        $booking = $this->bookingRepo->findByCode($code);

        if (! $booking || $booking->user_id !== auth()->id()) {
            return $this->notFound('Booking tidak ditemukan.');
        }

        return $this->success(new BookingResource($booking));
    }

    public function cancel(Request $request, string $code): JsonResponse
    {
        $booking = $this->bookingRepo->findByCode($code);

        if (! $booking || $booking->user_id !== auth()->id()) {
            return $this->notFound('Booking tidak ditemukan.');
        }

        try {
            $booking = $this->bookingService->cancelBooking($booking);

            ActivityLogger::log(
                auth()->id(),
                'booking_cancel',
                'Membatalkan booking: ' . $booking->booking_code,
                ['booking_code' => $booking->booking_code],
                $request
            );

            return $this->success(new BookingResource($booking), 'Booking berhasil dibatalkan.');
        } catch (InvalidStatusTransitionException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function availableSlots(Request $request): JsonResponse
    {
        $request->validate([
            'field_id' => ['required', 'integer', 'exists:fields,id'],
            'date'     => ['required', 'date', 'after_or_equal:today'],
        ]);

        $slots = $this->bookingService->getAvailableSlots(
            $request->integer('field_id'),
            $request->string('date')
        );

        $bookedSlots = collect($slots)
            ->filter(fn($s) => ! $s['is_available'])
            ->pluck('start')
            ->values()
            ->all();

        return $this->success([
            'slots'        => $slots,
            'booked_slots' => $bookedSlots,
        ]);
    }

    /**
     * GET /v1/bookings/{code}/check-payment
     *
     * Dipanggil frontend customer setiap 3 detik saat di halaman Payment.
     * Cek langsung ke Midtrans — jika sudah lunas, update status otomatis
     * tanpa perlu admin online atau webhook.
     */
    public function checkPaymentStatus(string $code): JsonResponse
    {
        $booking = $this->bookingRepo->findByCode($code);

        if (! $booking || $booking->user_id !== auth()->id()) {
            return $this->notFound('Booking tidak ditemukan.');
        }

        // Sudah paid — langsung return, tidak perlu cek ke Midtrans lagi
        if ($booking->payment_status === PaymentStatus::Paid) {
            return $this->success(new BookingResource($booking), 'Sudah lunas.');
        }

        $payment = $booking->payment;

        // Belum ada payment record atau belum ada order_id → belum bisa dicek
        if (! $payment || ! $payment->midtrans_order_id) {
            return $this->success(new BookingResource($booking), 'Menunggu pembayaran.');
        }

        $serverKey = config('services.midtrans.server_key', '');
        if (empty($serverKey)) {
            return $this->success(new BookingResource($booking), 'Konfigurasi Midtrans belum lengkap.');
        }

        $apiBase = config('services.midtrans.is_production', false)
            ? 'https://api.midtrans.com/v2'
            : 'https://api.sandbox.midtrans.com/v2';

        try {
            $response = Http::withBasicAuth($serverKey, '')
                ->acceptJson()
                ->timeout(8)
                ->get("{$apiBase}/{$payment->midtrans_order_id}/status");

            // Transaksi belum ada di Midtrans (user belum buka halaman bayar)
            if ($response->status() === 404) {
                return $this->success(new BookingResource($booking), 'Menunggu pembayaran.');
            }

            if (! $response->successful()) {
                return $this->success(new BookingResource($booking), 'Menunggu pembayaran.');
            }

            $body              = $response->json();
            $transactionStatus = $body['transaction_status'] ?? '';
            $fraudStatus       = $body['fraud_status'] ?? null;

            $resolvedStatus = $this->paymentService->resolveStatus($transactionStatus, $fraudStatus);

            // Belum lunas — return status sekarang
            if ($resolvedStatus !== PaymentStatus::Paid) {
                return $this->success(new BookingResource($booking), 'Menunggu pembayaran.');
            }

            // ── LUNAS: update booking & payment ──────────────────────────────
            $booking->update([
                'status'         => BookingStatus::Confirmed->value,
                'payment_status' => PaymentStatus::Paid->value,
            ]);

            $payment->update([
                'status'                 => PaymentStatus::Paid->value,
                'method'                 => $body['payment_type'] ?? null,
                'gateway_transaction_id' => $body['transaction_id'] ?? null,
                'gateway_response'       => $body,
                'paid_at'                => now(),
            ]);

            // Reload relasi supaya BookingResource punya data terbaru
            $booking->load(['field', 'user', 'payment']);

            // Kirim notifikasi ke customer
            try {
                $this->notificationService->notifyPaymentSuccess($booking);
            } catch (\Throwable $e) {
                Log::warning('checkPaymentStatus: notif gagal', ['error' => $e->getMessage()]);
            }

            // Kirim notifikasi ke admin lapangan
            try {
                $adminId = $booking->field->admin_id ?? null;
                if ($adminId) {
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
                }
            } catch (\Throwable $e) {
                Log::warning('checkPaymentStatus: notif admin gagal', ['error' => $e->getMessage()]);
            }

            Log::info('checkPaymentStatus: booking auto-confirmed', [
                'booking_code' => $booking->booking_code,
                'user'         => $booking->user->name ?? '-',
            ]);

            return $this->success(new BookingResource($booking), 'Pembayaran berhasil dikonfirmasi!');

        } catch (\Throwable $e) {
            Log::warning('checkPaymentStatus: exception', [
                'booking_code' => $code,
                'error'        => $e->getMessage(),
            ]);

            return $this->success(new BookingResource($booking), 'Menunggu pembayaran.');
        }
    }

    public function refreshPaymentToken(string $code): JsonResponse
    {
        $booking = $this->bookingRepo->findByCode($code);

        if (! $booking || $booking->user_id !== auth()->id()) {
            return $this->notFound('Booking tidak ditemukan.');
        }

        if ($booking->payment_status === PaymentStatus::Paid) {
            return $this->error('Booking ini sudah lunas.', 422);
        }

        if ($booking->status->value === 'cancelled') {
            return $this->error('Booking yang dibatalkan tidak bisa di-refresh.', 422);
        }

        $serverKey = config('services.midtrans.server_key', '');
        if (empty($serverKey)) {
            return $this->error(
                'MIDTRANS_SERVER_KEY belum diset di file .env backend. Silakan isi terlebih dahulu.',
                503
            );
        }

        try {
            $payment = $booking->payment;

            if ($payment && ! $payment->snap_token && ! $payment->payment_url) {
                $payment->delete();
                $payment = null;
            }

            if (! $payment) {
                $booking->load('user');
                $payment = $this->paymentService->initiate($booking);
            }

            $payment->refresh();

            if (! $payment->snap_token && ! $payment->payment_url) {
                return $this->error(
                    'Gagal mendapatkan token dari Midtrans. Pastikan MIDTRANS_SERVER_KEY valid dan cek log Laravel untuk detail.',
                    503
                );
            }

            return $this->success(
                new PaymentResource($payment),
                'Token pembayaran berhasil diperbarui.'
            );
        } catch (\Throwable $e) {
            Log::error('refreshPaymentToken error', [
                'booking' => $code,
                'error'   => $e->getMessage(),
            ]);

            return $this->error('Terjadi kesalahan server: ' . $e->getMessage(), 503);
        }
    }
}