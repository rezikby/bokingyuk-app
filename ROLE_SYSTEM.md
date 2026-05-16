# Role & Authorization System

Dokumentasi sistem role, authorization, dan isolasi data admin pada BookingYuk Backend API.

---

# Overview

Sistem menggunakan Role-Based Access Control (RBAC) dengan 3 role utama:

| Role | Description |
|---|---|
| Customer | User biasa yang melakukan booking |
| Admin | Mengelola field/lapangan miliknya sendiri |
| Super Admin | Memiliki akses penuh ke seluruh sistem |

Authentication menggunakan:

- Laravel Sanctum
- Bearer Token
- Middleware Authorization

---

# Data Isolation Principle

## Admin Data Isolation

Setiap field/lapangan memiliki pemilik:

```txt
admin_id
```

Seluruh query admin wajib difilter menggunakan:

```php
admin_id = auth()->id()
```

Tujuannya:
- admin tidak dapat melihat data admin lain
- seluruh resource terisolasi
- meningkatkan keamanan multi-tenant system

---

# Admin Scoped Resources

| Resource | Isolation Rule |
|---|---|
| Fields | `WHERE admin_id = current_admin` |
| Bookings | `WHERE field.admin_id = current_admin` |
| Reports | `WHERE field.admin_id = current_admin` |
| Maintenance | `WHERE field.admin_id = current_admin` |
| Ratings | `WHERE field.admin_id = current_admin` |
| Payment History | `WHERE field.admin_id = current_admin` |
| Export Reports | Semua join wajib filter `admin_id` |

---

# Super Admin Access

Super Admin memiliki akses global tanpa filter `admin_id`.

Super Admin dapat:
- melihat seluruh data
- mengelola semua admin
- mengakses audit logs
- mengelola site settings
- monitoring sistem secara keseluruhan

---

# Authorization Flow

```txt
Request
   │
   ▼
Sanctum Authentication
   │
   ▼
Role Middleware
   │
   ├── Customer
   ├── Admin
   └── Super Admin
   │
   ▼
Ownership Validation
   │
   ▼
Access Granted / 403 Forbidden
```

---

# API Access Levels

## Public Routes

Tidak membutuhkan authentication.

```http
POST /api/auth/register
POST /api/auth/login
GET  /api/auth/google
POST /api/auth/google/callback
```

---

# Customer Routes

Membutuhkan:

```txt
auth:sanctum
```

Customer hanya dapat mengakses data miliknya sendiri.

```http
GET    /api/v1/fields
GET    /api/v1/fields/{id}

GET    /api/v1/bookings
POST   /api/v1/bookings
GET    /api/v1/bookings/{code}
PATCH  /api/v1/bookings/{code}/cancel

GET    /api/v1/payment-history
GET    /api/v1/notifications
```

---

# Admin Routes

Membutuhkan:

```txt
auth:sanctum + admin middleware
```

Admin hanya dapat mengakses resource miliknya sendiri.

```http
GET    /api/v1/admin/dashboard

GET    /api/v1/admin/fields
POST   /api/v1/admin/fields
PUT    /api/v1/admin/fields/{id}
DELETE /api/v1/admin/fields/{id}

GET    /api/v1/admin/bookings

GET    /api/v1/admin/reports/revenue
GET    /api/v1/admin/reports/predict-busy-hours/{fieldId}
```

---

# Super Admin Routes

Membutuhkan:

```txt
auth:sanctum + super_admin middleware
```

Super Admin memiliki akses penuh ke seluruh sistem.

```http
GET    /api/v1/super-admin/dashboard

GET    /api/v1/super-admin/users
POST   /api/v1/super-admin/users
PUT    /api/v1/super-admin/users/{id}
DELETE /api/v1/super-admin/users/{id}

GET    /api/v1/super-admin/audit-logs
GET    /api/v1/super-admin/settings
```

---

# Middleware

| Middleware | File | Description |
|---|---|---|
| `auth:sanctum` | Laravel Built-in | Verifikasi Sanctum token |
| `admin` | `EnsureAdmin.php` | Mengizinkan role admin & super_admin |
| `super_admin` | `EnsureSuperAdmin.php` | Hanya super_admin |
| `AuditRequest` | `AuditRequest.php` | Audit seluruh mutasi data admin |

---

# Ownership Validation

Setiap endpoint admin wajib melakukan ownership validation.

Contoh:

```php
abort_if($field->admin_id !== auth()->id(), 403);
```

Jika resource bukan milik admin tersebut:

```json
{
  "success": false,
  "message": "Unauthorized access."
}
```

HTTP Status:

```txt
403 Forbidden
```

---

# Security Features

## Authentication

- Laravel Sanctum
- Bearer Token Authentication

---

## Authorization

- Role-based middleware
- Ownership validation
- Protected admin routes

---

## Password Security

Password di-hash menggunakan:

```txt
bcrypt
```

(Laravel default hashing)

---

## Request Validation

Semua request menggunakan:

```txt
Form Request Validation
```

untuk:
- sanitasi input
- validasi data
- mencegah invalid payload

---

## Audit Logging

Semua mutasi data admin/super-admin dicatat:

- POST
- PUT
- PATCH
- DELETE

Audit log mencatat:
- user
- endpoint
- method
- payload
- timestamp

---

## Soft Delete

Field/lapangan menggunakan:

```txt
Soft Deletes
```

untuk mencegah kehilangan data permanen.

---

## Rate Limiting

API mendukung rate limiting untuk:
- login
- auth endpoints
- public API

Contoh:

```php
RateLimiter::for('api', function () {
    ...
});
```

---

# Installation

## Install Dependencies

```bash
composer install
```

---

## Configure Environment

```bash
cp .env.example .env
php artisan key:generate
```

---

## Run Migration

```bash
php artisan migrate
```

---

## Seed Database

```bash
php artisan db:seed
```

---

## Create Storage Link

```bash
php artisan storage:link
```

---

## Run Server

```bash
php artisan serve
```

---

# Recommended Production Setup

## Environment

```env
APP_ENV=production
APP_DEBUG=false
```

---

## Queue

Gunakan Redis untuk production:

```env
QUEUE_CONNECTION=redis
```

---

## Cache

```env
CACHE_STORE=redis
```

---

## HTTPS

Gunakan HTTPS untuk:
- Sanctum token
- Google OAuth
- Midtrans callback

---

# Production Security Checklist

- [ ] APP_DEBUG=false
- [ ] HTTPS enabled
- [ ] Ownership validation active
- [ ] Admin scope filtering active
- [ ] Rate limiting enabled
- [ ] Audit logging enabled
- [ ] Queue worker running
- [ ] Scheduler running
- [ ] Secure environment variables
- [ ] Database backup configured