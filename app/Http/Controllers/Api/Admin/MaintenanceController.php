<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\FieldMaintenance;
use App\Services\MaintenanceService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly MaintenanceService $maintenanceService) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'field_id' => ['nullable', 'integer', 'exists:fields,id'],
            'status'   => ['nullable', 'string', 'in:scheduled,in_progress,completed,cancelled'],
            'date'     => ['nullable', 'date'],
            'upcoming' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $filters = $request->only('field_id', 'status', 'date', 'upcoming', 'per_page');
        $filters['admin_id'] = auth()->id();

        $maintenances = $this->maintenanceService->getAll($filters);

        return $this->success($maintenances);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'field_id'         => ['required', 'integer', 'exists:fields,id'],
            'maintenance_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time'       => ['required', 'date_format:H:i'],
            'end_time'         => ['required', 'date_format:H:i', 'after:start_time'],
            'title'            => ['required', 'string', 'max:200'],
            'description'      => ['nullable', 'string', 'max:1000'],
        ]);

        $field = \App\Models\Field::find($data['field_id']);
        if (! $field || $field->admin_id !== auth()->id()) {
            return $this->forbidden('Anda tidak memiliki akses ke lapangan ini.');
        }

        try {
            $maintenance = $this->maintenanceService->create($request->user(), $data);
            return $this->success($maintenance, 'Jadwal maintenance berhasil dibuat.', 201);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function show(int $id): JsonResponse
    {
        $maintenance = FieldMaintenance::with(['field', 'creator'])->findOrFail($id);

        if ($maintenance->field?->admin_id !== auth()->id()) {
            return $this->forbidden('Anda tidak memiliki akses ke maintenance ini.');
        }

        return $this->success($maintenance);
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $data = $request->validate([
            'maintenance_date' => ['nullable', 'date'],
            'start_time'       => ['nullable', 'date_format:H:i'],
            'end_time'         => ['nullable', 'date_format:H:i'],
            'title'            => ['nullable', 'string', 'max:200'],
            'description'      => ['nullable', 'string', 'max:1000'],
            'status'           => ['nullable', 'string', 'in:scheduled,in_progress,completed,cancelled'],
        ]);

        $maintenance = FieldMaintenance::findOrFail($id);

        if ($maintenance->field?->admin_id !== auth()->id()) {
            return $this->forbidden('Anda tidak memiliki akses ke maintenance ini.');
        }

        $data = array_filter($data, fn($v) => $v !== null);

        try {
            $updated = $this->maintenanceService->update($maintenance, $data);
            return $this->success($updated, 'Maintenance berhasil diperbarui.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\LogicException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function cancel(int $id): JsonResponse
    {
        $maintenance = FieldMaintenance::findOrFail($id);

        if ($maintenance->field?->admin_id !== auth()->id()) {
            return $this->forbidden('Anda tidak memiliki akses ke maintenance ini.');
        }

        try {
            $cancelled = $this->maintenanceService->cancel($maintenance);
            return $this->success($cancelled, 'Maintenance berhasil dibatalkan.');
        } catch (\LogicException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * DELETE /v1/admin/maintenances/bulk-delete
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:field_maintenances,id'],
        ]);

        try {
            $count = $this->maintenanceService->bulkDelete($data['ids'], auth()->id());
            return $this->success(null, "{$count} maintenance berhasil dihapus.");
        } catch (\LogicException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}