<?php

namespace App\Http\Middleware;

use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditRequest
{
    /**
     * Method yang dicatat (GET diabaikan agar log tidak membengkak).
     */
    private const AUDITED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Prefix URL yang dicatat.
     */
    private const AUDITED_PREFIXES = [
        'api/v1/admin',
        'api/v1/super-admin',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Hanya catat jika method termasuk dan URL cocok
        if ($this->shouldAudit($request)) {
            $user = $request->user();

            AuditLogService::log(
                action:    $this->resolveAction($request),
                userId:    $user?->id,
                request:   $request,
                newValues: $this->sanitizeInput($request->all()),
            );
        }

        return $response;
    }

    // ─── Private ───────────────────────────────────────────────────────────────

    private function shouldAudit(Request $request): bool
    {
        if (! in_array($request->method(), self::AUDITED_METHODS)) {
            return false;
        }

        foreach (self::AUDITED_PREFIXES as $prefix) {
            if (str_starts_with($request->path(), $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function resolveAction(Request $request): string
    {
        return match($request->method()) {
            'POST'   => 'create',
            'PUT'    => 'update',
            'PATCH'  => 'patch',
            'DELETE' => 'delete',
            default  => strtolower($request->method()),
        };
    }

    /**
     * Hapus field sensitif sebelum disimpan ke audit log.
     */
    private function sanitizeInput(array $input): array
    {
        $sensitive = ['password', 'password_confirmation', 'current_password', 'token', 'snap_token'];

        foreach ($sensitive as $field) {
            if (isset($input[$field])) {
                $input[$field] = '***';
            }
        }

        return $input;
    }
}
