# 📱 BokingYuk — Mobile API & QR Check-in

## Base URL

```
Production : https://bokingyuk.example.com/api
Development: http://localhost:8000/api
```

---

## 📌 Endpoint Mobile (Tanpa Login)

### 1. Check-in via QR Scan
```
GET /api/mobile/checkin/{qr_token}
```
- Dipanggil otomatis saat HP men-scan QR Code customer
- QR Code berisi URL lengkap ini
- Status booking berubah jadi `checked_in`

**Response sukses:**
```json
{
  "success": true,
  "message": "Check-in berhasil! Selamat menikmati fasilitas lapangan.",
  "data": {
    "booking_code": "BKY-20260510-ABC123",
    "status": "checked_in",
    "checked_in_at": "2026-05-10T09:00:00+07:00",
    "field": { "name": "Lapangan Futsal A" },
    "user":  { "name": "Budi Santoso" }
  }
}
```

**Response error:**
```json
{
  "success": false,
  "message": "Booking tidak dapat di-check-in saat ini."
}
```

---

### 2. Info Booking (Read-only)
```
GET /api/mobile/booking/{qr_token}
```
- Lihat detail booking tanpa melakukan check-in
- Cocok untuk verifikasi sebelum check-in

---

## 🔐 Endpoint Admin (Perlu Login)

### Konfirmasi Pembayaran Manual
```
PATCH /api/v1/admin/bookings/{booking_code}/confirm-payment
Authorization: Bearer {admin_token}
```
- Mengubah `status` → `confirmed`
- Mengubah `payment_status` → `paid` (Lunas)

### Check-in via Token (Admin Panel)
```
POST /api/v1/admin/bookings/check-in
Authorization: Bearer {admin_token}
Content-Type: application/json

{ "qr_token": "uuid-qr-token-here" }
```

---

## 📷 Upload Gambar Lapangan

```
POST /api/v1/admin/fields
Authorization: Bearer {admin_token}
Content-Type: multipart/form-data

name=Lapangan A
type=futsal
price_per_hour=100000
image=<file>
address=Jl. Sudirman No.1
maps_url=https://maps.google.com/...
latitude=-6.200000
longitude=106.816666
```

---

## 🔄 Auto-reload Frontend

- Data booking admin diperbarui otomatis setiap **15 detik**
- Juga reload saat browser window kembali aktif (focus)
- Tidak perlu refresh manual

