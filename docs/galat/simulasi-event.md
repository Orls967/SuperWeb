# Katalog Galat & Penanganan Masalah Kernel Simulasi (Fase 67)

Dokumen ini memuat daftar kode galat resmi (*error codes*), pesan kesalahan baku, akar penyebab (*root cause*), dan instruksi penanganan untuk seluruh komponen Kernel Simulasi Waktu Virtual dan Digital Twin Bus.

---

## 1. Tabel Kode Galat Resmi Platform

| Kode Galat | Kelas Eksepsi | Status HTTP | Dampak | Penjelasan Singkat |
|---|---|---|---|---|
| `PAST_EVENT` | `PastEventScheduledException` | 422 | Eksekusi dibatalkan | Mencoba menjadwalkan event ke detik virtual lampau. |
| `CYCLIC_DEPENDENCY` | `CyclicDependencyException` | 500 | Inisialisasi gagal | Terdapat ketergantungan melingkar antar `SimTickHandler`. |
| `INVALID_CERTIFICATE` | `InvalidAlgorithmCertificateException` | 500 | Audit gagal | Sertifikat bukti matematis A1/A2/A3 tidak cocok dengan data evaluasi. |
| `CORRUPTED_CHECKPOINT` | `CorruptedCheckpointException` | 500 | Resume dibatalkan | Hash data checkpoint tidak sesuai dengan perhitungan SHA-256 kanonikal. |
| `ILLEGAL_TRANSITION` | `InvalidSimulationTransitionException` | 422 | Transisi ditolak | Transisi status state machine tidak terdaftar dalam matriks sah. |
| `LEDGER_UNBALANCED` | `RuntimeException` | 500 | Sandbox rollback | Diskrepansi saldo $\sum \text{Debit} \neq \sum \text{Kredit}$ terdeteksi oleh probe. |
| `ANTI_IDOR_FORBIDDEN` | `AuthorizationException` | 403 | Akses ditolak | Operator mencoba mengakses atau memanipulasi sesi milik pengguna lain. |
| `IDEMPOTENCY_MISSING` | `ValidationException` | 422 | Request ditolak | Header HTTP `Idempotency-Key` tidak disertakan pada request POST inisiasi sesi. |
| `STREAM_EXHAUSTED` | `SimulationStreamExhaustedException` | 500 | Simulasi terhenti | Siklus periode generator bilangan acak habis atau keluar domain legal. |

---

## 2. Rincian & Solusi Galat

### 2.1. `PAST_EVENT` (Past Event Scheduled Exception)
- **Pesan Contoh:** `[PAST_EVENT] Tidak dapat menjadwalkan event ke masa lampau: waktu saat ini 150, waktu target 120.`
- **Akar Masalah:** Logika pada salah satu Action atau Handler domain mencoba memanggil penjadwal `schedule()` dengan argumen `virtual_second` yang lebih kecil daripada waktu virtual berjalan saat ini.
- **Solusi:** Periksa pemanggil event pada modul domain. Pastikan penambahan waktu selalu relatif terhadap waktu virtual saat ini ($\text{target} \ge \text{current}$).

### 2.2. `CYCLIC_DEPENDENCY` (Cyclic Dependency Exception)
- **Pesan Contoh:** `Kahn Topological Order: Terdeteksi dependensi siklik antar modul: [hospital_discharge, hotel_night_audit, hospital_discharge]`
- **Akar Masalah:** Dua atau lebih `SimTickHandler` menyatakan ketergantungan timbal balik (`A` bergantung `B` dan `B` bergantung `A`).
- **Solusi:** Buka implementasi `dependencies()` pada handler yang terdaftar. Hapus dependensi timbal balik dengan menggunakan event asinkron atau memisahkan tahapan tick menjadi beberapa fase (pagi vs malam).

### 2.3. `CORRUPTED_CHECKPOINT` (Corrupted Checkpoint Exception)
- **Pesan Contoh:** `Checkpoint korup pada hari ke-30: hash tercatat tidak cocok dengan ringkasan state.`
- **Akar Masalah:** Terjadi modifikasi langsung pada tabel `sim_checkpoints` melalui skrip manual, atau kegagalan I/O pada media penyimpanan basis data.
- **Solusi:** Jalankan `php artisan sim:audit` untuk mengidentifikasi baris yang korup. Pulihkan checkpoint dari cadangan basis data terverifikasi atau hapus checkpoint usang dengan `sim:checkpoint-prune`.

### 2.4. `ILLEGAL_TRANSITION` (Illegal Transition Exception)
- **Pesan Contoh:** `Transisi status simulasi ilegal dari 'draft' ke 'completed'.`
- **Akar Masalah:** Pengendali antarmuka atau perintah artisan mencoba mengubah status sesi simulasi melompati tahapan wajib (misal dari `draft` langsung ingin `completed` tanpa melalui `provisioned` dan `running`).
- **Solusi:** Ikuti diagram status resmi. Panggil metode transisi secara berurutan sesuai alur hidup mesin status simulasi.

### 2.5. `ANTI_IDOR_FORBIDDEN` (Anti-IDOR Forbidden)
- **Pesan Contoh:** `403 This action is unauthorized.`
- **Akar Masalah:** Pengguna yang sedang masuk tidak memiliki peran `admin` atau `sim_operator`, atau mencoba menghentikan sesi yang diinisiasi oleh operator lain (`created_by` tidak cocok).
- **Solusi:** Pastikan pengguna memiliki role yang berhak dan hanya mengelola sesi yang mereka miliki sendiri, atau minta personil dengan role `admin` untuk melakukan supervisi.

### 2.6. `IDEMPOTENCY_MISSING` (Missing Idempotency Key)
- **Pesan Contoh:** `The idempotency-key header is required.`
- **Akar Masalah:** Pemanggilan API endpoint `POST /admin/simulation` dilakukan tanpa menyertakan header `Idempotency-Key`.
- **Solusi:** Tambahkan header unik `Idempotency-Key: <UUID>` pada setiap panggilan permintaan pembuatan sesi.

