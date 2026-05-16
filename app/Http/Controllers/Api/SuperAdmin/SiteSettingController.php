<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SiteSettingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteSettingController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SiteSettingService $siteSettingService) {}

    /**
     * GET /v1/super-admin/settings
     */
    public function index(): JsonResponse
    {
        $settings = $this->siteSettingService->getAll()
            ->groupBy('group')
            ->map(fn($items) => $items->values());

        return $this->success($settings);
    }

    /**
     * GET /v1/super-admin/settings/{group}
     */
    public function byGroup(string $group): JsonResponse
    {
        $settings = $this->siteSettingService->getByGroup($group);

        if ($settings->isEmpty()) {
            return $this->error("Group setting '{$group}' tidak ditemukan.", 404);
        }

        return $this->success($settings);
    }

    /**
     * PUT /v1/super-admin/settings
     * Body: { "key1": "value1", "key2": "value2" }
     */
    public function updateMany(Request $request): JsonResponse
    {
        $allowedKeys = \App\Models\SiteSetting::pluck('key')->toArray();
        $data = $request->only($allowedKeys);

        if (empty($data)) {
            return $this->error('Tidak ada data setting yang valid untuk disimpan.', 422);
        }

        $this->siteSettingService->updateMany($data);

        return $this->success(null, 'Setting berhasil diperbarui.');
    }

    /**
     * PATCH /v1/super-admin/settings/{key}
     */
    public function update(string $key, Request $request): JsonResponse
    {
        $request->validate([
            'value' => ['nullable', 'string'],
        ]);

        try {
            $setting = $this->siteSettingService->update($key, $request->input('value'));
            return $this->success($setting, "Setting '{$key}' berhasil diperbarui.");
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->error("Setting dengan key '{$key}' tidak ditemukan.", 404);
        }
    }
}