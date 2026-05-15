# BookingYuk Backend 🏟️

Backend API untuk aplikasi booking lapangan olahraga berbasis Laravel 12.

---

## Project Status

🚧 Under Development

---

## Tech Stack

| Layer | Technology |
|-------|------------|
| Framework | Laravel 12 |
| Authentication | Laravel Sanctum (Bearer Token) |
| Database | MySQL |
| Queue | Database / Redis |
| Cache | Redis / Database |
| Payment Gateway | Midtrans Snap |
| WhatsApp Gateway | Fonnte API |
| Frontend | React + Vite |

---

## Requirements

- PHP 8.3+
- Composer 2+
- MySQL 8+
- Node.js 20+
- Redis (optional)

---

## Architecture

```txt
app/
├── Enums/                  # BookingStatus, PaymentStatus, FieldType
├── Exceptions/             # Custom domain exceptions
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── Auth/       # Authentication
│   │       ├── Admin/      # Admin Controllers
│   │       ├── Customer/   # Customer Controllers
│   │       └── SuperAdmin/ # Super Admin Controllers
│   ├── Middleware/         # EnsureAdmin, EnsureSuperAdmin
│   ├── Requests/           # Form Request Validation
│   └── Resources/          # API Resources
├── Jobs/                   # Queue Jobs
├── Models/                 # Eloquent Models
├── Repositories/
│   ├── Interfaces/         # Repository Contracts
│   └── Eloquent/           # Repository Implementations
├── Services/               # Business Logic Layer
│   ├── AuthService
│   ├── BookingService
│   ├── FieldService
│   ├── PaymentService
│   ├── ReportService
│   └── WhatsAppService
└── Traits/
    └── ApiResponse.php
```

---

## Principles

Project ini menerapkan prinsip:

- SOLID
- DRY
- KISS
- YAGNI
- Repository Pattern
- Service Layer
- Clean Code

---

## Installation

### 1. Clone Repository

```bash
git clone https://github.com/username/bookingyuk-backend.git
cd bookingyuk-backend
```

### 2. Install Dependency

```bash
composer install
```

### 3. Setup Environment

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configure Environment

```env
APP_NAME="BookingYuk"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bookingyuk
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
CACHE_STORE=database

MIDTRANS_SERVER_KEY=
MIDTRANS_CLIENT_KEY=
MIDTRANS_IS_PRODUCTION=false

FONNTE_TOKEN=

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
```

---

## Database Setup

### Run Migration & Seeder

```bash
php artisan migrate --seed
```

---

## Storage Link

```bash
php artisan storage:link
```

---

## Run Application

### Laravel Server

```bash
php artisan serve
```

### Queue Worker

```bash
php artisan queue:work --tries=3
```

### Scheduler

```bash
php artisan schedule:work
```

---

## Scheduler Cron (Production)

Tambahkan ke crontab server:

```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

---

## Default Accounts

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@example.com | password |
| Customer | customer@example.com | password |

---

## Authentication

Menggunakan Laravel Sanctum dengan Bearer Token.

### Authorization Header

```http
Authorization: Bearer your_token
```

---

# API Endpoints

## Auth (Public)

```http
POST   /api/auth/register
POST   /api/auth/login
POST   /api/auth/google/callback
GET    /api/auth/google

POST   /api/auth/logout         [sanctum]
GET    /api/auth/me             [sanctum]
```

---

## Customer Routes

```http
GET    /api/v1/fields
GET    /api/v1/fields/{id}

GET    /api/v1/bookings
POST   /api/v1/bookings
GET    /api/v1/bookings/available-slots
GET    /api/v1/bookings/{code}
PATCH  /api/v1/bookings/{code}/cancel

GET    /api/v1/payment-history
GET    /api/v1/payment-history/booking/{bookingCode}

GET    /api/v1/notifications
PATCH  /api/v1/notifications/read-all
```

---

## Admin Routes

```http
GET    /api/v1/admin/dashboard

GET    /api/v1/admin/fields
POST   /api/v1/admin/fields
GET    /api/v1/admin/fields/{id}
PUT    /api/v1/admin/fields/{id}
DELETE /api/v1/admin/fields/{id}

GET    /api/v1/admin/bookings
GET    /api/v1/admin/bookings/{code}
POST   /api/v1/admin/bookings/check-in
PATCH  /api/v1/admin/bookings/{code}/cancel

GET    /api/v1/admin/reports/revenue
GET    /api/v1/admin/reports/predict-busy-hours/{fieldId}
```

---

## Super Admin Routes

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

## Webhook

```http
POST /api/webhook/payment
```

Midtrans notification URL dengan signature verification.

---

# Main Features

- ✅ Booking realtime
- ✅ Conflict booking detection
- ✅ QR Check-in system
- ✅ Midtrans payment gateway
- ✅ Revenue reporting
- ✅ AI busy-hour prediction
- ✅ WhatsApp notification
- ✅ Auto expired booking
- ✅ Admin dashboard API
- ✅ Role-based authorization
- ✅ Google OAuth login

---

# Queue & Background Jobs

Digunakan untuk:

- Auto expire unpaid booking
- WhatsApp notifications
- Future scheduled tasks

Default menggunakan:

```env
QUEUE_CONNECTION=database
```

Dapat diganti ke Redis untuk performa lebih baik.

---

# Response Format

## Success Response

```json
{
  "success": true,
  "message": "Booking berhasil dibuat.",
  "data": {}
}
```

---

## Error Response

```json
{
  "success": false,
  "message": "Data yang dikirim tidak valid.",
  "errors": {
    "field_id": [
      "Lapangan tidak ditemukan."
    ]
  }
}
```

---

# Security

Project ini menggunakan:

- Laravel Sanctum Authentication
- Role-based Middleware
- Form Request Validation
- Protected Admin Routes
- Midtrans Signature Verification
- CSRF Protection
- Secure Password Hashing

---

# Development Commands

## Show Route List

```bash
php artisan route:list
```

## Clear Cache

```bash
php artisan optimize:clear
```

## Run Test

```bash
php artisan test
```

---

# License

MIT License# BokingYuk_Backend
