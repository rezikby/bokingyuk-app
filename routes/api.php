<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\Customer;
use App\Http\Controllers\Api\SuperAdmin;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\Mobile\CheckInController as MobileCheckInController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\Admin\SyncPaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {

    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);

    Route::get('google',          [AuthController::class, 'googleRedirect']);
    Route::get('google/callback', [AuthController::class, 'googleCallback']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me',      [AuthController::class, 'me']);
    });
});

/*
|--------------------------------------------------------------------------
| WEBHOOK
|--------------------------------------------------------------------------
*/

Route::prefix('webhook')->group(function () {
    Route::post('payment', [PaymentWebhookController::class, 'handle']);
});

/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

Route::prefix('mobile')->group(function () {

    Route::get('checkin/{qr_token}', [
        MobileCheckInController::class,
        'checkInByUrl',
    ])->name('mobile.checkin');

    Route::get('booking/{qr_token}', [
        MobileCheckInController::class,
        'bookingInfo',
    ])->name('mobile.booking.info');
});

/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('v1')
    ->middleware('auth:sanctum')
    ->group(function () {

        /*
        |----------------------------------------------------------------------
        | PROFILE
        |----------------------------------------------------------------------
        */

        Route::prefix('profile')->group(function () {

            Route::put('/', [
                ProfileController::class,
                'update',
            ]);

            Route::post('avatar', [
                ProfileController::class,
                'uploadAvatar',
            ]);

            Route::put('change-password', [
                ProfileController::class,
                'changePassword',
            ]);
        });

        /*
        |----------------------------------------------------------------------
        | CUSTOMER - FIELDS
        |----------------------------------------------------------------------
        */

        Route::prefix('fields')->group(function () {

            Route::get('/', [
                Customer\FieldController::class,
                'index',
            ]);

            Route::get('{id}', [
                Customer\FieldController::class,
                'show',
            ]);
        });

        /*
        |----------------------------------------------------------------------
        | CUSTOMER - BOOKINGS
        |
        | PENTING: Route statis WAJIB sebelum route dinamis {code}
        | agar Laravel tidak menangkap "available-slots" atau
        | "check-payment" sebagai nilai {code}.
        |----------------------------------------------------------------------
        */

        Route::prefix('bookings')->group(function () {

            Route::get('/', [
                Customer\BookingController::class,
                'index',
            ]);

            Route::post('/', [
                Customer\BookingController::class,
                'store',
            ]);

            // ── Statis (harus SEBELUM {code}) ────────────────────────────────

            Route::get('available-slots', [
                Customer\BookingController::class,
                'availableSlots',
            ]);

            // ← BARU: Customer cek status bayar langsung ke Midtrans.
            //   Dipanggil Payment.jsx setiap 3 detik.
            //   Backend cek ke Midtrans & auto-confirm jika lunas —
            //   tidak perlu admin online.
            Route::get('{code}/check-payment', [
                Customer\BookingController::class,
                'checkPaymentStatus',
            ]);

            // ── Dinamis ({code}) ──────────────────────────────────────────────

            Route::get('{code}', [
                Customer\BookingController::class,
                'show',
            ]);

            Route::patch('{code}/cancel', [
                Customer\BookingController::class,
                'cancel',
            ]);

            Route::post('{code}/refresh-payment-token', [
                Customer\BookingController::class,
                'refreshPaymentToken',
            ]);
        });

        /*
        |----------------------------------------------------------------------
        | CUSTOMER - ADMIN REQUESTS
        |----------------------------------------------------------------------
        */

        Route::prefix('admin-requests')->group(function () {

            Route::get('/', [
                Customer\AdminRequestController::class,
                'index',
            ]);

            Route::get('{id}', [
                Customer\AdminRequestController::class,
                'show',
            ]);

            Route::post('/', [
                Customer\AdminRequestController::class,
                'store',
            ]);
        });

        /*
        |----------------------------------------------------------------------
        | CUSTOMER - PENGADUAN
        |----------------------------------------------------------------------
        */

        Route::prefix('pengaduan')->group(function () {

            Route::get('/', [
                Customer\PengaduanController::class,
                'index',
            ]);

            Route::get('{id}', [
                Customer\PengaduanController::class,
                'show',
            ]);

            Route::post('/', [
                Customer\PengaduanController::class,
                'store',
            ]);
        });

        /*
        |----------------------------------------------------------------------
        | CUSTOMER - ACTIVITY LOGS
        |----------------------------------------------------------------------
        */

        Route::prefix('user')->group(function () {

            Route::get('activity-logs', [
                Customer\ActivityLogController::class,
                'index',
            ]);
        });

        /*
        |----------------------------------------------------------------------
        | CUSTOMER - PAYMENT HISTORY
        |----------------------------------------------------------------------
        */

        Route::prefix('payment-history')->group(function () {

            Route::get('/', [
                Customer\PaymentHistoryController::class,
                'index',
            ]);

            Route::get('booking/{bookingCode}', [
                Customer\PaymentHistoryController::class,
                'byBooking',
            ]);
        });

        /*
        |----------------------------------------------------------------------
        | CUSTOMER - NOTIFICATIONS
        |----------------------------------------------------------------------
        */

        Route::prefix('notifications')->group(function () {

            Route::get('/', [
                Customer\NotificationController::class,
                'index',
            ]);

            Route::get('unread-count', [
                Customer\NotificationController::class,
                'unreadCount',
            ]);

            Route::patch('read-all', [
                Customer\NotificationController::class,
                'markAllAsRead',
            ]);

            Route::patch('{id}/read', [
                Customer\NotificationController::class,
                'markAsRead',
            ]);
        });

        /*
        |----------------------------------------------------------------------
        | CUSTOMER - RATINGS
        |----------------------------------------------------------------------
        */

        Route::get('fields/{fieldId}/ratings', [
            Customer\RatingController::class,
            'index',
        ]);

        Route::post('bookings/{bookingCode}/rating', [
            Customer\RatingController::class,
            'store',
        ]);

        /*
        |----------------------------------------------------------------------
        | ADMIN
        |----------------------------------------------------------------------
        */

        Route::prefix('admin')
            ->middleware('admin')
            ->group(function () {

                Route::get('dashboard', [
                    Admin\DashboardController::class,
                    'stats',
                ]);

                Route::apiResource('fields', Admin\FieldController::class);

                Route::prefix('bookings')->group(function () {

                    Route::get('/', [
                        Admin\BookingController::class,
                        'index',
                    ]);

                    Route::get('sync-payments', [
                        SyncPaymentController::class,
                        'sync',
                    ]);

                    Route::post('check-in', [
                        Admin\BookingController::class,
                        'checkIn',
                    ]);

                    Route::get('{code}', [
                        Admin\BookingController::class,
                        'show',
                    ]);

                    Route::patch('{code}/cancel', [
                        Admin\BookingController::class,
                        'cancel',
                    ]);

                    Route::patch('{code}/confirm-payment', [
                        Admin\BookingController::class,
                        'confirmPayment',
                    ]);
                });

                Route::prefix('notifications')->group(function () {

                    Route::get('/', [
                        Admin\NotificationController::class,
                        'index',
                    ]);

                    Route::get('unread-count', [
                        Admin\NotificationController::class,
                        'unreadCount',
                    ]);

                    Route::patch('read-all', [
                        Admin\NotificationController::class,
                        'markAllAsRead',
                    ]);

                    Route::patch('{id}/read', [
                        Admin\NotificationController::class,
                        'markAsRead',
                    ]);
                });

                Route::prefix('reports')->group(function () {

                    Route::get('revenue', [
                        Admin\ReportController::class,
                        'revenue',
                    ]);

                    Route::get('predict-busy-hours/{fieldId}', [
                        Admin\ReportController::class,
                        'predictBusyHours',
                    ]);
                });

                Route::prefix('payment-history')->group(function () {

                    Route::get('/', [
                        Admin\PaymentHistoryController::class,
                        'index',
                    ]);

                    Route::get('booking/{id}', [
                        Admin\PaymentHistoryController::class,
                        'byBooking',
                    ]);
                });

                Route::prefix('ratings')->group(function () {

                    Route::get('/', [
                        Admin\RatingController::class,
                        'index',
                    ]);

                    Route::patch('{id}/toggle-visibility', [
                        Admin\RatingController::class,
                        'toggleVisibility',
                    ]);
                });

                Route::prefix('maintenances')->group(function () {

                    Route::delete('bulk-delete', [
                        Admin\MaintenanceController::class,
                        'bulkDelete',
                    ]);

                    Route::get('/', [
                        Admin\MaintenanceController::class,
                        'index',
                    ]);

                    Route::post('/', [
                        Admin\MaintenanceController::class,
                        'store',
                    ]);

                    Route::get('{id}', [
                        Admin\MaintenanceController::class,
                        'show',
                    ]);

                    Route::put('{id}', [
                        Admin\MaintenanceController::class,
                        'update',
                    ]);

                    Route::patch('{id}/cancel', [
                        Admin\MaintenanceController::class,
                        'cancel',
                    ]);
                });

                Route::prefix('export')->group(function () {

                    Route::get('revenue', [
                        Admin\ExportController::class,
                        'revenue',
                    ]);

                    Route::get('daily-summary', [
                        Admin\ExportController::class,
                        'dailySummary',
                    ]);

                    Route::get('field-report', [
                        Admin\ExportController::class,
                        'fieldReport',
                    ]);

                    Route::get('customers', [
                        Admin\ExportController::class,
                        'customers',
                    ]);
                });
            });

        /*
        |----------------------------------------------------------------------
        | SUPER ADMIN
        |----------------------------------------------------------------------
        */

        Route::prefix('super-admin')
            ->middleware('super_admin')
            ->group(function () {

                Route::get('dashboard', [
                    SuperAdmin\DashboardController::class,
                    'stats',
                ]);

                Route::get('bookings', [
                    SuperAdmin\BookingController::class,
                    'index',
                ]);

                Route::get('transactions', [
                    SuperAdmin\TransactionController::class,
                    'index',
                ]);

                Route::post('pengaduan/bulk-delete', [
                    SuperAdmin\PengaduanController::class,
                    'bulkDelete',
                ]);

                Route::get('pengaduan', [
                    SuperAdmin\PengaduanController::class,
                    'index',
                ]);

                Route::patch('pengaduan/{id}', [
                    SuperAdmin\PengaduanController::class,
                    'update',
                ]);

                Route::delete('pengaduan/{id}', [
                    SuperAdmin\PengaduanController::class,
                    'destroy',
                ]);

                Route::prefix('users')->group(function () {

                    Route::get('/', [
                        SuperAdmin\UserManagementController::class,
                        'index',
                    ]);

                    Route::post('/', [
                        SuperAdmin\UserManagementController::class,
                        'store',
                    ]);

                    Route::get('{id}', [
                        SuperAdmin\UserManagementController::class,
                        'show',
                    ]);

                    Route::put('{id}', [
                        SuperAdmin\UserManagementController::class,
                        'update',
                    ]);

                    Route::delete('{id}', [
                        SuperAdmin\UserManagementController::class,
                        'destroy',
                    ]);

                    Route::patch('{id}/toggle-active', [
                        SuperAdmin\UserManagementController::class,
                        'toggleActive',
                    ]);
                });

                Route::prefix('admin-requests')->group(function () {

                    Route::post('bulk-delete', [
                        SuperAdmin\AdminRequestController::class,
                        'bulkDelete',
                    ]);

                    Route::get('/', [
                        SuperAdmin\AdminRequestController::class,
                        'index',
                    ]);

                    Route::get('{id}', [
                        SuperAdmin\AdminRequestController::class,
                        'show',
                    ]);

                    Route::post('{id}/accept', [
                        SuperAdmin\AdminRequestController::class,
                        'accept',
                    ]);

                    Route::post('{id}/reject', [
                        SuperAdmin\AdminRequestController::class,
                        'reject',
                    ]);

                    Route::delete('{id}', [
                        SuperAdmin\AdminRequestController::class,
                        'destroy',
                    ]);
                });

                Route::prefix('audit-logs')->group(function () {

                    Route::get('/', [
                        SuperAdmin\AuditLogController::class,
                        'index',
                    ]);

                    Route::get('summary', [
                        SuperAdmin\AuditLogController::class,
                        'summary',
                    ]);

                    Route::get('model/{type}/{id}', [
                        SuperAdmin\AuditLogController::class,
                        'byModel',
                    ]);

                    Route::delete('prune', [
                        SuperAdmin\AuditLogController::class,
                        'prune',
                    ]);
                });

                Route::prefix('settings')->group(function () {

                    Route::get('/', [
                        SuperAdmin\SiteSettingController::class,
                        'index',
                    ]);

                    Route::put('/', [
                        SuperAdmin\SiteSettingController::class,
                        'updateMany',
                    ]);

                    Route::get('{group}', [
                        SuperAdmin\SiteSettingController::class,
                        'byGroup',
                    ]);

                    Route::patch('{key}', [
                        SuperAdmin\SiteSettingController::class,
                        'update',
                    ]);
                });
            });
    });