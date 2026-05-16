<?php

namespace App\Services;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class AuthService
{
    public function __construct(private readonly Request $request) {}

    public function register(array $data): array
    {
        $user = User::create([
            'name'             => $data['name'],
            'email'            => $data['email'],
            'password'         => Hash::make($data['password']),
            'phone'            => $data['phone'] ?? null,
            'whatsapp_number'  => $data['whatsapp_number'] ?? $data['phone'] ?? null,
            'role'             => 'customer',
        ]);

        ActivityLogger::log($user->id, 'login', 'Registrasi akun baru', null, $this->request);

        $token = $user->createToken('api-token')->plainTextToken;

        return compact('user', 'token');
    }

    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new AuthenticationException('Email atau password salah.');
        }

        if (! $user->is_active) {
            throw new AuthenticationException('Akun Anda dinonaktifkan. Hubungi admin.');
        }

        $user->tokens()->delete();

        ActivityLogger::log($user->id, 'login', 'Login berhasil', null, $this->request);

        $token = $user->createToken('api-token')->plainTextToken;

        return compact('user', 'token');
    }

    public function logout(User $user): void
    {
        ActivityLogger::log($user->id, 'logout', 'Logout dari akun', null, $this->request);

        $user->currentAccessToken()->delete();
    }

    public function googleCallback(): array
    {
        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->user();
        } catch (\Exception $e) {
            throw new AuthenticationException('Login Google gagal: ' . $e->getMessage());
        }

        $user = User::where('google_id', $googleUser->getId())->first();

        if (! $user) {
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                $user->update(['google_id' => $googleUser->getId()]);
            } else {
                $user = User::create([
                    'name'              => $googleUser->getName(),
                    'email'             => $googleUser->getEmail(),
                    'google_id'         => $googleUser->getId(),
                    'avatar'            => $googleUser->getAvatar(),
                    'email_verified_at' => now(),
                    'role'              => 'customer',
                    'is_active'         => true,
                ]);

                ActivityLogger::log($user->id, 'login', 'Registrasi akun baru via Google', null, $this->request);
            }
        }

        if (! $user->is_active) {
            throw new AuthenticationException('Akun Anda dinonaktifkan. Hubungi admin.');
        }

        $user->tokens()->delete();

        ActivityLogger::log($user->id, 'login', 'Login berhasil via Google', null, $this->request);

        $token = $user->createToken('api-token')->plainTextToken;

        return compact('user', 'token');
    }

    public function getGoogleRedirectUrl(): string
    {
        return Socialite::driver('google')
            ->stateless()
            ->redirect()
            ->getTargetUrl();
    }
}