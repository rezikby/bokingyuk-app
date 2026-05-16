<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'field_id'     => ['required', 'integer', 'exists:fields,id'],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time'   => ['required', 'date_format:H:i'],
            'end_time'     => ['required', 'date_format:H:i', 'after:start_time'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'field_id.exists'          => 'Lapangan tidak ditemukan.',
            'booking_date.after_or_equal' => 'Tanggal booking tidak boleh di masa lalu.',
            'end_time.after'           => 'Waktu selesai harus setelah waktu mulai.',
        ];
    }
}
