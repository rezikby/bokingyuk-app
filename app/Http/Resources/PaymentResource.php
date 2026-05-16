<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'amount'      => $this->amount,
            'formatted'   => 'Rp ' . number_format($this->amount, 0, ',', '.'),
            'method'      => $this->method,
            'status'      => $this->status->value,
            'status_label'=> $this->status->label(),
            'snap_token'  => $this->snap_token,
            'payment_url' => $this->payment_url,
            'paid_at'     => $this->paid_at?->toIso8601String(),
            'expired_at'  => $this->expired_at?->toIso8601String(),
        ];
    }
}
