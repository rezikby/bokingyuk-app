<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\ActivityLogger;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    use ApiResponse;

    /**
     * Update profile info.
     */
    public function update(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name'            => 'sometimes|string|max:255',
            'phone'           => 'nullable|string|max:20',
            'whatsapp_number' => 'nullable|string|max:20',
            'address'         => 'nullable|string|max:500',
            'email'           => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->update($validated);

        // ✅ Log — catat field yang diperbarui (tanpa nilai sensitif)
        ActivityLogger::log(
            $user->id,
            'profile_update',
            'Informasi profil diperbarui.',
            ['updated_fields' => array_keys($validated)],
            $request
        );

        return $this->success(new UserResource($user->fresh()), 'Profil berhasil diperbarui.');
    }

    /**
     * Upload / change avatar.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $user = auth()->user();

        // Delete old avatar
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        // ✅ Log — foto profil diperbarui
        ActivityLogger::log(
            $user->id,
            'profile_update',
            'Foto profil diperbarui.',
            ['field' => 'avatar'],
            $request
        );

        return $this->success([
            'avatar_url' => asset('storage/' . $path),
        ], 'Avatar berhasil diperbarui.');
    }

    /**
     * Change password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|confirmed',
        ]);

        $user = auth()->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return $this->error('Password lama tidak sesuai.', 422);
        }

        $user->update(['password' => Hash::make($validated['new_password'])]);

        // ✅ Log — hanya catat bahwa password diubah, TIDAK menyimpan nilai password
        ActivityLogger::log(
            $user->id,
            'profile_update',
            'Password akun diubah.',
            ['field' => 'password'],
            $request
        );

        return $this->success(null, 'Password berhasil diubah.');
    }
}