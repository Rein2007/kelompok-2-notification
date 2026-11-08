# Notification Service: Kelompok 2

Service **Notification** untuk proyek Mini LMS Terdistribusi (PTIK A Sistem Terdistribusi).

## Anggota dan kontribusi
| Nama | NIM | Kontribusi |
|---|---|---|
| (isi) | (isi) | (isi) |

## Cara menjalankan
Syarat: PHP 8+ dengan ekstensi `curl` dan `mbstring` (sudah ada di XAMPP).

```bash
php -S 127.0.0.1:8106 index.php
# agar bisa diakses laptop lain:
php -S 0.0.0.0:8106 index.php
```
Port: **8106**. Alamat service lain dibaca dari `services.json`. Opsi lain ada di `.env.example` (salin ke `.env`).

## Endpoint
`GET /health` · `GET /api/notifications?user_id=` · `POST /api/notifications` · `PATCH /api/notifications/{id}/read`
Detail lengkap di [API_CONTRACT.md](API_CONTRACT.md).

## Pengujian
- Mandiri tanpa Auth: set `AUTH_BYPASS=true` di `.env`, lalu `BYPASS=true bash tests/test_api.sh`
- Dengan Auth asli: `TOKEN=<token> bash tests/test_api.sh`
- Postman: impor `postman/Mini-LMS-Notification.postman_collection.json`, atur variabel `base_url` (Gateway `:8101`, atau `:8106` untuk uji langsung) dan `token`
- Ringkasan hasil: `docs/TESTING.md`

> Saat integrasi dan demo, **matikan `AUTH_BYPASS`** agar token benar-benar divalidasi.

## Penyimpanan
File `data/notifications.json` (tanpa database). Hapus isinya menjadi `{"seq":0,"items":[]}` untuk mereset data demo.

## Keterbatasan yang diketahui
- Penyimpanan berbasis file JSON, cocok untuk skala demo, bukan produksi.
- Validasi `user_id` tidak memeriksa ke Authentication (hanya memvalidasi token).
- Belum ada fitur hapus notifikasi atau pagination.
