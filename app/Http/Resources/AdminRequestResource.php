<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'user_id'        => $this->user_id,
            'full_name'      => $this->full_name,
            'email'          => $this->email,
            'phone'          => $this->phone,
            'address'        => $this->address,
            'ktp_image_url'  => $this->ktp_image
                                    ? asset('storage/' . $this->ktp_image)
                                    : null,
            'selfie_image_url' => $this->selfie_image
                                    ? asset('storage/' . $this->selfie_image)
                                    : null,
            'reason'         => $this->reason,
            'status'         => $this->status,
            'rejection_note' => $this->rejection_note,
            'reviewed_at'    => $this->reviewed_at?->toIso8601String(),
            'user'           => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'reviewer'       => $this->whenLoaded('reviewer', fn () => $this->reviewer
                                    ? new UserResource($this->reviewer)
                                    : null),
            'created_at'     => $this->created_at->toIso8601String(),
        ];
    }
}
