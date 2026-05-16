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
use App\Enums\PaymentStatus;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly BookingService $bookingService,
        private readonly BookingRepositoryInterface $bookingRepo,
        private readonly PaymentService $paymentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $bookings = $this->bookingRepo->getByUser(
            auth()->id(),
            $request->only(['status', 'date', 'per_page'])
        );

        return $this->success(BookingResource::collection($bookings)->response()->getData(true));
    }

    /**
     * FIX #6  — $booking->booking_code (bukan ->code)
     * FIX #2  — tangkap FieldNotFoundException sebagai 404
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        try {
            $booking = $this->bookingService->createBooking(auth()->user(), $request->validated());

            ActivityLogger::log(
                auth()->id(),
                'booking_create',
                'Membuat booking baru: ' . $booking->booking_code,  // ✅ fix #6
                ['booking_code' => $booking->booking_code],          // ✅ fix #6
                $request
            );

            return $this->created(
                new BookingResource($booking),
                'Booking berhasil dibuat. Silakan lakukan pembayaran.'
            );
        } catch (FieldNotFoundException $e) {
            return $this->notFound($e->getMessage());               // ✅ fix #2
        } catch (BookingConflictException $e) {
            return $this->error($e->getMessage(), 409);
        } catch (\InvalidArgumentException $e) {
            // Durasi tidak valid dari calculateDuration()
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

    /**
     * FIX #6 — booking_code konsisten di activity log cancel juga.
     */
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
                'Membatalkan booking: ' . $booking->booking_code,  // ✅ fix #6
                ['booking_code' => $booking->booking_code],         // ✅ fix #6
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