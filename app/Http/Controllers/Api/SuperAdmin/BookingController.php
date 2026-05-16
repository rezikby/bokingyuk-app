<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    use ApiResponse;

    /**
     * GET /v1/super-admin/bookings
     *
     * Query params:
     *   search   – booking_code atau nama customer (partial, case-insensitive)
     *   status   – filter by booking status (pending|confirmed|checked_in|completed|cancelled)
     *   page     – halaman (default 1)
     *   per_page – jumlah per halaman (default 15, max 100)
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);
        $search  = $request->query('search', '');
        $status  = $request->query('status', '');

        $query = Booking::with([
                'user:id,name,email',
                'field:id,name,admin_id',
                'field.admin:id,name',
            ])
            ->latest('booking_date');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('booking_code', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($u) =>
                        $u->where('name', 'like', "%{$search}%")
                    );
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        $paginated = $query->paginate($perPage);

        $paginated->getCollection()->transform(function (Booking $b) {
            return [
                'id'             => $b->id,
                'booking_code'   => $b->booking_code,
                'booking_date'   => $b->booking_date?->toDateString(),
                'start_time'     => $b->start_time?->format('H:i'),
                'end_time'       => $b->end_time?->format('H:i'),
                'total_price'    => $b->total_price,
                'status'         => $b->status instanceof \BackedEnum ? $b->status->value : $b->status,
                'payment_status' => $b->payment_status instanceof \BackedEnum ? $b->payment_status->value : $b->payment_status,
                'created_at'     => $b->created_at?->toISOString(),
                'user'           => $b->user ? [
                    'id'    => $b->user->id,
                    'name'  => $b->user->name,
                    'email' => $b->user->email,
                ] : null,
                'field'          => $b->field ? [
                    'id'    => $b->field->id,
                    'name'  => $b->field->name,
                    'admin' => $b->field->admin ? [
                        'id'   => $b->field->admin->id,
                        'name' => $b->field->admin->name,
                    ] : null,
                ] : null,
            ];
        });

        return $this->success([
            'data'      => $paginated->items(),
            'total'     => $paginated->total(),
            'per_page'  => $paginated->perPage(),
            'page'      => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
        ]);
    }
}
