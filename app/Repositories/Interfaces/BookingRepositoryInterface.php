<?php

namespace App\Repositories\Interfaces;

use App\Models\Booking;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface BookingRepositoryInterface
{
    public function findByCode(string $code): ?Booking;

    public function findByQrToken(string $token): ?Booking;

    public function getByUser(int $userId, array $filters = []): LengthAwarePaginator;

    /**
     * @param array $filters  Bisa berisi: status, field_id, date, search, per_page, admin_id
     *                        admin_id → filter booking berdasarkan field milik admin
     */
    public function getAllPaginated(array $filters = []): LengthAwarePaginator;

    public function getBookedSlotsByFieldAndDate(int $fieldId, string $date): Collection;

    public function isSlotBooked(int $fieldId, string $date, string $start, string $end): bool;

    public function create(array $data): Booking;

    public function update(Booking $booking, array $data): Booking;

    /**
     * @param array $filters  Bisa berisi: from, to, admin_id, field_id
     */
    public function getRevenueByPeriod(string $from, string $to, array $filters = []): Collection;

    public function getPopularHours(int $fieldId, int $limit = 5): Collection;
}
