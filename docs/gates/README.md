# Panduan Direktori Quality Gate Reports (`docs/gates/`)

Direktori ini memuat arsip laporan resmi pengujian dan quality gate untuk setiap fase proyek (`docs/gates/fase-{N}.md`), sesuai dengan protokol eksekusi `docs/PROGRESS.md` (§P4 & §P7).

---

## 1. Prinsip Utama & Larangan Salin-Tempel (Anti-pola X13, X24)

1. **Wajib Dihasilkan Command:** Seluruh laporan gate di folder ini **wajib dihasilkan otomatis** oleh command:
   ```bash
   php artisan gate:report --fase=N
   ```
   Laporan **dilarang keras** ditulis tangan atau hasil salin-tempel (Anti-pola X13/X24).
2. **Tidak Bisa Dibuat Bila Gate Gagal:** Command `gate:report` secara otomatis menolak membuat file laporan apabila test suite mengalami kegagalan (`failures > 0` atau `errors > 0` di JUnit XML).
3. **Commit Terikat:** Laporan mengunci hash commit git aktual pada saat suite pengujian dijalankan.

---

## 2. Struktur Laporan Quality Gate

Setiap laporan `fase-{N}.md` memiliki 5 bagian terstandarisasi:

### Bagian 1: Metadata Eksekusi
- **Commit:** Hash git lengkap dan short hash branch yang diuji.
- **Tanggal:** Timestamp waktu pembuatan laporan.
- **PHP & DB:** Versi runtime PHP asli (mis. PHP 8.4) dan database server (mis. SQLite / MySQL 8.0).
- **Status Keseluruhan:** Indikator visual hijau (`PASS 🟢`) atau merah (`FAIL 🔴`).

### Bagian 2: Ringkasan Test Suite
Data faktual dari log XML Pest/JUnit:
- Total Test & Assertions.
- Durasi total eksekusi test suite penuh.
- Jumlah kegagalan dan error.

### Bagian 3: Hasil Perintah Protokol P4
Tabel status dan exit code untuk 5 perintah gate wajib:
- `composer gate`
- `php artisan arch:scan`
- `php artisan bank:reconcile`
- `php artisan chain:audit-all`
- `php artisan super:health-check`

### Bagian 4: Status Test Blok Bukti Fase N
Daftar seluruh test case yang tercantum pada blok `Bukti:` fase bersangkutan di `docs/PROGRESS.md`. Setiap test dicocokkan langsung ke record eksekusi JUnit untuk membuktikan bahwa test tersebut benar-benar dieksekusi dan berstatus `PASS`.

### Bagian 5: Verifikasi Silang (Wajib untuk Status ✅)
Bagian ini diisi oleh **verifikator independen** (sesi agent terpisah atau pemilik proyek) setelah memvalidasi checklist C1–C14 di template PR:
- Tanggal verifikasi
- Nama/identitas verifikator
- Checklist C1–C14
- Catatan temuan atau rekomendasi

> ⚠️ **PENTING (P7, X20):** Fase yang berstatus `✅` **wajib** memiliki bagian Verifikasi yang terisi di `docs/gates/fase-{N}.md`. Pelaksana hanya boleh mengantar fase hingga status `🔵`. Mengubah ke `✅` tanpa verifikasi silang akan otomatis digagalkan oleh `ProgressIntegrityTest`.

---

## 3. Cara Menghasilkan Laporan Gate Baru

Jalankan rangkaian gate penuh pada terminal:

```bash
# 1. Jalankan test suite penuh dan buat log JUnit
composer gate

# 2. Jalankan pindaian arsitektur
php artisan arch:scan

# 3. Jalankan audit inti
php artisan bank:reconcile
php artisan chain:audit-all
php artisan super:health-check

# 4. Hasilkan laporan resmi gate untuk fase yang dikerjakan
php artisan gate:report --fase=R0
```
Laporan akan otomatis tersimpan di `docs/gates/fase-r0.md`.
