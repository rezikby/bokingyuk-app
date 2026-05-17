<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuthService $authService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return $this->created([
            'user'  => new UserResource($result['user']),
            'token' => $result['token'],
        ], 'Registrasi berhasil.');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login(
                $request->email,
                $request->password
            );

            return $this->success([
                'user'  => new UserResource($result['user']),
                'token' => $result['token'],
            ], 'Login berhasil.');
        } catch (AuthenticationException $e) {
            return $this->unauthorized($e->getMessage());
        }
    }

    public function logout(): JsonResponse
    {
        $this->authService->logout(auth()->user());

        return $this->success(null, 'Logout berhasil.');
    }

    public function me(): JsonResponse
    {
        return $this->success(new UserResource(auth()->user()));
    }

    public function googleRedirect(): JsonResponse
    {
        $url = $this->authService->getGoogleRedirectUrl();

        return $this->success(['url' => $url], 'Redirect URL Google berhasil dibuat.');
    }

    public function googleCallback(Request $request)
    {
        try {
            $result = $this->authService->googleCallback();

            return redirect()->away(
                env('FRONTEND_URL') . '/login?access_token=' . $result['token']
            );
        } catch (\Exception $e) {

            return redirect()->away(
                env('FRONTEND_URL') . '/login?error=google_failed'
            );
        }
    }
}
