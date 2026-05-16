<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Services\PaymentHistoryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentHistoryController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly PaymentHistoryService $paymentHistoryService) {}

    /**
     * GET /v1/payment-history
     * Riwayat pembayaran milik customer yang sedang login.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'event'    => ['nullable', 'string', 'in:initiated,paid,expired,refunded,failed'],
            'from'     => ['nullable', 'date'],
            'to'       => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $histories = $this->paymentHistoryService->getByUser(
            $request->user()->id,
            $request->only('event', 'from', 'to', 'per_page')
        );

        return $this->success($histories);
    }

    /**
     * GET /v1/payment-history/booking/{bookingCode}
     * Riwayat pembayaran untuk booking tertentu milik customer.
     */
    public function byBooking(string $bookingCode, Request $request): JsonResponse
    {
        $booking = $request->user()->bookings()
            ->where('booking_code', $bookingCode)
            ->firstOrFail();

        $histories = $this->paymentHistoryService->getByBooking($booking->id);

        return $this->success($histories);
    }
}
