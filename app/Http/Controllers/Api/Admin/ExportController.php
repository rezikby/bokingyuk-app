<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\FieldRepositoryInterface;
use App\Services\ExportService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExportController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ExportService $exportService,
        private readonly FieldRepositoryInterface $fieldRepo,
    ) {}

    /**
     * GET /v1/admin/export/revenue
     * Mendukung: ?from=&to=  ATAU  ?start_date=&end_date=
     */
    public function revenue(Request $request): Response
    {
        $this->normalizeDateParams($request);

        $request->validate([
            'from'     => ['required', 'date'],
            'to'       => ['required', 'date', 'after_or_equal:from'],
            'field_id' => ['nullable', 'integer', 'exists:fields,id'],
        ]);

        $adminId = auth()->id();

        if ($request->field_id) {
            $field = $this->fieldRepo->findById((int) $request->field_id);
            if (! $field || $field->admin_id !== $adminId) {
                abort(403, 'Anda tidak memiliki akses ke lapangan ini.');
            }
        }

        $csv      = $this->exportService->exportRevenueCsv($request->from, $request->to, $request->field_id, $adminId);
        $filename = "laporan-pendapatan_{$request->from}_{$request->to}.csv";

        return response($csv, 200, $this->csvHeaders($filename));
    }

    /**
     * GET /v1/admin/export/daily-summary
     * Mendukung: ?from=&to=  ATAU  ?start_date=&end_date=
     */
    public function dailySummary(Request $request): Response
    {
        $this->normalizeDateParams($request);

        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        $csv      = $this->exportService->exportDailySummaryCsv($request->from, $request->to, auth()->id());
        $filename = "ringkasan-harian_{$request->from}_{$request->to}.csv";

        return response($csv, 200, $this->csvHeaders($filename));
    }

    /**
     * GET /v1/admin/export/field-report
     * Mendukung: ?from=&to=  ATAU  ?start_date=&end_date=
     */
    public function fieldReport(Request $request): Response
    {
        $this->normalizeDateParams($request);

        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        $csv      = $this->exportService->exportFieldReportCsv($request->from, $request->to, auth()->id());
        $filename = "laporan-lapangan_{$request->from}_{$request->to}.csv";

        return response($csv, 200, $this->csvHeaders($filename));
    }

    /**
     * GET /v1/admin/export/customers
     *
     * ?include_admin=1  → semua user (customer + admin + super_admin)
     * tanpa parameter   → hanya customer milik admin yang login
     */
    public function customers(Request $request): Response
    {
        $includeAdmin = filter_var($request->input('include_admin', false), FILTER_VALIDATE_BOOLEAN);
        $adminId      = $includeAdmin ? null : auth()->id();

        $csv      = $this->exportService->exportUsersCsv($includeAdmin, $adminId);
        $suffix   = $includeAdmin ? 'semua-user' : 'customer';
        $filename = "daftar-{$suffix}_" . now()->format('Y-m-d') . '.csv';

        return response($csv, 200, $this->csvHeaders($filename));
    }

    // ─── Private ───────────────────────────────────────────────────────────────

    private function normalizeDateParams(Request $request): void
    {
        $from = $request->input('from') ?? $request->input('start_date');
        $to   = $request->input('to')   ?? $request->input('end_date');

        $request->merge(['from' => $from, 'to' => $to]);
    }

    private function csvHeaders(string $filename): array
    {
        return [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ];
    }
}