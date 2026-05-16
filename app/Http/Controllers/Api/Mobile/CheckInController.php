<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Repositories\Interfaces\BookingRepositoryInterface;
use App\Services\BookingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Mobile-friendly QR Check-in API
 * Dapat diakses dari handphone tanpa perlu login.
 *
 * Base URL: http://yourdomain.com/api/mobile
 *
 * Endpoints:
 *   GET  /api/mobile/checkin/{qr_token}   → Lakukan check-in via QR scan
 *   GET  /api/mobile/booking/{qr_token}   → Lihat info booking (read-only)
 */
class CheckInController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly BookingService $bookingService,
        private readonly BookingRepositoryInterface $bookingRepo,
    ) {}

    /**
     * Check-in via QR token (dari link di QR code).
     * Endpoint ini dipanggil ketika HP men-scan QR lalu browser membuka URL.
     *
     * GET /api/mobile/checkin/{qr_token}
     */
    public function checkInByUrl(string $qrToken): JsonResponse
    {
        try {
            $booking = $this->bookingService->checkIn($qrToken);

            return $this->success(
                new BookingResource($booking->load(['field', 'user', 'payment'])),
                'Check-in berhasil! Selamat menikmati fasilitas lapangan.'
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Lihat info booking via QR token (read-only, untuk display di HP).
     *
     * GET /api/mobile/booking/{qr_token}
     */
    public function bookingInfo(string $qrToken): JsonResponse
    {
        $booking = $this->bookingRepo->findByQrToken($qrToken);

        if (! $booking) {
            return $this->notFound('QR token tidak valid atau booking tidak ditemukan.');
        }

        return $this->success(
            new BookingResource($booking->load(['field', 'user', 'payment'])),
            'Info booking berhasil dimuat.'
        );
    }
}
