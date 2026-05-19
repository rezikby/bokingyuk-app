<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    private string $serverKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->serverKey = config('services.midtrans.server_key', '');

        $this->baseUrl = config('services.midtrans.is_production', false)
            ? 'https://app.midtrans.com/snap/v1'
            : 'https://app.sandbox.midtrans.com/snap/v1';
    }

    /**
     * Initiate payment — create Midtrans Snap token.
     */
    public function initiate(Booking $booking): Payment
    {
        // FIX: buat order_id dengan format yang konsisten dan simpan ke DB
        $orderId = $booking->booking_code . '-' . time();

        // Jika server key kosong, langsung simpan tanpa hit Midtrans
        if (empty($this->serverKey)) {
            Log::warning('MIDTRANS_SERVER_KEY belum diset di .env', [
                'booking' => $booking->booking_code,
            ]);

            return Payment::updateOrCreate(
                ['booking_id' => $booking->id],
                [
                    'amount'             => $booking->total_price,
                    'status'             => PaymentStatus::Unpaid->value,
                    'snap_token'         => null,
                    'payment_url'        => null,
                    'midtrans_order_id'  => $orderId, // ← simpan meski tanpa hit Midtrans
                    'expired_at'         => now()->addHours(24),
                ]
            );
        }

        $payload = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => (int) $booking->total_price,
            ],
            'customer_details' => [
                'first_name' => $booking->user->name  ?? 'Customer',
                'email'      => $booking->user->email ?? 'customer@mail.com',
                'phone'      => $booking->user->phone ?? '08123456789',
            ],
        ];

        try {
            $response = Http::withBasicAuth($this->serverKey, '')
                ->acceptJson()
                ->timeout(15)
                ->post("{$this->baseUrl}/transactions", $payload);

            Log::info('MIDTRANS RESPONSE', [
                'status'  => $response->status(),
                'booking' => $booking->booking_code,
                'body'    => $response->body(),
            ]);

            $body = $response->json();

            // Jika Midtrans return error (4xx/5xx), log dan simpan tanpa token
            if (! $response->successful()) {
                Log::error('Midtrans rejected request', [
                    'booking'     => $booking->booking_code,
                    'http_status' => $response->status(),
                    'error'       => $body['error_messages'] ?? $body,
                ]);

                return Payment::updateOrCreate(
                    ['booking_id' => $booking->id],
                    [
                        'amount'             => $booking->total_price,
                        'status'             => PaymentStatus::Unpaid->value,
                        'snap_token'         => null,
                        'payment_url'        => null,
                        'midtrans_order_id'  => $orderId, // ← tetap simpan
                        'expired_at'         => now()->addHours(24),
                    ]
                );
            }

            return Payment::updateOrCreate(
                ['booking_id' => $booking->id],
                [
                    'amount'             => $booking->total_price,
                    'status'             => PaymentStatus::Unpaid->value,
                    'snap_token'         => $body['token'] ?? null,
                    'payment_url'        => $body['redirect_url'] ?? null,
                    'midtrans_order_id'  => $orderId, // ← simpan order_id yang terkirim ke Midtrans
                    'expired_at'         => now()->addHour(),
                ]
            );

        } catch (\Throwable $e) {
            Log::error('Midtrans initiate exception', [
                'booking' => $booking->booking_code,
                'error'   => $e->getMessage(),
            ]);

            return Payment::updateOrCreate(
                ['booking_id' => $booking->id],
                [
                    'amount'             => $booking->total_price,
                    'status'             => PaymentStatus::Unpaid->value,
                    'snap_token'         => null,
                    'payment_url'        => null,
                    'midtrans_order_id'  => $orderId, // ← tetap simpan
                    'expired_at'         => now()->addHours(24),
                ]
            );
        }
    }

    /**
     * Handle Midtrans webhook notification.
     */
    public function handleNotification(array $payload): void
    {
        $this->validateSignature($payload);

        // order_id format: "BKY-YYYYMMDD-XXXXXX-{timestamp}" — strip the timestamp suffix
        $bookingCode = preg_replace('/-\d+$/', '', $payload['order_id']);

        $payment = Payment::where('booking_id', function ($q) use ($bookingCode) {
            $q->from('bookings')
                ->select('id')
                ->where('booking_code', $bookingCode);
        })->firstOrFail();

        $status = $this->resolveStatus(
            $payload['transaction_status'],
            $payload['fraud_status'] ?? null
        );

        $payment->update([
            'status'                 => $status->value,
            'method'                 => $payload['payment_type'] ?? null,
            'gateway_transaction_id' => $payload['transaction_id'] ?? null,
            'gateway_response'       => $payload,
            'paid_at'                => $status === PaymentStatus::Paid ? now() : null,
        ]);

        if ($status === PaymentStatus::Paid) {
            $payment->booking->update([
                'payment_status' => PaymentStatus::Paid->value,
                'status'         => 'confirmed',
            ]);
        }
    }

    /**
     * Issue refund via Midtrans.
     */
    public function refund(Payment $payment): void
    {
        if (! $payment->gateway_transaction_id) {
            Log::warning('Refund skipped: gateway transaction id missing', [
                'payment_id' => $payment->id,
            ]);
            return;
        }

        try {
            Http::withBasicAuth($this->serverKey, '')
                ->acceptJson()
                ->timeout(15)
                ->post(
                    "https://api.midtrans.com/v2/{$payment->gateway_transaction_id}/refund",
                    ['reason' => 'Booking dibatalkan']
                );

            $payment->update(['status' => PaymentStatus::Refunded->value]);

        } catch (\Throwable $e) {
            Log::error('Midtrans refund error', [
                'payment_id' => $payment->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function validateSignature(array $payload): void
    {
        $signature = hash(
            'sha512',
            $payload['order_id'] .
                $payload['status_code'] .
                $payload['gross_amount'] .
                $this->serverKey
        );

        if ($signature !== $payload['signature_key']) {
            throw new \InvalidArgumentException('Invalid Midtrans signature.');
        }
    }

    public function resolveStatus(string $transactionStatus, ?string $fraudStatus): PaymentStatus
    {
        if ($transactionStatus === 'capture') {
            return $fraudStatus === 'accept' ? PaymentStatus::Paid : PaymentStatus::Unpaid;
        }

        return match ($transactionStatus) {
            'settlement'                          => PaymentStatus::Paid,
            'deny', 'cancel', 'failure', 'expire' => PaymentStatus::Expired,
            default                               => PaymentStatus::Unpaid,
        };
    }
}