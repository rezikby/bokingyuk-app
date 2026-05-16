<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminRequestResource;
use App\Models\AdminRequest;
use App\Services\ActivityLogger; // ✅ BARU
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AdminRequestController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $requests = AdminRequest::where('user_id', auth()->id())
            ->latest()
            ->get();

        return $this->success(AdminRequestResource::collection($requests));
    }

    public function show(int $id): JsonResponse
    {
        $adminRequest = AdminRequest::where('user_id', auth()->id())
            ->findOrFail($id);

        return $this->success(new AdminRequestResource($adminRequest));
    }

    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();

        if ($user->hasAdminAccess()) {
            return $this->error('Anda sudah memiliki akses admin.', 422);
        }

        $existing = AdminRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return $this->error('Anda sudah memiliki pengajuan yang sedang menunggu persetujuan.', 422);
        }

        $validated = $request->validate([
            'full_name'     => 'required|string|max:255',
            'email'         => 'required|email|max:255',
            'phone'         => 'required|string|max:20',
            'address'       => 'required|string|max:1000',
            'ktp_image'     => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'selfie_image'  => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'reason'        => 'required|string|min:20|max:2000',
            'captcha_token' => 'required|string',
        ]);

        if (! app()->isLocal()) {
            $captchaResponse = Http::asForm()->post(
                'https://hcaptcha.com/siteverify',
                [
                    'secret'   => env('HCAPTCHA_SECRET_KEY'),
                    'response' => $validated['captcha_token'],
                    'remoteip' => $request->ip(),
                ]
            );

            if (! $captchaResponse->json('success')) {
                return $this->error('Verifikasi CAPTCHA gagal. Silakan coba lagi.', 422);
            }
        }

        $ktpPath    = $request->file('ktp_image')->store('admin-requests/ktp', 'public');
        $selfiePath = $request->file('selfie_image')->store('admin-requests/selfie', 'public');

        $adminRequest = AdminRequest::create([
            'user_id'      => $user->id,
            'full_name'    => $validated['full_name'],
            'email'        => $validated['email'],
            'phone'        => $validated['phone'],
            'address'      => $validated['address'],
            'ktp_image'    => $ktpPath,
            'selfie_image' => $selfiePath,
            'reason'       => $validated['reason'],
            'status'       => 'pending',
        ]);

        // ✅ Catat log pengajuan admin
        ActivityLogger::log(
            $user->id,
            'pengajuan_admin',
            'Mengajukan permohonan menjadi admin',
            ['admin_request_id' => $adminRequest->id],
            $request
        );

        return $this->created(
            new AdminRequestResource($adminRequest),
            'Pengajuan berhasil dikirim. Mohon tunggu persetujuan super admin.'
        );
    }
}