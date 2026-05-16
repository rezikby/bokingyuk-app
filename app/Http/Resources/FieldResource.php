<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'type'           => $this->type->value,
            'type_label'     => $this->type->label(),
            'description'    => $this->description,
            'price_per_hour' => $this->price_per_hour,
            'price_formatted'=> 'Rp ' . number_format($this->price_per_hour, 0, ',', '.'),
            'is_active'      => $this->is_active,
            'image_url'      => $this->image ? asset('storage/' . $this->image) : null,
            'facilities'     => $this->facilities ?? [],
            // Location
            'address'        => $this->address,
            'maps_url'       => $this->maps_url,
            'latitude'       => $this->latitude,
            'longitude'      => $this->longitude,
            'created_at'     => $this->created_at->toIso8601String(),
        ];
    }
}
