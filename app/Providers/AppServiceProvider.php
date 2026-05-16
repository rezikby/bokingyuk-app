<?php

namespace App\Providers;

use App\Repositories\Eloquent\BookingRepository;
use App\Repositories\Eloquent\FieldRepository;
use App\Repositories\Interfaces\BookingRepositoryInterface;
use App\Repositories\Interfaces\FieldRepositoryInterface;
use App\Services\AuditLogService;
use App\Services\ExportService;
use App\Services\MaintenanceService;
use App\Services\NotificationService;
use App\Services\PaymentHistoryService;
use App\Services\RatingService;
use App\Services\SiteSettingService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ── Repositories ──────────────────────────────────────────────────────
        $this->app->bind(BookingRepositoryInterface::class, BookingRepository::class);
        $this->app->bind(FieldRepositoryInterface::class, FieldRepository::class);

        // ── Feature Services ──────────────────────────────────────────────────
        $this->app->singleton(NotificationService::class);   // Fitur 3
        $this->app->singleton(PaymentHistoryService::class); // Fitur 1
        $this->app->singleton(RatingService::class);         // Fitur 2
        $this->app->singleton(MaintenanceService::class);    // Fitur 4
        $this->app->singleton(ExportService::class);         // Fitur 5
        $this->app->singleton(AuditLogService::class);       // Fitur 6
        $this->app->singleton(SiteSettingService::class);    // Fitur 7
    }

    public function boot(): void {}
}
