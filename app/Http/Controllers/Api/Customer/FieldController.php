<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\FieldResource;
use App\Repositories\Interfaces\FieldRepositoryInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FieldController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly FieldRepositoryInterface $fieldRepo) {}

    public function index(Request $request): JsonResponse
    {
        $fields = $this->fieldRepo->getAllPaginated(
            array_merge($request->only(['type', 'search', 'per_page']), ['is_active' => true])
        );

        return $this->success(FieldResource::collection($fields)->response()->getData(true));
    }

    public function show(int $id): JsonResponse
    {
        $field = $this->fieldRepo->findById($id);

        if (! $field || ! $field->is_active) {
            return $this->notFound('Lapangan tidak ditemukan.');
        }

        return $this->success(new FieldResource($field));
    }
}
