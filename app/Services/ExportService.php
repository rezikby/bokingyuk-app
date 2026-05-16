<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExportService
{
    /**
     * Export laporan revenue ke CSV.
     */
    public function exportRevenueCsv(string $from, string $to, ?int $fieldId = null, ?int $adminId = null): string
    {
        $rows = Booking::join('payments', 'bookings.id', '=', 'payments.booking_id')
            ->join('fields', 'bookings.field_id', '=', 'fields.id')
            ->join('users', 'bookings.user_id', '=', 'users.id')
            ->where('payments.status', PaymentStatus::Paid->value)
            ->whereBetween('bookings.booking_date', [$from, $to])
            ->when($fieldId, fn($q) => $q->where('bookings.field_id', $fieldId))
            ->when($adminId, fn($q) => $q->where('fields.admin_id', $adminId))
            ->select([
                'bookings.booking_code',
                'users.name as customer_name',
                'users.email as customer_email',
                'fields.name as field_name',
                'bookings.booking_date',
                'bookings.start_time',
                'bookings.end_time',
                'bookings.duration_hours',
                'bookings.status',
                'payments.amount',
                'payments.method as payment_method',
                'payments.paid_at',
            ])
            ->orderBy('bookings.booking_date')
            ->get();

        $headers = [
            'Kode Booking', 'Nama Customer', 'Email', 'Lapangan',
            'Tanggal', 'Jam Mulai', 'Jam Selesai', 'Durasi (Jam)',
            'Status', 'Jumlah Bayar', 'Metode Pembayaran', 'Tanggal Bayar',
        ];

        $data = $rows->map(fn($r) => [
            $r->booking_code,
            $r->customer_name,
            $r->customer_email,
            $r->field_name,
            $r->booking_date,
            substr($r->start_time, 0, 5),
            substr($r->end_time, 0, 5),
            $r->duration_hours,
            $r->status instanceof \App\Enums\BookingStatus ? $r->status->value : (string) $r->status,
            $r->amount,
            $r->payment_method ?? '-',
            $r->paid_at ? date('Y-m-d H:i', strtotime($r->paid_at)) : '-',
        ]);

        return $this->buildCsv($headers, $data);
    }

    /**
     * Export ringkasan harian ke CSV.
     */
    public function exportDailySummaryCsv(string $from, string $to, ?int $adminId = null): string
    {
        $rows = DB::table('bookings')
            ->join('payments', 'bookings.id', '=', 'payments.booking_id')
            ->join('fields', 'bookings.field_id', '=', 'fields.id')
            ->where('payments.status', PaymentStatus::Paid->value)
            ->whereBetween('bookings.booking_date', [$from, $to])
            ->when($adminId, fn($q) => $q->where('fields.admin_id', $adminId))
            ->selectRaw('
                DATE(bookings.booking_date) as tanggal,
                COUNT(bookings.id) as total_booking,
                SUM(payments.amount) as total_pendapatan,
                AVG(payments.amount) as rata_rata
            ')
            ->groupBy('tanggal')
            ->orderBy('tanggal')
            ->get();

        $headers = ['Tanggal', 'Total Booking', 'Total Pendapatan (Rp)', 'Rata-rata (Rp)'];

        $data = $rows->map(fn($r) => [
            $r->tanggal,
            $r->total_booking,
            number_format((float) $r->total_pendapatan, 0, ',', '.'),
            number_format((float) $r->rata_rata, 0, ',', '.'),
        ]);

        return $this->buildCsv($headers, $data);
    }

    /**
     * Export laporan per lapangan ke CSV.
     */
    public function exportFieldReportCsv(string $from, string $to, ?int $adminId = null): string
    {
        $rows = DB::table('bookings')
            ->join('fields', 'bookings.field_id', '=', 'fields.id')
            ->join('payments', 'bookings.id', '=', 'payments.booking_id')
            ->where('payments.status', PaymentStatus::Paid->value)
            ->whereBetween('bookings.booking_date', [$from, $to])
            ->when($adminId, fn($q) => $q->where('fields.admin_id', $adminId))
            ->selectRaw('
                fields.name as lapangan,
                COUNT(bookings.id) as total_booking,
                SUM(bookings.duration_hours) as total_jam,
                SUM(payments.amount) as total_pendapatan,
                AVG(fields.avg_rating) as rating
            ')
            ->groupBy('fields.id', 'fields.name')
            ->orderByDesc('total_pendapatan')
            ->get();

        $headers = ['Lapangan', 'Total Booking', 'Total Jam', 'Total Pendapatan (Rp)', 'Rating'];

        $data = $rows->map(fn($r) => [
            $r->lapangan,
            $r->total_booking,
            $r->total_jam,
            number_format((float) $r->total_pendapatan, 0, ',', '.'),
            number_format((float) ($r->rating ?? 0), 2),
        ]);

        return $this->buildCsv($headers, $data);
    }

    /**
     * Export users ke CSV.
     *
     * @param bool $includeAdmin  true = semua role (customer + admin + super_admin)
     *                            false = hanya customer (default)
     * @param int|null $adminId   Jika diisi dan includeAdmin=false,
     *                            hanya customer yang pernah booking ke field milik admin tsb.
     */
    public function exportUsersCsv(bool $includeAdmin = false, ?int $adminId = null): string
    {
        if ($includeAdmin) {
            // Semua user — customer, admin, super_admin
            $rows = DB::table('users')
                ->leftJoin('bookings', 'users.id', '=', 'bookings.user_id')
                ->leftJoin('payments', function ($join) {
                    $join->on('bookings.id', '=', 'payments.booking_id')
                         ->where('payments.status', PaymentStatus::Paid->value);
                })
                ->selectRaw('
                    users.name,
                    users.email,
                    users.phone,
                    users.role,
                    users.is_active,
                    DATE(users.created_at) as bergabung,
                    COUNT(DISTINCT bookings.id) as total_booking,
                    COALESCE(SUM(payments.amount), 0) as total_spent
                ')
                ->groupBy('users.id', 'users.name', 'users.email', 'users.phone', 'users.role', 'users.is_active', 'users.created_at')
                ->orderByRaw("CASE users.role WHEN 'super_admin' THEN 1 WHEN 'admin' THEN 2 ELSE 3 END")
                ->orderBy('users.name')
                ->get();

            $headers = ['Nama', 'Email', 'Telepon', 'Role', 'Status', 'Bergabung', 'Total Booking', 'Total Pengeluaran (Rp)'];

            $data = $rows->map(fn($r) => [
                $r->name,
                $r->email,
                $r->phone ?? '-',
                match ($r->role) {
                    'super_admin' => 'Super Admin',
                    'admin'       => 'Admin',
                    default       => 'Customer',
                },
                $r->is_active ? 'Aktif' : 'Nonaktif',
                $r->bergabung,
                $r->total_booking,
                number_format((float) $r->total_spent, 0, ',', '.'),
            ]);

            return $this->buildCsv($headers, $data);
        }

        // Hanya customer
        $query = DB::table('users')->where('users.role', 'customer');

        if ($adminId) {
            // Hanya customer yang pernah booking ke field milik admin ini
            $query
                ->join('bookings', 'users.id', '=', 'bookings.user_id')
                ->join('fields', function ($join) use ($adminId) {
                    $join->on('bookings.field_id', '=', 'fields.id')
                         ->where('fields.admin_id', $adminId);
                })
                ->leftJoin('payments', function ($join) {
                    $join->on('bookings.id', '=', 'payments.booking_id')
                         ->where('payments.status', PaymentStatus::Paid->value);
                });
        } else {
            // Semua customer, termasuk yang belum pernah booking
            $query
                ->leftJoin('bookings', 'users.id', '=', 'bookings.user_id')
                ->leftJoin('payments', function ($join) {
                    $join->on('bookings.id', '=', 'payments.booking_id')
                         ->where('payments.status', PaymentStatus::Paid->value);
                });
        }

        $rows = $query
            ->selectRaw('
                users.name,
                users.email,
                users.phone,
                users.is_active,
                DATE(users.created_at) as bergabung,
                COUNT(DISTINCT bookings.id) as total_booking,
                COALESCE(SUM(payments.amount), 0) as total_spent
            ')
            ->groupBy('users.id', 'users.name', 'users.email', 'users.phone', 'users.is_active', 'users.created_at')
            ->orderByDesc('total_spent')
            ->get();

        $headers = ['Nama', 'Email', 'Telepon', 'Status', 'Bergabung', 'Total Booking', 'Total Pengeluaran (Rp)'];

        $data = $rows->map(fn($r) => [
            $r->name,
            $r->email,
            $r->phone ?? '-',
            $r->is_active ? 'Aktif' : 'Nonaktif',
            $r->bergabung,
            $r->total_booking,
            number_format((float) $r->total_spent, 0, ',', '.'),
        ]);

        return $this->buildCsv($headers, $data);
    }

    /**
     * @deprecated Gunakan exportUsersCsv() — dipertahankan agar tidak ada breaking change.
     */
    public function exportCustomersCsv(?int $adminId = null): string
    {
        return $this->exportUsersCsv(false, $adminId);
    }

    // ─── Private ───────────────────────────────────────────────────────────────

    /**
     * Build CSV string dari headers dan rows.
     *
     * Menerima Collection berisi array (bukan stdClass / Eloquent model),
     * sehingga tidak perlu memanggil toArray() yang akan crash pada stdClass.
     */
    private function buildCsv(array $headers, Collection $rows): string
    {
        $output = fopen('php://temp', 'r+');

        // BOM agar Excel membuka UTF-8 dengan benar
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, $headers, ';');

        foreach ($rows as $row) {
            // $row sudah berupa array dari ->map(fn => [...])
            fputcsv($output, $row, ';');
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}