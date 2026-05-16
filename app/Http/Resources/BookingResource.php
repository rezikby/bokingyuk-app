<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'booking_code'    => $this->booking_code,
            'booking_date'    => $this->booking_date->format('Y-m-d'),
            'start_time'      => $this->start_time,
            'end_time'        => $this->end_time,
            'duration_hours'  => $this->duration_hours,
            'total_price'     => $this->total_price,
            'total_formatted' => 'Rp ' . number_format($this->total_price, 0, ',', '.'),
            'status'          => $this->status->value,
            'status_label'    => $this->status->label(),
            'payment_status'  => $this->payment_status->value,
            // QR token: tersedia saat confirmed/checked_in (untuk customer & mobile)
            'qr_token'        => $this->when(
                in_array($this->status->value, ['confirmed', 'checked_in']),
                $this->qr_token
            ),
            // Mobile check-in URL: digunakan sebagai value QR code
            'qr_checkin_url'  => $this->when(
                in_array($this->status->value, ['confirmed', 'checked_in']),
                fn() => url("/api/mobile/checkin/{$this->qr_token}")
            ),
            'checked_in_at'   => $this->checked_in_at?->toIso8601String(),
            'notes'           => $this->notes,
            'field'           => new FieldResource($this->whenLoaded('field')),
            'user'            => new UserResource($this->whenLoaded('user')),
            'payment'         => new PaymentResource($this->whenLoaded('payment')),
            'created_at'      => $this->created_at->toIso8601String(),
        ];
    }
}
