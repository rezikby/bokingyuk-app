<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Field;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponse;

    /**
     * GET /v1/admin/dashboard
     *
     * Dashboard statistik personal admin — hanya data miliknya sendiri.
     */
    public function stats(): JsonResponse
    {
        $adminId = auth()->id();

        // Field IDs milik admin ini
        $fieldIds = Field::where('admin_id', $adminId)->pluck('id');

        // Total lapangan milik admin
        $totalFields = $fieldIds->count();

        // Booking dari field admin ini
        $bookingQuery = Booking::whereIn('field_id', $fieldIds);

        $totalBookings   = (clone $bookingQuery)->count();
        $activeBookings  = (clone $bookingQuery)->whereIn('status', [
            BookingStatus::Pending->value,
            BookingStatus::Confirmed->value,
        ])->count();
        $todayBookings   = (clone $bookingQuery)->whereDate('booking_date', today())->count();

        // Revenue bulan ini (dari payment lunas)
        $revenueThisMonth = (clone $bookingQuery)
            ->join('payments', 'bookings.id', '=', 'payments.booking_id')
            ->where('payments.status', PaymentStatus::Paid->value)
            ->whereMonth('bookings.booking_date', now()->month)
            ->whereYear('bookings.booking_date', now()->year)
            ->sum('payments.amount');

        // Revenue total
        $revenueTotal = (clone $bookingQuery)
            ->join('payments as p2', 'bookings.id', '=', 'p2.booking_id')
            ->where('p2.status', PaymentStatus::Paid->value)
            ->sum('p2.amount');

        // Customer unik yang pernah booking ke field admin ini
        $totalCustomers = (clone $bookingQuery)->distinct('user_id')->count('user_id');

        // Booking pending (belum bayar)
        $pendingPayments = (clone $bookingQuery)
            ->where('payment_status', PaymentStatus::Unpaid->value)
            ->whereNotIn('status', [BookingStatus::Cancelled->value])
            ->count();

        // 5 lapangan terpopuler milik admin
        $popularFields = Field::where('admin_id', $adminId)
            ->withCount(['bookings' => fn($q) => $q->whereNotIn('status', [BookingStatus::Cancelled->value])])
            ->orderByDesc('bookings_count')
            ->limit(5)
            ->get(['id', 'name', 'type', 'bookings_count']);

        // Revenue 7 hari terakhir
        $recentRevenue = DB::table('bookings')
            ->join('payments', 'bookings.id', '=', 'payments.booking_id')
            ->whereIn('bookings.field_id', $fieldIds)
            ->where('payments.status', PaymentStatus::Paid->value)
            ->whereBetween('bookings.booking_date', [now()->subDays(6)->toDateString(), today()->toDateString()])
            ->selectRaw('DATE(bookings.booking_date) as date, SUM(payments.amount) as revenue, COUNT(bookings.id) as bookings')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $this->success([
            'summary' => [
                'total_fields'       => $totalFields,
                'total_bookings'     => $totalBookings,
                'active_bookings'    => $activeBookings,
                'today_bookings'     => $todayBookings,
                'total_customers'    => $totalCustomers,
                'pending_payments'   => $pendingPayments,
                'revenue_this_month' => $revenueThisMonth,
                'revenue_total'      => $revenueTotal,
            ],
            'popular_fields'  => $popularFields,
            'recent_revenue'  => $recentRevenue,
        ]);
    }
}
