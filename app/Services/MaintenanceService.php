<?php

namespace App\Services;

use App\Models\Field;
use App\Models\FieldMaintenance;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MaintenanceService
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function create(User $admin, array $data): FieldMaintenance
    {
        $this->validateNoConflict(
            $data['field_id'],
            $data['maintenance_date'],
            $data['start_time'],
            $data['end_time']
        );

        return DB::transaction(function () use ($admin, $data) {
            $maintenance = FieldMaintenance::create([
                'field_id'         => $data['field_id'],
                'created_by'       => $admin->id,
                'maintenance_date' => $data['maintenance_date'],
                'start_time'       => $data['start_time'],
                'end_time'         => $data['end_time'],
                'title'            => $data['title'],
                'description'      => $data['description'] ?? null,
                'status'           => 'scheduled',
            ]);

            $maintenance->load(['field', 'creator']);

            Field::where('id', $maintenance->field_id)->update(['is_active' => false]);

            $this->notificationService->notifyMaintenanceScheduled($maintenance);

            return $maintenance;
        });
    }

    public function update(FieldMaintenance $maintenance, array $data): FieldMaintenance
    {
        if (isset($data['maintenance_date']) || isset($data['start_time']) || isset($data['end_time'])) {
            $this->validateNoConflict(
                $maintenance->field_id,
                $data['maintenance_date'] ?? $maintenance->maintenance_date->toDateString(),
                $data['start_time'] ?? $maintenance->start_time,
                $data['end_time'] ?? $maintenance->end_time,
                excludeId: $maintenance->id
            );
        }

        $maintenance->update($data);
        $updated = $maintenance->fresh(['field', 'creator']);

        if (isset($data['status']) && in_array($data['status'], ['completed', 'cancelled'])) {
            $this->reactivateFieldIfNoActiveMaintenance($maintenance->field_id);
        }

        return $updated;
    }

    public function cancel(FieldMaintenance $maintenance): FieldMaintenance
    {
        if ($maintenance->status === 'completed') {
            throw new \LogicException('Maintenance yang sudah selesai tidak bisa dibatalkan.');
        }

        $maintenance->update(['status' => 'cancelled']);
        $this->reactivateFieldIfNoActiveMaintenance($maintenance->field_id);

        return $maintenance->fresh();
    }

    /**
     * Bulk delete maintenance (hanya status completed atau cancelled).
     *
     * @throws \LogicException jika ada yang masih aktif atau bukan milik admin
     */
    public function bulkDelete(array $ids, int $adminId): int
    {
        $maintenances = FieldMaintenance::with('field')
            ->whereIn('id', $ids)
            ->get();

        foreach ($maintenances as $m) {
            if ($m->field?->admin_id !== $adminId) {
                throw new \LogicException("Akses ditolak untuk maintenance ID {$m->id}.");
            }

            if (in_array($m->status, ['scheduled', 'in_progress'])) {
                throw new \LogicException(
                    "Maintenance \"{$m->title}\" masih aktif dan tidak bisa dihapus. Batalkan terlebih dahulu."
                );
            }
        }

        return DB::transaction(function () use ($ids) {
            return FieldMaintenance::whereIn('id', $ids)->delete();
        });
    }

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        return FieldMaintenance::with(['field:id,name,admin_id', 'creator:id,name'])
            ->when(
                isset($filters['admin_id']),
                fn($q) => $q->whereHas('field', fn($f) => $f->where('admin_id', $filters['admin_id']))
            )
            ->when(isset($filters['field_id']), fn($q) => $q->where('field_id', $filters['field_id']))
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['date']), fn($q) => $q->whereDate('maintenance_date', $filters['date']))
            ->when(isset($filters['upcoming']), fn($q) => $q->upcoming())
            ->orderBy('maintenance_date')
            ->orderBy('start_time')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function isBlockedByMaintenance(int $fieldId, string $date, string $start, string $end): bool
    {
        return FieldMaintenance::where('field_id', $fieldId)
            ->whereDate('maintenance_date', $date)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->where(function ($q) use ($start, $end) {
                $q->where('start_time', '<', $end)
                  ->where('end_time', '>', $start);
            })
            ->exists();
    }

    private function reactivateFieldIfNoActiveMaintenance(int $fieldId): void
    {
        $hasActive = FieldMaintenance::where('field_id', $fieldId)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->exists();

        if (! $hasActive) {
            Field::where('id', $fieldId)->update(['is_active' => true]);
        }
    }

    private function validateNoConflict(
        int $fieldId,
        string $date,
        string $start,
        string $end,
        ?int $excludeId = null
    ): void {
        $conflict = FieldMaintenance::where('field_id', $fieldId)
            ->whereDate('maintenance_date', $date)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->where(fn($q) => $q->where('start_time', '<', $end)->where('end_time', '>', $start))
            ->exists();

        if ($conflict) {
            throw new \InvalidArgumentException(
                'Sudah ada jadwal maintenance lain pada slot waktu tersebut.'
            );
        }
    }
}