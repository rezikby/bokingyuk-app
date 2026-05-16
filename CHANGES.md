# Perubahan Backend - Fitur Role & User Management

## File BARU yang Ditambahkan

### Migrations
- `database/migrations/2026_05_11_000001_add_super_admin_role_and_profile_to_users.php`
  - Menambahkan kolom `avatar` dan `address` ke tabel `users`
- `database/migrations/2026_05_11_000002_create_admin_requests_table.php`
  - Membuat tabel `admin_requests` untuk pengajuan menjadi admin

### Models
- `app/Models/AdminRequest.php` — Model untuk tabel admin_requests

### Middleware
- `app/Http/Middleware/EnsureSuperAdmin.php` — Middleware khusus super admin

### Controllers
- `app/Http/Controllers/Api/SuperAdmin/DashboardController.php`
  - GET `/api/v1/super-admin/dashboard` — Statistik dashboard
- `app/Http/Controllers/Api/SuperAdmin/UserManagementController.php`
  - GET    `/api/v1/super-admin/users` — List user (search, filter role, sort, pagination)
  - POST   `/api/v1/super-admin/users` — Buat user baru
  - GET    `/api/v1/super-admin/users/{id}` — Detail user
  - PUT    `/api/v1/super-admin/users/{id}` — Update user
  - DELETE `/api/v1/super-admin/users/{id}` — Hapus user
  - PATCH  `/api/v1/super-admin/users/{id}/toggle-active` — Aktif/nonaktifkan
- `app/Http/Controllers/Api/SuperAdmin/AdminRequestController.php`
  - GET  `/api/v1/super-admin/admin-requests` — List semua pengajuan
  - GET  `/api/v1/super-admin/admin-requests/{id}` — Detail pengajuan
  - POST `/api/v1/super-admin/admin-requests/{id}/accept` — Terima (promote ke admin)
  - POST `/api/v1/super-admin/admin-requests/{id}/reject` — Tolak
- `app/Http/Controllers/Api/Customer/AdminRequestController.php`
  - GET  `/api/v1/admin-requests` — Riwayat pengajuan user sendiri
  - GET  `/api/v1/admin-requests/{id}` — Detail pengajuan
  - POST `/api/v1/admin-requests` — Kirim pengajuan baru (multipart/form-data)
- `app/Http/Controllers/Api/ProfileController.php`
  - PUT  `/api/v1/profile` — Update profil
  - POST `/api/v1/profile/avatar` — Upload avatar
  - PUT  `/api/v1/profile/change-password` — Ganti password

### Resources
- `app/Http/Resources/AdminRequestResource.php` — Resource untuk admin request

## File yang DIMODIFIKASI

### `app/Models/User.php`
- Tambah `avatar`, `address` ke `$fillable`
- Tambah method `isSuperAdmin()` dan `hasAdminAccess()`
- Tambah relasi `adminRequests()`

### `app/Http/Resources/UserResource.php`
- Tambah field `avatar_url` dan `address` ke response

### `app/Http/Middleware/EnsureAdmin.php`
- Perubahan minimal: sekarang cek `hasAdminAccess()` sehingga super_admin juga bisa akses route admin

### `bootstrap/app.php`
- Tambah alias middleware `super_admin` → `EnsureSuperAdmin`

### `routes/api.php`
- Tambah route group `/api/v1/super-admin/*` (middleware: super_admin)
- Tambah route group `/api/v1/admin-requests` (untuk user)
- Tambah route group `/api/v1/profile`

### `database/seeders/DatabaseSeeder.php`
- Tambah seed untuk super admin: `superadmin@bokingyuk.com` / `SuperAdmin@12345`

## Role yang Tersedia

| Role        | Keterangan                                          |
|-------------|-----------------------------------------------------|
| `customer`  | User biasa (default saat register)                  |
| `admin`     | Admin lapangan (CRUD field, manage booking)         |
| `super_admin` | Super admin (manage semua user, approve request)  |

## Cara Jalankan Migrasi

```bash
php artisan migrate
php artisan db:seed
```

## Storage Link (untuk foto KTP, selfie, avatar)

```bash
php artisan storage:link
```

## Upload Format (POST /api/v1/admin-requests)

```
Content-Type: multipart/form-data

full_name     : string (required)
email         : string email (required)
phone         : string (required)
address       : string (required)
ktp_image     : file image jpg/jpeg/png max 5MB (required)
selfie_image  : file image jpg/jpeg/png max 5MB (required)
reason        : string min 20 chars (required)
```
