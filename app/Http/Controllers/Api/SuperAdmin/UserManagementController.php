<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    use ApiResponse;

    /**
     * List all users (with pagination, search, filter by role, sort).
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        // Search by name or email
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        // Sort
        $sortBy  = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $allowedSorts = ['name', 'email', 'role', 'is_active', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min((int) $request->input('per_page', 10), 100);
        $users   = $query->paginate($perPage);

        return $this->success([
            'data'       => UserResource::collection($users->items()),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'per_page'     => $users->perPage(),
                'total'        => $users->total(),
            ],
        ]);
    }

    /**
     * Show single user detail.
     */
    public function show(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        return $this->success(new UserResource($user));
    }

    /**
     * Create a new user (or admin).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => 'required|email|unique:users,email',
            'password'         => 'required|string|min:8',
            'phone'            => 'nullable|string|max:20',
            'whatsapp_number'  => 'nullable|string|max:20',
            'role'             => 'required|in:customer,admin,super_admin',
            'is_active'        => 'boolean',
            'address'          => 'nullable|string|max:500',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = $validated['is_active'] ?? true;

        $user = User::create($validated);

        return $this->created(new UserResource($user), 'User berhasil dibuat.');
    }

    /**
     * Update user data.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name'            => 'sometimes|string|max:255',
            'email'           => ['sometimes', 'email', Rule::unique('users')->ignore($id)],
            'password'        => 'sometimes|string|min:8',
            'phone'           => 'nullable|string|max:20',
            'whatsapp_number' => 'nullable|string|max:20',
            'role'            => 'sometimes|in:customer,admin,super_admin',
            'is_active'       => 'sometimes|boolean',
            'address'         => 'nullable|string|max:500',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return $this->success(new UserResource($user->fresh()), 'User berhasil diperbarui.');
    }

    /**
     * Delete a user.
     */
    public function destroy(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        // Prevent deleting yourself
        if (auth()->id() === $user->id) {
            return $this->error('Tidak dapat menghapus akun sendiri.', 422);
        }

        $user->delete();

        return $this->success(null, 'User berhasil dihapus.');
    }

    /**
     * Toggle is_active status.
     */
    public function toggleActive(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if (auth()->id() === $user->id) {
            return $this->error('Tidak dapat menonaktifkan akun sendiri.', 422);
        }

        $user->update(['is_active' => ! $user->is_active]);

        $status = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return $this->success(new UserResource($user->fresh()), "User berhasil {$status}.");
    }
}
