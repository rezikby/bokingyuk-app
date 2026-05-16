<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'email'           => $this->email,
            'phone'           => $this->phone,
            'whatsapp_number' => $this->whatsapp_number,
            'role'            => $this->role,
            'is_active'       => $this->is_active,
            'avatar_url'      => $this->avatar
                                    ? asset('storage/' . $this->avatar)
                                    : null,
            'address'         => $this->address,
            'created_at'      => $this->created_at->toIso8601String(),
        ];
    }
}
