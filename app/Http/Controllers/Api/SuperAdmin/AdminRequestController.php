<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminRequestResource;
use App\Models\AdminRequest;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class AdminRequestController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = AdminRequest::with(['user', 'reviewer'])->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $perPage  = min((int) $request->input('per_page', 10), 100);
        $requests = $query->paginate($perPage);

        return $this->success([
            'data'       => AdminRequestResource::collection($requests->items()),
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page'    => $requests->lastPage(),
                'per_page'     => $requests->perPage(),
                'total'        => $requests->total(),
            ],
        ]);
    }


    public function show(int $id): JsonResponse
    {
        $adminRequest = AdminRequest::with(['user', 'reviewer'])->findOrFail($id);

        return $this->success(new AdminRequestResource($adminRequest));
    }


    public function accept(int $id): JsonResponse
    {
        $adminRequest = AdminRequest::findOrFail($id);

        if ($adminRequest->status !== 'pending') {
            return $this->error('Request ini sudah diproses sebelumnya.', 422);
        }

        $adminRequest->update([
            'status'      => 'accepted',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        $adminRequest->user->update(['role' => 'admin']);

        return $this->success(
            new AdminRequestResource($adminRequest->fresh(['user', 'reviewer'])),
            'Request diterima. User telah dipromosikan menjadi admin.'
        );
    }


    public function reject(Request $request, int $id): JsonResponse
    {
        $adminRequest = AdminRequest::findOrFail($id);

        if ($adminRequest->status !== 'pending') {
            return $this->error('Request ini sudah diproses sebelumnya.', 422);
        }

        $validated = $request->validate([
            'rejection_note' => 'nullable|string|max:1000',
        ]);

        $adminRequest->update([
            'status'         => 'rejected',
            'rejection_note' => $validated['rejection_note'] ?? null,
            'reviewed_by'    => auth()->id(),
            'reviewed_at'    => now(),
        ]);

        return $this->success(
            new AdminRequestResource($adminRequest->fresh(['user', 'reviewer'])),
            'Request telah ditolak.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $adminRequest = AdminRequest::findOrFail($id);
        $adminRequest->delete();

        return $this->success(null, 'Request admin berhasil dihapus.');
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:admin_requests,id',
        ]);

        $deleted = AdminRequest::whereIn('id', $request->ids)->delete();

        return $this->success(null, "Berhasil menghapus {$deleted} request admin.");
    }
}