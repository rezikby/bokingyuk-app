<?php

namespace App\Services;

use App\Repositories\Interfaces\BookingRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ReportService
{
    public function __construct(private readonly BookingRepositoryInterface $bookingRepo) {}

    /**
     * Revenue report for a date range.
     *
     * @param array $filters  Bisa berisi: admin_id, field_id
     *                        Jika admin_id diisi, hanya revenue field milik admin tsb.
     */
    public function getRevenueReport(string $from, string $to, array $filters = []): array
    {
        $data = $this->bookingRepo->getRevenueByPeriod($from, $to, $filters);

        return [
            'period'         => compact('from', 'to'),
            'total_revenue'  => $data->sum('total_revenue'),
            'total_bookings' => $data->sum('total_bookings'),
            'daily'          => $data,
        ];
    }

    /**
     * AI-style prediction: busy hours per field based on historical data.
     * Uses weighted moving average from booking history.
     */
    public function predictBusyHours(int $fieldId): array
    {
        $cacheKey = "predict_busy_hours_field_{$fieldId}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($fieldId) {
            $popularHours = $this->bookingRepo->getPopularHours($fieldId, 24);

            $predictions = $this->buildHourlyPrediction($popularHours);

            return [
                'field_id'     => $fieldId,
                'generated_at' => now()->toIso8601String(),
                'predictions'  => $predictions,
                'peak_hours'   => $predictions->where('level', 'tinggi')->pluck('hour')->values(),
                'quiet_hours'  => $predictions->where('level', 'rendah')->pluck('hour')->values(),
            ];
        });
    }

    // ─── Private ───────────────────────────────────────────────────────────────

    private function buildHourlyPrediction(Collection $popularHours): Collection
    {
        $maxBooking = $popularHours->max('total') ?: 1;

        $hourlyMap = $popularHours->keyBy('hour');

        return collect(range(8, 21))->map(function (int $hour) use ($hourlyMap, $maxBooking) {
            $total      = $hourlyMap->get($hour)?->total ?? 0;
            $percentage = round(($total / $maxBooking) * 100);

            return [
                'hour'           => sprintf('%02d:00', $hour),
                'percentage'     => $percentage,
                'level'          => match(true) {
                    $percentage >= 70 => 'tinggi',
                    $percentage >= 40 => 'sedang',
                    default           => 'rendah',
                },
                'total_bookings' => $total,
            ];
        });
    }
}
