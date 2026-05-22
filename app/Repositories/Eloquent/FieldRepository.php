<?php

namespace App\Repositories\Eloquent;

use App\Exceptions\FieldNotFoundException;
use App\Models\Field;
use App\Repositories\Interfaces\FieldRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class FieldRepository implements FieldRepositoryInterface
{
    public function __construct(private readonly Field $model) {}

    public function findById(int $id, array $with = ['schedules']): ?Field
    {
        return $this->model->with($with)->find($id);
    }

    public function findByIdOrFail(int $id, array $with = ['schedules']): Field
    {
        $field = $this->findById($id, $with);

        if (! $field) {
            throw new FieldNotFoundException("Lapangan dengan ID {$id} tidak ditemukan.");
        }

        return $field;
    }

    /**
     * Paginated list dengan filter.
     *
     * Filter baru: admin_id → batasi hanya field milik admin tertentu.
     * Super admin tidak perlu pass admin_id sehingga melihat semua field.
     */
    public function getAllPaginated(array $filters = []): LengthAwarePaginator
    {
        $perPage = isset($filters['per_page'])
            ? (int) $filters['per_page']
            : 100; 

        return $this->model
            ->when(isset($filters['admin_id']), function ($q) use ($filters) {
                $q->where('admin_id', $filters['admin_id']);
            })
            ->when(isset($filters['type']), function ($q) use ($filters) {
                $q->where('type', $filters['type']);
            })
            ->when(array_key_exists('is_active', $filters), function ($q) use ($filters) {
                $q->where('is_active', (bool) $filters['is_active']);
            })
            ->when(isset($filters['search']), function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%');
            })
            ->latest()
            ->paginate($perPage);
    }

    public function getActive(int $limit = 100): Collection
    {
        return $this->model->active()->limit($limit)->get();
    }

    public function create(array $data): Field
    {
        return $this->model->create($data);
    }

    public function update(Field $field, array $data): Field
    {
        $field->update($data);
        return $field->fresh();
    }

    public function delete(Field $field): bool
    {
        return $field->delete();
    }
}
