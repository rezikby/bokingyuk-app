<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\PaymentHistoryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentHistoryController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly PaymentHistoryService $paymentHistoryService) {}

    /**
     * GET /v1/admin/payment-history
     * Hanya riwayat pembayaran dari booking yang masuk ke field milik admin.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'event'    => ['nullable', 'string', 'in:initiated,paid,expired,refunded,failed'],
            'user_id'  => ['nullable', 'integer', 'exists:users,id'],
            'from'     => ['nullable', 'date'],
            'to'       => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $filters = $request->only('event', 'user_id', 'from', 'to', 'per_page');
        $filters['admin_id'] = auth()->id();

        $histories = $this->paymentHistoryService->getAll($filters);

        return $this->success($histories);
    }

    /**
     * GET /v1/admin/payment-history/booking/{bookingId}
     * Hanya jika booking tersebut masuk ke field milik admin.
     */
    public function byBooking(int $bookingId): JsonResponse
    {
        $booking = Booking::with('field')->find($bookingId);

        if (! $booking || $booking->field?->admin_id !== auth()->id()) {
            return $this->forbidden('Anda tidak memiliki akses ke booking ini.');
        }

        $histories = $this->paymentHistoryService->getByBooking($bookingId);
        return $this->success($histories);
    }
}
