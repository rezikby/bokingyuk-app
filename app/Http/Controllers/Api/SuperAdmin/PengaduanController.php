<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Pengaduan;
use Illuminate\Http\Request;

class PengaduanController extends Controller
{
    public function index(Request $request)
    {
        $query = Pengaduan::with('user')->latest();

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('user', function ($q2) use ($request) {
                    $q2->where('name', 'like', "%{$request->search}%")
                       ->orWhere('email', 'like', "%{$request->search}%");
                })->orWhere('subject', 'like', "%{$request->search}%");
            });
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $pengaduan = $query->get();

        return response()->json([
            'status' => 'success',
            'data'   => $pengaduan,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,proses,selesai,ditolak',
        ]);

        $pengaduan = Pengaduan::findOrFail($id);
        $pengaduan->update(['status' => $request->status]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Status pengaduan berhasil diperbarui',
            'data'    => $pengaduan->load('user'),
        ]);
    }

    public function destroy($id)
    {
        $pengaduan = Pengaduan::findOrFail($id);
        $pengaduan->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Pengaduan berhasil dihapus',
        ]);
    }

    // ── Bulk Delete ──────────────────────────────────────────────────────────
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:pengaduans,id',
        ]);

        $deleted = Pengaduan::whereIn('id', $request->ids)->delete();

        return response()->json([
            'status'  => 'success',
            'message' => "Berhasil menghapus {$deleted} pengaduan.",
        ]);
    }
}