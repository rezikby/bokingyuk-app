<?php

namespace App\Jobs;

use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupOldNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $notificationDaysOld = 30,
        private readonly int $auditLogDaysOld = 90
    ) {}

    public function handle(
        NotificationService $notificationService,
        AuditLogService $auditLogService
    ): void {
        $deletedNotif  = $notificationService->deleteOld($this->notificationDaysOld);
        $deletedAudit  = $auditLogService->prune($this->auditLogDaysOld);

        Log::info('CleanupOldNotifications completed', [
            'notifications_deleted' => $deletedNotif,
            'audit_logs_deleted'    => $deletedAudit,
        ]);
    }
}
