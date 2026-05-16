<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Field;
use App\Models\FieldRating;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RatingService
{
    /**
     * Buat rating baru untuk lapangan setelah selesai booking.
     */
    public function createRating(User $user, Booking $booking, int $rating, ?string $review = null): FieldRating
    {
        if ($booking->user_id !== $user->id) {
            throw new \InvalidArgumentException('Booking tidak ditemukan.');
        }

        if ($booking->status !== BookingStatus::Completed) {
            throw new \LogicException('Rating hanya bisa diberikan untuk booking yang sudah selesai.');
        }

        if (FieldRating::where('booking_id', $booking->id)->exists()) {
            throw new \LogicException('Anda sudah memberikan rating untuk booking ini.');
        }

        if ($rating < 1 || $rating > 5) {
            throw new \InvalidArgumentException('Rating harus antara 1 hingga 5.');
        }

        return DB::transaction(function () use ($booking, $user, $rating, $review) {
            $fieldRating = FieldRating::create([
                'field_id'   => $booking->field_id,
                'user_id'    => $user->id,
                'booking_id' => $booking->id,
                'rating'     => $rating,
                'review'     => $review,
                'is_visible' => true,
            ]);

            $this->recalculateFieldRating($booking->field_id);

            return $fieldRating->load(['user', 'booking']);
        });
    }

    /**
     * Semua rating untuk satu lapangan (publik, hanya visible).
     */
    public function getRatingsByField(int $fieldId, array $filters = []): LengthAwarePaginator
    {
        return FieldRating::with(['user:id,name,avatar'])
            ->where('field_id', $fieldId)
            ->visible()
            ->when(isset($filters['rating']), fn($q) => $q->where('rating', $filters['rating']))
            ->latest()
            ->paginate($filters['per_page'] ?? 10);
    }

    /**
     * Semua rating dengan filter.
     *
     * Filter baru: admin_id → hanya rating dari field milik admin tsb.
     */
    public function getAllRatings(array $filters = []): LengthAwarePaginator
    {
        return FieldRating::with(['field:id,name', 'user:id,name', 'booking:id,booking_code'])
            ->when(isset($filters['field_id']), fn($q) => $q->where('field_id', $filters['field_id']))
            ->when(isset($filters['rating']), fn($q) => $q->where('rating', $filters['rating']))
            ->when(isset($filters['is_visible']), fn($q) => $q->where('is_visible', $filters['is_visible']))
            ->when(
                isset($filters['admin_id']),
                fn($q) => $q->whereHas('field', fn($f) => $f->where('admin_id', $filters['admin_id']))
            )
            ->latest()
            ->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Toggle visibilitas rating.
     */
    public function toggleVisibility(FieldRating $rating): FieldRating
    {
        $rating->update(['is_visible' => ! $rating->is_visible]);
        $this->recalculateFieldRating($rating->field_id);
        return $rating->fresh();
    }

    /**
     * Hapus rating (super admin).
     */
    public function deleteRating(FieldRating $rating): void
    {
        $fieldId = $rating->field_id;
        $rating->delete();
        $this->recalculateFieldRating($fieldId);
    }

    /**
     * Ringkasan rating untuk satu lapangan.
     */
    public function getRatingSummary(int $fieldId): array
    {
        $field = Field::findOrFail($fieldId);

        $distribution = FieldRating::where('field_id', $fieldId)
            ->visible()
            ->select(DB::raw('rating, COUNT(*) as count'))
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        return [
            'avg_rating'    => round((float) $field->avg_rating, 2),
            'total_ratings' => $field->total_ratings,
            'distribution'  => array_map(fn($r) => [
                'rating' => $r,
                'count'  => $distribution[$r] ?? 0,
            ], range(5, 1)),
        ];
    }

    // ─── Private ───────────────────────────────────────────────────────────────

    private function recalculateFieldRating(int $fieldId): void
    {
        $stats = FieldRating::where('field_id', $fieldId)
            ->visible()
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total_ratings')
            ->first();

        Field::where('id', $fieldId)->update([
            'avg_rating'    => round((float) ($stats->avg_rating ?? 0), 2),
            'total_ratings' => (int) ($stats->total_ratings ?? 0),
        ]);
    }
}
