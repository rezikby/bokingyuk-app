<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * GET /api/v1/user/activity-logs
     * Query params: page, per_page, type
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 10), 50);
        $type    = $request->query('type');

        $query = ActivityLog::where('user_id', $request->user()->id)
            ->orderByDesc('created_at');

        if ($type && $type !== 'semua') {
            // login filter juga ambil logout
            if ($type === 'login') {
                $query->whereIn('type', ['login', 'logout']);
            } else {
                $query->where('type', $type);
            }
        }

        $paginated = $query->paginate($perPage);

        return response()->json([
            'data'  => $paginated->items(),
            'total' => $paginated->total(),
            'page'  => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
        ]);
    }
}