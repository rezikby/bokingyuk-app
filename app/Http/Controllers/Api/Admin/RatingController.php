<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Field;
use App\Models\FieldRating;
use App\Services\RatingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly RatingService $ratingService) {}

    /**
     * GET /v1/admin/ratings
     * Admin hanya melihat rating dari lapangan miliknya.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'field_id'   => ['nullable', 'integer', 'exists:fields,id'],
            'rating'     => ['nullable', 'integer', 'min:1', 'max:5'],
            'is_visible' => ['nullable', 'boolean'],
            'per_page'   => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        // Jika ada field_id, verifikasi kepemilikan
        if ($request->field_id) {
            $field = Field::find($request->field_id);
            if (! $field || $field->admin_id !== auth()->id()) {
                return $this->forbidden('Anda tidak memiliki akses ke lapangan ini.');
            }
        }

        $filters = $request->only('field_id', 'rating', 'is_visible', 'per_page');

        // Filter hanya rating dari field milik admin
        $filters['admin_id'] = auth()->id();

        $ratings = $this->ratingService->getAllRatings($filters);

        return $this->success($ratings);
    }

    /**
     * PATCH /v1/admin/ratings/{id}/toggle-visibility
     */
    public function toggleVisibility(int $id): JsonResponse
    {
        $rating = FieldRating::with('field')->findOrFail($id);

        // Verifikasi rating ini dari field milik admin
        if ($rating->field?->admin_id !== auth()->id()) {
            return $this->forbidden('Anda tidak memiliki akses ke rating ini.');
        }

        $updated = $this->ratingService->toggleVisibility($rating);

        $msg = $updated->is_visible ? 'Rating ditampilkan.' : 'Rating disembunyikan.';

        return $this->success($updated, $msg);
    }
}
