<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuditLogService $auditLogService) {}

    /**
     * GET /v1/super-admin/audit-logs
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'user_id'        => ['nullable', 'integer', 'exists:users,id'],
            'action'         => ['nullable', 'string'],
            'auditable_type' => ['nullable', 'string'],
            'from'           => ['nullable', 'date'],
            'to'             => ['nullable', 'date', 'after_or_equal:from'],
            'search'         => ['nullable', 'string', 'max:100'],
            'per_page'       => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $logs = $this->auditLogService->getAll($request->only(
            'user_id', 'action', 'auditable_type', 'from', 'to', 'search', 'per_page'
        ));

        return $this->success($logs);
    }

    /**
     * GET /v1/super-admin/audit-logs/summary
     */
    public function summary(): JsonResponse
    {
        return $this->success($this->auditLogService->getSummary());
    }

    /**
     * GET /v1/super-admin/audit-logs/model/{type}/{id}
     * Audit trail untuk satu resource (misal: User, Booking, Field).
     */
    public function byModel(string $type, int $id, Request $request): JsonResponse
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        // type bisa pendek (User, Booking) atau full class
        $modelType = str_contains($type, '\\') ? $type : 'App\\Models\\' . ucfirst($type);

        $logs = $this->auditLogService->getByModel($modelType, $id, $request->integer('per_page', 20));

        return $this->success($logs);
    }

    /**
     * DELETE /v1/super-admin/audit-logs/prune
     * Hapus audit log lama.
     */
    public function prune(Request $request): JsonResponse
    {
        $request->validate([
            'days_old' => ['nullable', 'integer', 'min:30'],
        ]);

        $deleted = $this->auditLogService->prune($request->integer('days_old', 90));

        return $this->success(['deleted' => $deleted], "{$deleted} audit log lama berhasil dihapus.");
    }
}
