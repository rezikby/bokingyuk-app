<?php

namespace App\Repositories\Interfaces;

use App\Models\Field;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface FieldRepositoryInterface
{
    public function findById(int $id, array $with = ['schedules']): ?Field;

    public function findByIdOrFail(int $id, array $with = ['schedules']): Field;

    /**
     * @param array $filters  Bisa berisi: type, is_active, search, per_page, admin_id
     */
    public function getAllPaginated(array $filters = []): LengthAwarePaginator;

    public function getActive(int $limit = 100): Collection;

    public function create(array $data): Field;

    public function update(Field $field, array $data): Field;

    public function delete(Field $field): bool;
}
