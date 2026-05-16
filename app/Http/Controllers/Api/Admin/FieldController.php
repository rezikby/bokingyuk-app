<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Field\StoreFieldRequest;
use App\Http\Requests\Field\UpdateFieldRequest;
use App\Http\Resources\FieldResource;
use App\Repositories\Interfaces\FieldRepositoryInterface;
use App\Services\FieldService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FieldController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly FieldService $fieldService,
        private readonly FieldRepositoryInterface $fieldRepo,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['type', 'is_active', 'search', 'per_page']);

        // Admin hanya melihat field miliknya sendiri
        $filters['admin_id'] = auth()->id();

        $fields = $this->fieldRepo->getAllPaginated($filters);

        return $this->success(FieldResource::collection($fields)->response()->getData(true));
    }

    public function show(int $id): JsonResponse
    {
        $field = $this->fieldRepo->findById($id);

        if (! $field) {
            return $this->notFound('Lapangan tidak ditemukan.');
        }

        // Pastikan field ini milik admin yang login
        if ($field->admin_id !== auth()->id()) {
            return $this->forbidden('Anda tidak memiliki akses ke lapangan ini.');
        }

        return $this->success(new FieldResource($field));
    }

    public function store(StoreFieldRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Handle multipart/form-data image upload
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image');
        }

        // Set admin_id ke admin yang sedang login
        $data['admin_id'] = auth()->id();

        $field = $this->fieldService->createField($data);

        return $this->created(new FieldResource($field), 'Lapangan berhasil ditambahkan.');
    }

    public function update(UpdateFieldRequest $request, int $id): JsonResponse
    {
        $field = $this->fieldRepo->findById($id);

        if (! $field) {
            return $this->notFound('Lapangan tidak ditemukan.');
        }

        // Pastikan admin hanya bisa update field miliknya
        if ($field->admin_id !== auth()->id()) {
            return $this->forbidden('Anda tidak memiliki akses ke lapangan ini.');
        }

        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image');
        }

        $field = $this->fieldService->updateField($field, $data);

        return $this->success(new FieldResource($field), 'Lapangan berhasil diperbarui.');
    }

    public function destroy(int $id): JsonResponse
    {
        $field = $this->fieldRepo->findById($id);

        if (! $field) {
            return $this->notFound('Lapangan tidak ditemukan.');
        }

        // Pastikan admin hanya bisa hapus field miliknya
        if ($field->admin_id !== auth()->id()) {
            return $this->forbidden('Anda tidak memiliki akses ke lapangan ini.');
        }

        $this->fieldService->deleteField($field);

        return $this->success(null, 'Lapangan berhasil dihapus.');
    }
}
