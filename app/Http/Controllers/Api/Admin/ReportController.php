<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\FieldRepositoryInterface;
use App\Services\ReportService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ReportService $reportService,
        private readonly FieldRepositoryInterface $fieldRepo,
    ) {}

    public function revenue(Request $request): JsonResponse
    {
        $request->validate([
            'from'     => ['required', 'date'],
            'to'       => ['required', 'date', 'after_or_equal:from'],
            'field_id' => ['nullable', 'integer', 'exists:fields,id'],
        ]);

        // Admin hanya melihat revenue field miliknya
        $filters = ['admin_id' => auth()->id()];

        if ($request->field_id) {
            // Verifikasi bahwa field_id ini milik admin
            $field = $this->fieldRepo->findById((int) $request->field_id);
            if (! $field || $field->admin_id !== auth()->id()) {
                return $this->forbidden('Anda tidak memiliki akses ke lapangan ini.');
            }
            $filters['field_id'] = $request->field_id;
        }

        $report = $this->reportService->getRevenueReport($request->from, $request->to, $filters);

        return $this->success($report);
    }

    public function predictBusyHours(int $fieldId): JsonResponse
    {
        // Verifikasi field milik admin
        $field = $this->fieldRepo->findById($fieldId);
        if (! $field || $field->admin_id !== auth()->id()) {
            return $this->forbidden('Anda tidak memiliki akses ke lapangan ini.');
        }

        $prediction = $this->reportService->predictBusyHours($fieldId);

        return $this->success($prediction, 'Prediksi jam ramai berhasil dihasilkan.');
    }
}
