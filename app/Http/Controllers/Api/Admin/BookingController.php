<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Repositories\Interfaces\BookingRepositoryInterface;
use App\Services\BookingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly BookingService $bookingService,
        private readonly BookingRepositoryInterface $bookingRepo,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'field_id', 'date', 'search', 'per_page']);

        // Admin hanya melihat booking dari field miliknya
        $filters['admin_id'] = auth()->id();

        $bookings = $this->bookingRepo->getAllPaginated($filters);

        return $this->success(BookingResource::collection($bookings)->response()->getData(true));
    }

    public function show(string $code): JsonResponse
    {
        $booking = $this->bookingRepo->findByCode($code);

        if (! $booking) {
            return $this->notFound('Booking tidak ditemukan.');
        }

        // Pastikan booking ini dari field milik admin
        if ($booking->field?->admin_id !== auth()->id()) {
            return $this->forbidden('Anda tidak memiliki akses ke booking ini.');
        }

        return $this->success(new BookingResource($booking));
    }

    public function checkIn(Request $request): JsonResponse
    {
        $request->validate(['qr_token' => ['required', 'string']]);

        $token = trim($request->string('qr_token'));
        if (str_contains($token, '/')) {
            $token = last(explode('/', $token));
        }

        try {
            $booking = $this->bookingService->checkIn($token);

            // Verifikasi ownership
            if ($booking->field?->admin_id !== auth()->id()) {
                return $this->forbidden('Anda tidak dapat check-in booking lapangan admin lain.');
            }

            return $this->success(new BookingResource($booking), 'Check-in berhasil.');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function cancel(string $code): JsonResponse
    {
        $booking = $this->bookingRepo->findByCode($code);

        if (! $booking) {
            return $this->notFound('Booking tidak ditemukan.');
        }

        if ($booking->field?->admin_id !== auth()->id()) {
            return $this->forbidden('Anda tidak memiliki akses ke booking ini.');
        }

        try {
            $booking = $this->bookingService->cancelBooking($booking);

            return $this->success(new BookingResource($booking), 'Booking dibatalkan oleh admin.');
        } catch (InvalidStatusTransitionException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Konfirmasi pembayaran manual oleh admin.
     */
    public function confirmPayment(string $code): JsonResponse
    {
        $booking = $this->bookingRepo->findByCode($code);

        if (! $booking) {
            return $this->notFound('Booking tidak ditemukan.');
        }

        if ($booking->field?->admin_id !== auth()->id()) {
            return $this->forbidden('Anda tidak memiliki akses ke booking ini.');
        }

        if ($booking->payment_status === PaymentStatus::Paid) {
            return $this->error('Booking ini sudah berstatus lunas.', 422);
        }

        if ($booking->status === BookingStatus::Cancelled) {
            return $this->error('Booking yang sudah dibatalkan tidak dapat dikonfirmasi.', 422);
        }

        $booking->update([
            'status'         => BookingStatus::Confirmed->value,
            'payment_status' => PaymentStatus::Paid->value,
        ]);

        if ($booking->payment) {
            $booking->payment->update([
                'status'  => PaymentStatus::Paid->value,
                'paid_at' => now(),
            ]);
        }

        return $this->success(
            new BookingResource($booking->fresh(['field', 'user', 'payment'])),
            'Pembayaran berhasil dikonfirmasi.'
        );
    }
}
