# Katalog Galat — Batas Modul, API v2 & Multi-Tenancy

Katalog kode galat resmi untuk domain Batas Modul (Fase R4), API v2 (Fase 55), dan Identitas/Akses (Fase 188). Setiap galat memuat informasi terstruktur: Domain, Kode Galat, Pesan Standar, Akar Penyebab, dan Tindakan Remediasi.

---

## 1. Galat Batas Modul & Kepemilikan Data (Fase R4)

### `BOUND_A1_CROSS_MODULE_DOMAIN_IMPORT`
- **Domain**: Modul Boundaries / Static Scan
- **Pesan**: `Modul {client} dilarang mengimpor domain internal Modules\{owner}\Domain\{class}.`
- **Penyebab**: Kode pada modul pemanggil mengimpor model Eloquent, entity, atau trait internal dari modul lain secara langsung, melanggar batas modular A1.
- **Tindakan**:
  1. Hapus import ke `Modules\{owner}\Domain\{class}`.
  2. Gunakan antarmuka kontrak resmi pada `Modules\{owner}\Contracts` atau `Modules\Core\Contracts`.
  3. Jika memerlukan snapshot data entitas, gunakan DTO snapshot yang disediakan oleh kontrak directory pemilik data.

---

### `BOUND_A2_FOREIGN_TABLE_PREFIX`
- **Domain**: Data Ownership / Schema Access
- **Pesan**: `Modul {client} dilarang mengakses tabel '{table}' yang dimiliki oleh modul {owner}.`
- **Penyebab**: Modul pemanggil melakukan kueri langsung via `DB::table('{table}')`, deklarasi `$table = '{table}'` pada Model, atau eksekusi DDL `Schema::*` pada tabel yang bukan miliknya.
- **Tindakan**:
  1. Rujuk modul pemilik tabel dan buat antarmuka kontrak untuk mengekspos fungsi yang dibutuhkan.
  2. Jika tabel merupakan tabel warisan integrasi sementara, ajukan permohonan waiver dengan anchor `docs/DECISIONS.md`.
  3. Jangan pernah mengubah migrasi lama secara sembarangan tanpa mekanisme rollback aman.

---

### `BOUND_A3_CIRCULAR_DEPENDENCY`
- **Domain**: Architecture Topology / Tarjan SCC
- **Pesan**: `Siklus dependensi terlarang terdeteksi di antara modul-modul: [{modules}].`
- **Penyebab**: Sekelompok modul memiliki ketergantungan melingkar ($A \to B \to A$) yang menghasilkan ukuran Strongly Connected Component (SCC) $> 1$.
- **Tindakan**:
  1. Jalankan `php artisan arch:boundaries --format=json` untuk melihat rantai siklus.
  2. Pindahkan antarmuka kontrak bersama ke layer kernel/shared (`Modules\Core\Contracts`).
  3. Ubah salah satu modul untuk menggunakan komunikasi asinkron melalui Event Spine.

---

### `BOUND_PREFIX_CONFLICT`
- **Domain**: Prefix Registry / Configuration
- **Pesan**: `Konflik prefiks terdeteksi: Prefiks '{prefix}' diklaim oleh modul '{module_a}' dan '{module_b}'.`
- **Penyebab**: Dua modul berbeda mendaftarkan string prefiks tabel yang sama pada `config/modules.php`.
- **Tindakan**:
  1. Tinjau file `config/modules.php`.
  2. Berikan prefiks yang berbeda atau gunakan hierarki prefiks bertumpuk untuk modul subdomain.
  3. Jalankan `vendor/bin/pest tests/Architecture/ModulePrefixRegistryTest.php` untuk memastikan keunikan prefiks.

---

### `BOUND_WAIVER_EXPIRED`
- **Domain**: Waiver Lifecycle / State Machine
- **Pesan**: `Waiver untuk pelanggaran '{signature}' telah kedaluwarsa pada {date}.`
- **Penyebab**: Batas waktu dispensasi 90 hari telah terlewati tanpa adanya penyelesaian migrasi atau perpanjangan resmi.
- **Tindakan**:
  1. Periksa tiket tugas di Kotak Masuk "Tugas Saya" via `TaskInboxService`.
  2. Selesaikan implementasi kontrak untuk menghapus pelanggaran secara permanen.
  3. Jika diperlukan penyesuaian jadwal, ajukan perpanjangan waiver melalui `ApprovalEngine` dengan evaluasi Platform Architect.

---

### `BOUND_WAIVER_INVALID_ANCHOR`
- **Domain**: Waiver Governance / Audit
- **Pesan**: `Anchor keputusan '{anchor}' tidak ditemukan dalam docs/DECISIONS.md.`
- **Penyebab**: Pengajuan waiver menyertakan rujukan anchor fiktif atau tidak valid yang tidak terdaftar di log keputusan arsitektur.
- **Tindakan**:
  1. Daftarkan ADR resmi pada direktori `docs/decisions/`.
  2. Tambahkan anchor indeks pada file `docs/DECISIONS.md`.
  3. Masukkan anchor yang valid pada saat memanggil `WaiveViolationAction`.

---

### `BOUND_WAIVER_EXCEEDS_90_DAYS`
- **Domain**: Waiver Governance / Guard Rule
- **Pesan**: `Durasi waiver {days} hari melebihi batas maksimum 90 hari.`
- **Penyebab**: Permintaan waiver mencantumkan tanggal kedaluwarsa lebih dari 90 hari ke depan.
- **Tindakan**:
  1. Ubah parameter `expires_at` agar berada dalam rentang maksimal 90 hari sejak tanggal pengajuan.

---

### `BOUND_SM_INVALID_TRANSITION`
- **Domain**: Boundary Violation State Machine
- **Pesan**: `Transisi status ilegal dari '{from}' ke '{to}' untuk pelanggaran #{id}.`
- **Penyebab**: Upaya memindahkan status pelanggaran yang melompati tahapan yang diizinkan (misal langsung dari `detected` ke `closed`).
- **Tindakan**:
  1. Ikuti tahapan State Machine: `detected` $\to$ `triaged` $\to$ `contract_designed` $\to$ `migrating` $\to$ `verified` $\to$ `closed`.
  2. Pastikan seluruh guard transisi terpenuhi sebelum memicu pemindahan status.

---

### `BOUND_AUDIT_DUAL_SOURCE_MISMATCH`
- **Domain**: Dual-Source Reconciliation
- **Pesan**: `Audit gagal: Terdapat diskrepansi antara hasil scan statis dan rekaman basis data: {details}.`
- **Penyebab**: Perintah `boundary:audit` menemukan bahwa terdapat pelanggaran di kode sumber yang belum dicatat di database, atau ada entri berstatus `closed` yang masih tercantum dalam baseline ratchet.
- **Tindakan**:
  1. Jalankan `php artisan boundary:triage` untuk menyinkronkan rekaman.
  2. Jalankan `php artisan arch:scan --update-baseline` jika perbaikan kode telah selesai.
  3. Periksa kembali dengan `php artisan boundary:audit`.

---

## 2. Galat API v2 & Idempotensi (Fase 55)

### `API_IDEMPOTENCY_KEY_MISSING` (HTTP 422)
- **Domain**: API v2 Protocol
- **Pesan**: `Header 'Idempotency-Key' wajib disertakan untuk metode mutasi state.`
- **Penyebab**: Klien mengirim permintaan `POST`, `PUT`, `PATCH`, atau `DELETE` tanpa menyertakan header `Idempotency-Key`.
- **Tindakan**: Sertakan UUID v4 unik pada header `Idempotency-Key` pada setiap mutasi state.

### `API_IDEMPOTENCY_PAYLOAD_MISMATCH` (HTTP 409)
- **Domain**: API v2 Protocol
- **Pesan**: `Idempotency-Key telah digunakan sebelumnya dengan payload permintaan yang berbeda.`
- **Penyebab**: Klien menggunakan ulang kunci idempotensi yang sama untuk operasi yang berbeda atau parameter yang diubah.
- **Tindakan**: Buat kunci idempotensi baru untuk payload baru, atau gunakan kembali payload asli jika berniat melakukan retry.

### `API_RATE_LIMIT_EXCEEDED` (HTTP 429)
- **Domain**: API Rate Limiting / Token Bucket
- **Pesan**: `Batas laju permintaan terlampaui. Silakan coba kembali setelah {retry_after} detik.`
- **Penyebab**: Klien mengonsumsi token permintaan melebihi kuota per detik atau per menit tier langganan.
- **Tindakan**: Hormati header respons `Retry-After` dan implementasikan backoff eksponensial di sisi klien.

### `API_WEBHOOK_SIGNATURE_INVALID` (HTTP 401)
- **Domain**: Webhook Security / HMAC
- **Pesan**: `Tanda tangan webhook HMAC-SHA256 pada header X-Signature tidak cocok atau telah kedaluwarsa.`
- **Penyebab**: Nilai hash HMAC tidak sesuai dengan payload, stempel waktu melampaui toleransi 300 detik, atau kunci rahasia salah.
- **Tindakan**: Verifikasi penghitungan HMAC menggunakan secret aktif dan sinkronkan waktu server klien dengan NTP.

---

## 3. Galat Otorisasi & Tenancy (Fase 188)

### `ACCESS_POLICY_DENIED` (HTTP 403)
- **Domain**: ABAC Evaluation
- **Pesan**: `Akses ditolak berdasarkan aturan kebijakan akses deny-overrides.`
- **Penyebab**: Evaluator kebijakan ABAC menemukan aturan eksplisit `DENY` pada atribut konteks permintaan pengguna (`entity`, `region`, `site`, `time`).
- **Tindakan**: Periksa hak akses pengguna dan pastikan atribut penugasan sesuai dengan batasan kebijakan organisasi.

### `TENANT_CROSS_BOUNDARY_LEAK` (HTTP 403)
- **Domain**: Tenancy Isolation
- **Pesan**: `Upaya akses lintas batas tenant terdeteksi dan diblokir secara otomatis.`
- **Penyebab**: Kueri mencoba mengakses rekaman yang memiliki `tenant_id` berbeda dengan konteks aktif pengguna terautentikasi.
- **Tindakan**: Pastikan seluruh query melalui model ber-scope `TenantScope` dan jangan menonaktifkan filter tenant secara ilegal.

