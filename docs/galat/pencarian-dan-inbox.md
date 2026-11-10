# Katalog Galat & Penanganan Eksepsi — Fase 194

Dokumen ini mencatat seluruh kode kesalahan spesifik domain pencarian global dan kotak masuk tugas beserta tindakan pemulihan (*recovery actions*).

---

## 1. Galat Mesin Pencarian Global

### 1.1. `SearchIndexCorruptionException`
- **Penyebab:** Terjadi ketidakcocokan tanda tangan digital HMAC-SHA256 pada kalkulasi BM25 atau koefisien Jaccard saat audit dua sumber dieksekusi.
- **Tindakan Pemulihan:**  
  Jalankan pembersihan dan pembangunan ulang indeks modul terkait menggunakan perintah:  
  `php artisan search:reindex --module=<nama_modul> --force`

### 1.2. `InvalidIndexJobTransitionException`
- **Penyebab:** Percobaan mutasi status pada `IndexJobStateMachine` yang melanggar aturan transisi yang sah (misalnya melompat dari `pending` langsung ke `indexed` tanpa melewati `processing`).
- **Tindakan Pemulihan:**  
  Periksa alur pemrosesan pekerja antrean (*queue worker*); pastikan pekerjaan diproses secara berurutan dan gunakan idempotency key deterministik.

### 1.3. `SearchRateLimitExceededException`
- **Penyebab:** Kueri pencarian dari alamat IP atau pengguna yang sama melebihi ambang batas perlindungan DDoS (maksimal 60 permintaan per menit).
- **Tindakan Pemulihan:**  
  Gunakan debounce input minimal 250ms pada komponen pencarian sisi klien.

---

## 2. Galat Kotak Masuk Tugas Saya

### 2.1. `TaskAlreadyCompletedException`
- **Penyebab:** Percobaan mengeksekusi aksi (Approve / Reject) pada tugas yang statusnya sudah berstatus `COMPLETED` atau `REJECTED`.
- **Tindakan Pemulihan:**  
  Sistem mengembalikan respons HTTP 409 Conflict dan me-refresh tampilan antarmuka kotak masuk pengguna untuk menampilkan status mutakhir.

### 2.2. `UnauthorizedTaskActionException`
- **Penyebab:** Pengguna mencoba mengeksekusi aksi pada tugas yang tidak ditugaskan ke peran atau akun pengguna tersebut (pelanggaran RBAC anti-IDOR).
- **Tindakan Pemulihan:**  
  Sistem memblokir aksi dengan kode status HTTP 403 Forbidden dan mencatat insiden keamanan ke tabel log audit.

### 2.3. `TaskEscalationGuardException`
- **Penyebab:** Percobaan melakukan eskalasi manual pada tugas yang belum melewati batas waktu tenggat (*due date*).
- **Tindakan Pemulihan:**  
  Pastikan eskalasi hanya dijalankan oleh daemon sistem terjadwal `inbox:escalate` atau pengguna dengan hak akses `admin`.
