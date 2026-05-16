<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $apiKey;
    private string $apiUrl = 'https://api.fonnte.com/send';

    public function __construct()
    {
        $this->apiKey = config('services.fonnte.api_key', '');
    }

    public function sendBookingConfirmation(Booking $booking): void
    {
        $message = $this->buildConfirmationMessage($booking);
        $this->send($booking->user->whatsapp_number ?? $booking->user->phone, $message);
    }

    public function sendCheckInConfirmation(Booking $booking): void
    {
        $message = "✅ *Check-In Berhasil!*\n\n"
            . "Kode Booking: *{$booking->booking_code}*\n"
            . "Lapangan: *{$booking->field->name}*\n"
            . "Waktu: {$booking->start_time} - {$booking->end_time}\n\n"
            . "Selamat bermain! 🏟️";

        $this->send($booking->user->whatsapp_number ?? $booking->user->phone, $message);
    }

    public function sendCancellationNotice(Booking $booking): void
    {
        $message = "❌ *Booking Dibatalkan*\n\n"
            . "Kode Booking: *{$booking->booking_code}*\n"
            . "Lapangan: *{$booking->field->name}*\n"
            . "Tanggal: {$booking->booking_date->format('d M Y')}\n\n"
            . "Jika ada pertanyaan hubungi admin. Terima kasih.";

        $this->send($booking->user->whatsapp_number ?? $booking->user->phone, $message);
    }

    public function sendPaymentReminder(Booking $booking): void
    {
        $message = "⏰ *Reminder Pembayaran*\n\n"
            . "Booking *{$booking->booking_code}* akan kadaluarsa dalam 30 menit.\n"
            . "Segera selesaikan pembayaran sebesar Rp " . number_format($booking->total_price, 0, ',', '.') . ".\n\n"
            . "Link: {$booking->payment?->payment_url}";

        $this->send($booking->user->whatsapp_number ?? $booking->user->phone, $message);
    }

    // ─── Private ───────────────────────────────────────────────────────────────

    private function buildConfirmationMessage(Booking $booking): string
    {
        return "🎉 *Booking Berhasil!*\n\n"
            . "Kode Booking: *{$booking->booking_code}*\n"
            . "Lapangan: *{$booking->field->name}*\n"
            . "Tanggal: {$booking->booking_date->format('d M Y')}\n"
            . "Waktu: {$booking->start_time} - {$booking->end_time}\n"
            . "Total: Rp " . number_format($booking->total_price, 0, ',', '.') . "\n\n"
            . "Segera lakukan pembayaran sebelum booking kadaluarsa.\n"
            . "Link Pembayaran: {$booking->payment?->payment_url}";
    }

    private function send(string $phone, string $message): void
    {
        if (empty($this->apiKey) || empty($phone)) {
            Log::warning('WhatsApp not sent — missing API key or phone number.');
            return;
        }

        try {
            $response = Http::withHeaders(['Authorization' => $this->apiKey])
                ->timeout(10)
                ->post($this->apiUrl, [
                    'target'  => $phone,
                    'message' => $message,
                ]);

            Log::info('WhatsApp sent', ['phone' => $phone, 'status' => $response->status()]);
        } catch (\Exception $e) {
            Log::error('WhatsApp send failed', ['phone' => $phone, 'error' => $e->getMessage()]);
        }
    }
}
