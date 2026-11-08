# Hasil Pengujian: Notification Service

Diuji pada 8 November 2026 (PHP 8.3, `php -S 127.0.0.1:8106 index.php`, Auth disimulasikan dengan service tiruan).

| # | Kasus | Hasil diharapkan | Hasil |
|---|---|---|---|
| 1 | GET /health | 200 | Lulus |
| 2 | GET daftar tanpa token | 401 | Lulus |
| 3 | GET daftar dengan token salah | 401 | Lulus |
| 4 | POST notifikasi valid | 201 | Lulus |
| 5 | POST tanpa `message` | 400 | Lulus |
| 6 | POST JSON rusak | 400 | Lulus |
| 7 | POST Content-Type text/plain | 415 | Lulus |
| 8 | GET tanpa `user_id` | 400 | Lulus |
| 9 | GET daftar milik user | 200 | Lulus |
| 10 | PATCH /{id}/read | 200 (`is_read: true`) | Lulus |
| 11 | PATCH id tidak ada | 404 | Lulus |
| 12 | Service Auth dimatikan | 503 | Lulus |
| 13 | Endpoint tidak dikenal | 404 | Lulus |

## Yang masih perlu kalian lengkapi
- Jalankan ulang dengan **Auth asli** (Kelompok 1) dan lewat **Gateway** (Kelompok 5).
- Tempel tangkapan layar Postman Collection Runner ke `docs/bukti/`.
