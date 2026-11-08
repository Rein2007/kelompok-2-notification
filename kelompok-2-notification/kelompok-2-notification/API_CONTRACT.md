# API Contract: Notification Service (Kelompok 2)

- Port: **8106** (akses normal lewat API Gateway: `http://127.0.0.1:8101/api/notifications`)
- Format: JSON, mengikuti kontrak bersama (bagian 8 modul)
- Header: `Content-Type: application/json`, `Authorization: Bearer <token>`, `X-Request-ID: <id>` (opsional; dibuatkan otomatis bila kosong)
- Semua endpoint kecuali `/health` **wajib token**. Tanpa token / token salah → `401`.

## Model data Notification

| Field | Tipe | Keterangan |
|---|---|---|
| id | string | `notif-001`, dibuat otomatis |
| user_id | string | **wajib**, penerima notifikasi |
| message | string | **wajib**, maks 500 karakter |
| type | string | opsional, default `info` (contoh: `submission`) |
| course_id | string/null | opsional |
| assignment_id | string/null | opsional |
| submission_id | string/null | opsional, diisi pemicu dari Submission |
| is_read | boolean | default `false` |
| created_at | string | waktu UTC ISO-8601 |
| read_at | string/null | waktu ditandai dibaca |

## 1. GET /health
Tanpa token. **200**
```json
{ "success": true, "data": { "service": "notification", "status": "healthy" }, "meta": { "request_id": "req-001" } }
```

## 2. GET /api/notifications?user_id={id}[&is_read=true|false]
Mengambil notifikasi milik satu pengguna (terbaru di atas).

**200**
```json
{
  "success": true,
  "data": {
    "notifications": [
      { "id": "notif-001", "user_id": "user-001", "message": "Submission berhasil diterima",
        "type": "submission", "course_id": null, "assignment_id": null, "submission_id": "sub-001",
        "is_read": false, "created_at": "2026-11-08T11:39:04+00:00", "read_at": null }
    ],
    "total": 1
  },
  "meta": { "request_id": "req-001" }
}
```
Error: `400 validation_error` (user_id kosong / is_read salah), `401 unauthorized`.

## 3. POST /api/notifications
Dipanggil Submission/Gateway setelah submission berhasil.

Request:
```json
{ "user_id": "user-001", "message": "Submission berhasil diterima", "type": "submission", "submission_id": "sub-001" }
```
**201** → objek notifikasi pada `data` (format seperti di atas).

Error: `400 invalid_json`, `400 validation_error`, `401 unauthorized`, `415 unsupported_media_type`.

## 4. PATCH /api/notifications/{id}/read
Menandai notifikasi sudah dibaca (idempoten). Tanpa body.

**200** → objek notifikasi dengan `is_read: true` dan `read_at` terisi.

Error: `401 unauthorized`, `404 notification_not_found`.

## Error format
```json
{ "success": false, "error": { "code": "notification_not_found", "message": "Notifikasi tidak ditemukan" }, "meta": { "request_id": "req-001" } }
```

## Kegagalan dependency (Authentication)
| Kondisi | Status | code |
|---|---|---|
| Auth tidak bisa dihubungi | 503 | auth_unavailable |
| Auth melewati timeout | 504 | auth_timeout |
| Auth membalas rusak | 502 | auth_bad_response |

## Kesepakatan integrasi (konfirmasi dengan kelompok lain)
- **Kelompok 1 (Auth):** token divalidasi lewat `GET /api/auth/me` (default). Jika memakai `POST /api/auth/validate`, ubah `AUTH_VALIDATE_METHOD` dan `AUTH_VALIDATE_PATH` di `.env`.
- **Kelompok 4 (Submission) / 5 (Gateway):** setelah submission sukses, panggil `POST /api/notifications` dengan `user_id`, `message`, `type: "submission"`, `submission_id`.
- **Ringkasan course:** field notifikasi yang dipakai `id` dan `message` (sesuai contoh bagian 11 modul).
