<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AdminRequest;
use App\Models\Booking;
use App\Models\Field;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ApiResponse;

    public function stats(): JsonResponse
    {
        $stats = [
            'total_users'           => User::where('role', 'customer')->count(),
            'total_admins'          => User::where('role', 'admin')->count(),
            'total_super_admins'    => User::where('role', 'super_admin')->count(),
            'total_fields'          => Field::count(),
            'total_bookings'        => Booking::count(),
            'pending_admin_requests'=> AdminRequest::where('status', 'pending')->count(),
            'active_users'          => User::where('is_active', true)->count(),
            'inactive_users'        => User::where('is_active', false)->count(),
        ];

        return $this->success($stats);
    }
}
