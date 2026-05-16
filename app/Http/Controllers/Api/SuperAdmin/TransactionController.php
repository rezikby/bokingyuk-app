<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    use ApiResponse;

    /**
     * GET /v1/super-admin/transactions
     *
     * Query params:
     *   search   – transaction_code / gateway_transaction_id atau nama customer
     *   status   – filter by payment status (paid|unpaid|expired|refunded)
     *   page     – halaman (default 1)
     *   per_page – jumlah per halaman (default 15, max 100)
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);
        $search  = $request->query('search', '');
        $status  = $request->query('status', '');

        $query = Payment::with([
                'booking:id,booking_code,user_id,field_id',
                'booking.user:id,name,email',
                'booking.field:id,name,admin_id',
                'booking.field.admin:id,name',
            ])
            ->latest();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('gateway_transaction_id', 'like', "%{$search}%")
                  ->orWhereHas('booking', function ($bq) use ($search) {
                      $bq->where('booking_code', 'like', "%{$search}%")
                         ->orWhereHas('user', fn ($u) =>
                             $u->where('name', 'like', "%{$search}%")
                         );
                  });
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        $paginated = $query->paginate($perPage);

        $paginated->getCollection()->transform(function (Payment $p) {
            $booking = $p->booking;

            return [
                'id'               => $p->id,
                'transaction_code' => $p->gateway_transaction_id ?? "TRX-{$p->id}",
                'amount'           => $p->amount,
                'payment_method'   => $p->method,
                'status'           => $p->status instanceof \BackedEnum ? $p->status->value : $p->status,
                'paid_at'          => $p->paid_at?->toISOString(),
                'expired_at'       => $p->expired_at?->toISOString(),
                'created_at'       => $p->created_at?->toISOString(),
                'booking'          => $booking ? [
                    'id'           => $booking->id,
                    'booking_code' => $booking->booking_code,
                    'user'         => $booking->user ? [
                        'id'    => $booking->user->id,
                        'name'  => $booking->user->name,
                        'email' => $booking->user->email,
                    ] : null,
                    'field'        => $booking->field ? [
                        'id'    => $booking->field->id,
                        'name'  => $booking->field->name,
                        'admin' => $booking->field->admin ? [
                            'id'   => $booking->field->admin->id,
                            'name' => $booking->field->admin->name,
                        ] : null,
                    ] : null,
                ] : null,
                'user'             => $booking?->user ? [
                    'id'   => $booking->user->id,
                    'name' => $booking->user->name,
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
