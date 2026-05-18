<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly NotificationService $notificationService) {}

    /**
     * GET /v1/admin/notifications
     * Ambil notifikasi milik admin yang sedang login.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type'     => ['nullable', 'string', 'in:booking,payment,maintenance,system'],
            'unread'   => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $notifications = $this->notificationService->getByUser(
            $request->user()->id,
            $request->only('type', 'unread', 'per_page')
        );

        $unreadCount = $this->notificationService->getUnreadCount($request->user()->id);

        return $this->success([
            'unread_count'  => $unreadCount,
            'notifications' => $notifications,
        ]);
    }

    /**
     * GET /v1/admin/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $this->notificationService->getUnreadCount($request->user()->id);
        return $this->success(['unread_count' => $count]);
    }

    /**
     * PATCH /v1/admin/notifications/{id}/read
     */
    public function markAsRead(int $id, Request $request): JsonResponse
    {
        $success = $this->notificationService->markAsRead($id, $request->user()->id);

        if (! $success) {
            return $this->error('Notifikasi tidak ditemukan atau sudah dibaca.', 404);
        }

        return $this->success(null, 'Notifikasi ditandai sudah dibaca.');
    }

    /**
     * PATCH /v1/admin/notifications/read-all
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead($request->user()->id);
        return $this->success(['updated' => $count], "{$count} notifikasi ditandai sudah dibaca.");
    }
}