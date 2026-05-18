<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\BookingConflictException;
use App\Exceptions\BookingNotFoundException;
use App\Exceptions\FieldNotFoundException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Booking;
use App\Models\User;
use App\Repositories\Interfaces\BookingRepositoryInterface;
use App\Repositories\Interfaces\FieldRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingService
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepo,
        private readonly FieldRepositoryInterface $fieldRepo,
        private readonly PaymentService $paymentService,
        private readonly WhatsAppService $whatsAppService,
        private readonly MaintenanceService $maintenanceService,
        private readonly NotificationService $notificationService,
    ) {}

    public function createBooking(User $user, array $data): Booking
    {
        $field = $this->fieldRepo->findByIdOrFail($data['field_id']);

        $duration = $this->calculateDuration($data['start_time'], $data['end_time']);

        $this->ensureNoConflict(
            $data['field_id'],
            $data['booking_date'],
            $data['start_time'],
            $data['end_time'],
        );

        $this->ensureNoMaintenance(
            $data['field_id'],
            $data['booking_date'],
            $data['start_time'],
            $data['end_time'],
        );

        $totalPrice = $field->price_per_hour * $duration;

        return DB::transaction(function () use ($user, $data, $duration, $totalPrice, $field) {
            $booking = $this->bookingRepo->create([
                'user_id'        => $user->id,
                'field_id'       => $data['field_id'],
                'booking_date'   => $data['booking_date'],
                'start_time'     => $data['start_time'],
                'end_time'       => $data['end_time'],
                'duration_hours' => $duration,
                'total_price'    => $totalPrice,
                'status'         => BookingStatus::Pending->value,
                'payment_status' => PaymentStatus::Unpaid->value,
                'notes'          => $data['notes'] ?? null,
            ]);

            $this->paymentService->initiate($booking);

            // Notifikasi ke USER: booking berhasil dibuat
            $this->notificationService->notifyBookingCreated($booking);

            // Notifikasi ke ADMIN lapangan: ada booking baru masuk
            if ($field->admin_id) {
                $this->notificationService->send(
                    $field->admin_id,
                    'booking',
                    '📋 Booking Baru Masuk!',
                    "Booking {$booking->booking_code} dari {$user->name} untuk lapangan {$field->name} pada " .
                        Carbon::parse($data['booking_date'])->format('d M Y') .
                        " pukul {$data['start_time']}–{$data['end_time']}. Menunggu pembayaran.",
                    [
                        'booking_code' => $booking->booking_code,
                        'booking_id'   => $booking->id,
                        'user_name'    => $user->name,
                        'field_name'   => $field->name,
                    ]
                );
            }

            Log::info('Booking created', [
                'booking_code' => $booking->booking_code,
                'user_id'      => $user->id,
            ]);

            return $booking->load(['field', 'payment']);
        });
    }

    public function checkIn(string $qrToken): Booking
    {
        $booking = $this->bookingRepo->findByQrToken($qrToken);

        if (! $booking) {
            throw new BookingNotFoundException('QR token tidak valid.');
        }

        if (! $booking->canCheckIn()) {
            throw new InvalidStatusTransitionException('Booking tidak dapat di-check-in saat ini.');
        }

        $booking = $this->bookingRepo->update($booking, [
            'status'        => BookingStatus::CheckedIn->value,
            'checked_in_at' => now(),
        ]);

        $this->whatsAppService->sendCheckInConfirmation($booking);
        $this->notificationService->notifyCheckIn($booking);

        return $booking->load(['user', 'field']);
    }

    public function cancelBooking(Booking $booking): Booking
    {
        if (! $booking->status->canTransitionTo(BookingStatus::Cancelled)) {
            throw new InvalidStatusTransitionException(
                "Booking berstatus '{$booking->status->label()}' tidak dapat dibatalkan."
            );
        }

        return DB::transaction(function () use ($booking) {
            $updated = $this->bookingRepo->update($booking, [
                'status' => BookingStatus::Cancelled->value,
            ]);

            if ($booking->payment && $booking->payment->isPaid()) {
                $this->paymentService->refund($booking->payment);
            }

            $this->whatsAppService->sendCancellationNotice($updated);
            $this->notificationService->notifyBookingCancelled($updated);

            return $updated;
        });
    }

    public function getAvailableSlots(int $fieldId, string $date): array
    {
        $bookedSlots = $this->bookingRepo->getBookedSlotsByFieldAndDate($fieldId, $date);
        $allSlots    = $this->generateTimeSlots('08:00', '22:00');

        return collect($allSlots)->map(function (array $slot) use ($bookedSlots) {
            $isBooked = $bookedSlots->contains(function ($b) use ($slot) {
                return $slot['start'] < $this->normalizeTime($b->end_time)
                    && $slot['end']   > $this->normalizeTime($b->start_time);
            });

            return array_merge($slot, ['is_available' => ! $isBooked]);
        })->values()->all();
    }

    // ─── Private Helpers ───────────────────────────────────────────────────────

    private function ensureNoMaintenance(int $fieldId, string $date, string $start, string $end): void
    {
        $blocked = $this->maintenanceService->isBlockedByMaintenance(
            $fieldId,
            $date,
            $this->normalizeTime($start),
            $this->normalizeTime($end),
        );

        if ($blocked) {
            throw new \InvalidArgumentException(
                'Lapangan sedang dalam jadwal maintenance pada waktu tersebut. Silakan pilih waktu lain.'
            );
        }
    }

    private function normalizeTime(string $time): string
    {
        if (str_contains($time, ' ')) {
            $time = explode(' ', $time)[1];
        }

        return substr($time, 0, 5);
    }

    private function ensureNoConflict(int $fieldId, string $date, string $start, string $end): void
    {
        $hasConflict = $this->bookingRepo->isSlotBooked(
            $fieldId,
            $date,
            $this->normalizeTime($start),
            $this->normalizeTime($end),
        );

        if ($hasConflict) {
            throw new BookingConflictException(
                'Slot waktu sudah dipesan. Silakan pilih waktu lain.'
            );
        }
    }

    private function calculateDuration(string $start, string $end): int
    {
        $duration = (int) Carbon::parse($start)->diffInHours(Carbon::parse($end));

        if ($duration < 1) {
            throw new \InvalidArgumentException(
                'Durasi booking minimal 1 jam. Pastikan jam selesai lebih besar dari jam mulai.'
            );
        }

        return $duration;
    }

    private function generateTimeSlots(string $open, string $close): array
    {
        $slots   = [];
        $current = Carbon::parse($open);
        $limit   = Carbon::parse($close);

        while ($current->copy()->addHour()->lte($limit)) {
            $slots[] = [
                'start' => $current->format('H:i'),
                'end'   => $current->copy()->addHour()->format('H:i'),
            ];
            $current->addHour();
        }

        return $slots;
    }
}