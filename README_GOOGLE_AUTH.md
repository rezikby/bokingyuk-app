# Google OAuth Integration Guide

Integrasi Google Authentication menggunakan:

- Laravel Socialite
- Laravel Sanctum
- React / SPA / Mobile App
- Google Identity Services

---

# Overview

Sistem authentication mendukung:

- Login manual (email & password)
- Register manual
- Login menggunakan Google OAuth
- Auto register akun Google baru
- Auto link akun berdasarkan email

Authentication API menggunakan:

- Laravel Sanctum
- Bearer Token

---

# Architecture Flow

```txt
Frontend (React / Mobile)
        │
        │ Login with Google
        ▼
Google OAuth
        │
        │ access_token
        ▼
Laravel API
        │
        │ verify token
        ▼
Google User Data
        │
        ├── User exists?
        │      ├── YES → login
        │      └── NO  → register
        │
        ▼
Sanctum Token
        │
        ▼
Frontend Authenticated
```

---

# Requirements

| Requirement | Version |
|---|---|
| PHP | 8.3+ |
| Laravel | 12 |
| Composer | 2+ |
| Sanctum | Latest |
| Socialite | Latest |

---

# Install Dependencies

## Install Socialite

```bash
composer require laravel/socialite
```

---

# Database Preparation

## Run Migration

```bash
php artisan migrate
```

---

# Environment Configuration

Tambahkan konfigurasi berikut ke file `.env`:

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
```

---

# Configure services.php

File:

```txt
config/services.php
```

Tambahkan:

```php
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => env('GOOGLE_REDIRECT_URI'),
],
```

---

# Configure Google Cloud Console

## Step 1 — Open Google Console

https://console.cloud.google.com/apis/credentials

---

## Step 2 — Create Project

Buat project baru atau gunakan project yang sudah ada.

---

## Step 3 — Enable APIs

Aktifkan:

- Google Identity Services
- Google OAuth API

---

## Step 4 — Create OAuth Client

Pilih:

```txt
OAuth 2.0 Client ID
```

Application Type:

```txt
Web Application
```

---

## Step 5 — Add Authorized Redirect URI

Development:

```txt
http://localhost:8000/api/auth/google/callback
```

Production:

```txt
https://api.bookingyuk.com/api/auth/google/callback
```

---

# API Endpoints

## Public Routes

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/auth/google` | Get Google redirect URL |
| POST | `/api/auth/google/callback` | Login/register using Google token |

---

# Authentication Flow

## Option A — Token Based (Recommended)

Digunakan untuk:
- React SPA
- Flutter
- React Native
- Mobile Apps

---

## Flow Diagram

```txt
Frontend
   │
   ├── Google Login SDK
   │
   ▼
Google OAuth
   │
   ▼
access_token
   │
   ▼
POST /api/auth/google/callback
   │
   ▼
Laravel verifies token
   │
   ▼
Sanctum Token
```

---

# Frontend Request Example

## Request

```http
POST /api/auth/google/callback
Content-Type: application/json
```

Body:

```json
{
  "access_token": "ya29.a0AfH6SM..."
}
```

---

# Success Response

```json
{
  "success": true,
  "message": "Login via Google berhasil.",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "customer"
    },
    "token": "1|sanctum-token"
  }
}
```

---

# Error Response

## Invalid Token

```json
{
  "success": false,
  "message": "Google token tidak valid."
}
```

---

## Missing Token

```json
{
  "message": "The access token field is required.",
  "errors": {
    "access_token": [
      "The access token field is required."
    ]
  }
}
```

---

# Backend Logic

## Authentication Process

Backend akan:

1. Verifikasi Google token
2. Ambil data user dari Google
3. Cek email user
4. Jika user belum ada → register otomatis
5. Jika user sudah ada → login
6. Generate Sanctum token
7. Return authenticated user

---

# Auto Register Rules

| Condition | Action |
|---|---|
| Email belum ada | Register user baru |
| Email sudah ada | Login existing user |
| User baru | Default role = customer |
| Password akun Google | nullable |

---

# Important Notes

## Manual Login Tetap Berjalan

Google login tidak mempengaruhi:
- login manual
- register manual
- reset password

---

## Password Google User

User yang dibuat melalui Google:
- password dapat bernilai `null`
- tidak bisa login manual
- harus set password terlebih dahulu jika ingin login manual

---

# Security Best Practices

## Backend

- Jangan expose `GOOGLE_CLIENT_SECRET`
- Selalu validasi request
- Gunakan HTTPS di production
- Simpan Sanctum token dengan aman
- Gunakan middleware auth

---

## Frontend

JANGAN simpan:

```env
GOOGLE_CLIENT_SECRET=
```

di frontend React/mobile.

---

# Production Setup

## Update Environment

```env
APP_ENV=production
APP_DEBUG=false
```

---

## Update Redirect URI

```env
GOOGLE_REDIRECT_URI=https://api.bookingyuk.com/api/auth/google/callback
```

---

# Testing

## Test Successful Login

- Login Google berhasil
- User baru otomatis dibuat
- Sanctum token dikembalikan

---

## Test Existing User

- Email sama
- Tidak duplicate account
- User langsung login

---

## Test Invalid Token

- Token palsu
- Token expired
- Token kosong

---

# Troubleshooting

## Error: redirect_uri_mismatch

Penyebab:
- redirect URI tidak sama dengan Google Console

Solusi:
- samakan URI di `.env`
- samakan URI di Google Cloud Console

---

## Error: invalid_client

Penyebab:
- client id / secret salah

Solusi:
- cek `.env`
- clear config cache

```bash
php artisan optimize:clear
```

---

## Error: Unauthenticated

Penyebab:
- Sanctum token tidak dikirim

Solusi:

```http
Authorization: Bearer your_token
```

---

# Production Checklist

- [ ] HTTPS aktif
- [ ] APP_DEBUG=false
- [ ] Redirect URI production valid
- [ ] Sanctum configured
- [ ] CORS configured
- [ ] Google credentials production valid
- [ ] Token storage secure
- [ ] API rate limit aktif

---

# Example Production URL

```txt
https://api.bookingyuk.com/api/auth/google/callback
```