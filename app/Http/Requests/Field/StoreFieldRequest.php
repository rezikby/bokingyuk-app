<?php

namespace App\Http\Requests\Field;

use App\Enums\FieldType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFieldRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:100'],
            'type'           => ['required', Rule::enum(FieldType::class)],
            'description'    => ['nullable', 'string', 'max:1000'],
            'price_per_hour' => ['required', 'integer', 'min:1000'],
            'is_active'      => ['boolean'],
            'image'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'facilities'     => ['nullable', 'array'],
            'facilities.*'   => ['string', 'max:50'],
            // Location
            'address'        => ['nullable', 'string', 'max:255'],
            'maps_url'       => ['nullable', 'string', 'max:1000'],
            'latitude'       => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'      => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
