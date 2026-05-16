<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\RatingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly RatingService $ratingService) {}

    /**
     * GET /v1/fields/{fieldId}/ratings
     * Semua rating untuk satu lapangan (publik).
     */
    public function index(int $fieldId, Request $request): JsonResponse
    {
        $request->validate([
            'rating'   => ['nullable', 'integer', 'min:1', 'max:5'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $ratings = $this->ratingService->getRatingsByField($fieldId, $request->only('rating', 'per_page'));
        $summary = $this->ratingService->getRatingSummary($fieldId);

        return $this->success([
            'summary' => $summary,
            'reviews' => $ratings,
        ]);
    }

    /**
     * POST /v1/bookings/{bookingCode}/rating
     * Customer memberi rating setelah booking selesai.
     */
    public function store(string $bookingCode, Request $request): JsonResponse
    {
        $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review' => ['nullable', 'string', 'max:1000'],
        ]);

        $booking = Booking::where('booking_code', $bookingCode)->firstOrFail();

        try {
            $rating = $this->ratingService->createRating(
                $request->user(),
                $booking,
                $request->integer('rating'),
                $request->input('review')
            );

            return $this->success($rating, 'Rating berhasil diberikan.', 201);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\LogicException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
