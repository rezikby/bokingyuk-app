<?php

namespace App\Repositories\Eloquent;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Repositories\Interfaces\BookingRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingRepository implements BookingRepositoryInterface
{
    public function __construct(private readonly Booking $model) {}

    public function findByCode(string $code): ?Booking
    {
        return $this->model->with(['user', 'field', 'payment'])
            ->where('booking_code', $code)
            ->first();
    }

    public function findByQrToken(string $token): ?Booking
    {
        return $this->model->with(['user', 'field'])
            ->where('qr_token', $token)
            ->first();
    }

    public function getByUser(int $userId, array $filters = []): LengthAwarePaginator
    {
        return $this->model->with(['field', 'payment'])
            ->where('user_id', $userId)
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['date']), fn($q) => $q->whereDate('booking_date', $filters['date']))
            ->latest('booking_date')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Semua booking, dengan filter opsional.
     *
     * Filter admin_id: hanya booking dari field yang dimiliki admin tersebut.
     * Super admin tidak pass admin_id sehingga melihat semua booking.
     */
    public function getAllPaginated(array $filters = []): LengthAwarePaginator
    {
        return $this->model->with(['user', 'field', 'payment'])
            ->when(
                isset($filters['admin_id']),
                fn($q) => $q->whereHas('field', fn($f) => $f->where('admin_id', $filters['admin_id']))
            )
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['field_id']), fn($q) => $q->where('field_id', $filters['field_id']))
            ->when(isset($filters['date']), fn($q) => $q->whereDate('booking_date', $filters['date']))
            ->when(isset($filters['search']), function ($q) use ($filters) {
                $q->where(function ($inner) use ($filters) {
                    $inner->where('booking_code', 'like', "%{$filters['search']}%")
                          ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$filters['search']}%"));
                });
            })
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getBookedSlotsByFieldAndDate(int $fieldId, string $date): Collection
    {
        $results = $this->model
            ->where('field_id', $fieldId)
            ->whereDate('booking_date', $date)
            ->whereNotIn('status', [BookingStatus::Cancelled->value])
            ->select([
                DB::raw('TIME(start_time) as start_time'),
                DB::raw('TIME(end_time) as end_time'),
                'status',
            ])
            ->get();

        Log::info('getBookedSlots', [
            'field_id' => $fieldId,
            'date'     => $date,
            'count'    => $results->count(),
            'results'  => $results->toArray(),
        ]);

        return $results;
    }

    public function isSlotBooked(int $fieldId, string $date, string $start, string $end): bool
    {
        return $this->model
            ->where('field_id', $fieldId)
            ->whereDate('booking_date', $date)
            ->whereNotIn('status', [BookingStatus::Cancelled->value])
            ->where(function ($q) use ($start, $end) {
                $q->whereRaw('TIME(start_time) < ?', [$end])
                  ->whereRaw('TIME(end_time) > ?', [$start]);
            })
            ->exists();
    }

    public function create(array $data): Booking
    {
        return $this->model->create($data);
    }

    public function update(Booking $booking, array $data): Booking
    {
        $booking->update($data);
        return $booking->fresh();
    }

    /**
     * Revenue per periode, optional filter admin_id (via field).
     */
    public function getRevenueByPeriod(string $from, string $to, array $filters = []): Collection
    {
        return $this->model
            ->join('payments', 'bookings.id', '=', 'payments.booking_id')
            ->join('fields', 'bookings.field_id', '=', 'fields.id')
            ->where('payments.status', PaymentStatus::Paid->value)
            ->whereBetween('bookings.booking_date', [$from, $to])
            ->when(isset($filters['admin_id']), fn($q) => $q->where('fields.admin_id', $filters['admin_id']))
            ->when(isset($filters['field_id']), fn($q) => $q->where('bookings.field_id', $filters['field_id']))
            ->select(
                DB::raw('DATE(bookings.booking_date) as date'),
                DB::raw('SUM(payments.amount) as total_revenue'),
                DB::raw('COUNT(bookings.id) as total_bookings')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    public function getPopularHours(int $fieldId, int $limit = 5): Collection
    {
        return $this->model
            ->where('field_id', $fieldId)
            ->whereNotIn('status', [BookingStatus::Cancelled->value])
            ->select(
                DB::raw('HOUR(start_time) as hour'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('hour')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();
    }
}
