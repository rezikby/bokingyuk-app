<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:100'],
            'email'            => ['required', 'email', 'unique:users,email', 'regex:/^[^@]+@gmail\.com$/'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
            'phone'            => ['nullable', 'string', 'max:20'],
            'whatsapp_number'  => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'      => 'Nama wajib diisi.',
            'email.required'     => 'Email wajib diisi.',
            'email.unique'       => 'Email sudah terdaftar.',
            'email.regex'        => 'Hanya akun Gmail (@gmail.com) yang diizinkan.',
            'password.min'       => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }
}