<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\PengaduanResource;
use App\Models\Pengaduan;
use App\Services\ActivityLogger;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PengaduanController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/pengaduan
     * Daftar pengaduan milik user yang login
     */
    public function index(): JsonResponse
    {
        $pengaduans = Pengaduan::where('user_id', auth()->id())
            ->latest()
            ->get();

        return $this->success(PengaduanResource::collection($pengaduans));
    }

    /**
     * GET /api/v1/pengaduan/{id}
     */
    public function show(int $id): JsonResponse
    {
        $pengaduan = Pengaduan::where('user_id', auth()->id())->find($id);

        if (! $pengaduan) {
            return $this->notFound('Pengaduan tidak ditemukan.');
        }

        return $this->success(new PengaduanResource($pengaduan));
    }

    /**
     * POST /api/v1/pengaduan
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kategori'      => 'required|string|max:100',
            'judul'         => 'required|string|max:100',
            'detail'        => 'required|string|min:10',
            'lampiran'      => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'captcha_token' => 'required|string',
        ]);

        // Verifikasi reCAPTCHA — skip di local/development
        if (! app()->isLocal()) {
            $captchaResponse = Http::asForm()->post(
                'https://www.google.com/recaptcha/api/siteverify',
                [
                    'secret'   => config('services.recaptcha.secret'),
                    'response' => $validated['captcha_token'],
                    'remoteip' => $request->ip(),
                ]
            );

            if (! $captchaResponse->json('success')) {
                return $this->error('Verifikasi CAPTCHA gagal. Silakan coba lagi.', 422);
            }
        }

        $lampiranPath = null;
        if ($request->hasFile('lampiran')) {
            $lampiranPath = $request->file('lampiran')->store('pengaduan/lampiran', 'public');
        }

        $pengaduan = Pengaduan::create([
            'user_id'  => auth()->id(),
            'kategori' => $validated['kategori'],
            'judul'    => $validated['judul'],
            'detail'   => $validated['detail'],
            'lampiran' => $lampiranPath,
            'status'   => 'pending',
        ]);

        // Catat log aktivitas
        ActivityLogger::log(
            auth()->id(),
            'pengaduan',
            'Mengirim pengaduan: ' . $pengaduan->judul,
            ['pengaduan_id' => $pengaduan->id],
            $request
        );

        return $this->created(
            new PengaduanResource($pengaduan),
            'Pengaduan berhasil dikirim. Tim kami akan meninjau dalam 1–3 hari kerja.'
        );
    }
}