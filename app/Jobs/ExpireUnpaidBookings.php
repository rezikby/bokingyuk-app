<?php

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Expire bookings whose payment window has lapsed.
 * Dispatched by the scheduler every 5 minutes.
 */
class ExpireUnpaidBookings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function handle(): void
    {
        $expired = Booking::where('status', BookingStatus::Pending->value)
            ->whereHas('payment', function ($q) {
                $q->where('expired_at', '<=', now())
                  ->where('status', PaymentStatus::Unpaid->value);
            })
            ->with('payment')
            ->get();

        foreach ($expired as $booking) {
            $booking->update([
                'status'         => BookingStatus::Cancelled->value,
                'payment_status' => PaymentStatus::Expired->value,
            ]);

            $booking->payment?->update(['status' => PaymentStatus::Expired->value]);

            Log::info('Booking expired', ['booking_code' => $booking->booking_code]);
        }

        Log::info("ExpireUnpaidBookings: {$expired->count()} booking(s) expired.");
    }
}
