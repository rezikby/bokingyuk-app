<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class AuditLogService
{
    /**
     * Catat aksi audit.
     */
    public static function log(
        string $action,
        ?int $userId = null,
        ?string $auditableType = null,
        ?int $auditableId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Request $request = null
    ): AuditLog {
        return AuditLog::create([
            'user_id'        => $userId,
            'action'         => $action,
            'auditable_type' => $auditableType,
            'auditable_id'   => $auditableId,
            'old_values'     => $oldValues,
            'new_values'     => $newValues,
            'ip_address'     => $request?->ip(),
            'user_agent'     => $request?->userAgent(),
            'url'            => $request?->fullUrl(),
            'method'         => $request?->method(),
        ]);
    }

    /**
     * Log dengan model instance secara otomatis extract type dan id.
     */
    public static function logModel(
        string $action,
        object $model,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
        ?Request $request = null
    ): AuditLog {
        return static::log(
            action: $action,
            userId: $userId,
            auditableType: get_class($model),
            auditableId: $model->id ?? null,
            oldValues: $oldValues,
            newValues: $newValues,
            request: $request
        );
    }

    /**
     * Get semua audit log (paginasi) dengan filter.
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        return AuditLog::with(['user:id,name,email,role'])
            ->when(isset($filters['user_id']), fn($q) => $q->where('user_id', $filters['user_id']))
            ->when(isset($filters['action']), fn($q) => $q->where('action', $filters['action']))
            ->when(isset($filters['auditable_type']), fn($q) => $q->where('auditable_type', $filters['auditable_type']))
            ->when(isset($filters['from']), fn($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->when(isset($filters['search']), function ($q) use ($filters) {
                $q->where(function ($inner) use ($filters) {
                    $inner->where('action', 'like', "%{$filters['search']}%")
                          ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$filters['search']}%"));
                });
            })
            ->latest()
            ->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get audit log untuk satu resource tertentu.
     */
    public function getByModel(string $type, int $id, int $perPage = 20): LengthAwarePaginator
    {
        return AuditLog::with(['user:id,name,role'])
            ->where('auditable_type', $type)
            ->where('auditable_id', $id)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Statistik ringkasan audit log.
     */
    public function getSummary(): array
    {
        return [
            'total_today'     => AuditLog::whereDate('created_at', today())->count(),
            'total_this_week' => AuditLog::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'by_action'       => AuditLog::selectRaw('action, COUNT(*) as count')
                                    ->groupBy('action')
                                    ->orderByDesc('count')
                                    ->limit(10)
                                    ->pluck('count', 'action'),
            'active_users'    => AuditLog::whereDate('created_at', today())
                                    ->distinct('user_id')
                                    ->count('user_id'),
        ];
    }

    /**
     * Hapus audit log lama (lebih dari N hari).
     */
    public function prune(int $daysOld = 90): int
    {
        return AuditLog::where('created_at', '<', now()->subDays($daysOld))->delete();
    }
}
