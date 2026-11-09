# Konsep dan Arsitektur Modul Platform (plt_)

## 1. Latar Belakang dan Masalah Bisnis

Modul **Platform** (`plt_`) dibangun untuk menjawab kebutuhan platform engineering dan developer experience (DX) modern pada arsitektur monolit modular. Dalam skala enterprise dengan puluhan modul domain (Core, Pengadaan, Gudang, Finansial, Penjualan, HR, dsb.), pengembang dan tim QA membutuhkan lingkungan uji yang cepat, terisolasi, deterministik, dan aman secara regulasi data (GDPR/UU PDP).

Tantangan utama yang diselesaikan oleh modul Platform meliputi:
1. **Penyediaan Sandbox Terisolasi**: Pengembang membutuhkan environment staging/ephemeral yang dapat di-seed secara instan tanpa mengotori basis data bersama atau mengganggu pipeline pengujian lain.
2. **Kepatuhan Privasi Data (PII Redaction)**: Data produksi tidak boleh disalin mentah ke lingkungan pengujian. Modul Platform menyediakan pipeline penyamaran (masking) dan tokenisasi deterministik (HMAC-SHA256 salt) yang melindungi email, NIK, nomor telepon, dan nomor rekening finansial.
3. **Observabilitas dan SLO (Service Level Objectives)**: Menghitung error budget, multi-window burn rate (1 jam vs 6 jam), serta routing peringatan otomatis ketika konsumsi error budget melampaui ambang bahaya.
4. **Korelasi Jejak Terdistribusi (Distributed Tracing)**: Menautkan alur eksekusi lintas modul melalui HTTP middleware dan W3C Trace Context (`traceparent`).

## 2. Aktor dan Hak Akses (RBAC)

Modul Platform beroperasi di bawah pengawasan kebijakan keamanan ketat (`SandboxEnvironmentPolicy`):
- **Platform Engineer / DevOps Admin**:
  - Memiliki akses penuh untuk membuat, memperpanjang, memusnahkan, dan memicu garbage collection pada semua sandbox.
  - Mengonfigurasi Service Level Objective (SLO), error budget target, dan ambang burn rate.
  - Memicu audit berkala (`platform:audit`) dan inspeksi integritas hash audit trail.
- **Developer / Tenant User**:
  - Hanya dapat mengelola sandbox miliknya sendiri (anti-IDOR guard).
  - Dapat merequest perpanjangan masa berlaku (TTL) maksimal hingga 72 jam dari batas awal.
  - Membaca metrik fase kualitas dan status pipeline branch masing-masing.

## 3. State Machine Sandbox Lingkungan

Siklus hidup sandbox dikontrol oleh `SandboxEnvironmentStateMachine` dengan 7 status deterministik:

```
[requested] ──> [provisioning] ──> [seeded] ──> [ready]
                      │                           │
                      ▼                           ▼
                  [destroyed]                [expiring] ──> [expired] ──> [destroyed]
```

Aturan Transisi:
- `requested` $\rightarrow$ `provisioning` (via `start_provisioning`)
- `provisioning` $\rightarrow$ `seeded` (via `mark_seeded`)
- `seeded` $\rightarrow$ `ready` (via `mark_ready`)
- `ready` $\rightarrow$ `expiring` (via `mark_expiring`)
- `expiring` $\rightarrow$ `expired` (via `mark_expired`)
- `expired` $\rightarrow$ `destroyed` (via `destroy`)
- Setiap status aktif dapat bertransisi langsung ke `destroyed` jika dibatalkan secara eksplisit oleh admin atau garbage collector.

## 4. Pipeline Penyamaran PII (Data Privacy Vault)

Pipeline masking PII menggunakan kombinasi strategi tokenisasi deterministik dan penggantian sintetik:
1. **Email**: Menjaga struktur domain organisasi (`user_XXXX@example.internal`) dengan tokenisasi hash pendek pada local-part.
2. **Nomor Telepon**: Mengganti digit tengah dengan karakter 'X' deterministik sesuai salt audit.
3. **Nomor Identitas (NIK/KTP)**: Mengonversi nomor 16-digit menjadi token terenkripsi HMAC yang tetap mempertahankan keunikan untuk uji constraint basis data.
4. **Verifikasi Zero-Leakage**: Perintah `platform:mask-verify` memindai seluruh record hasil masking untuk memastikan tidak ada ekspresi reguler PII asli yang tersisa.

## 5. Model Data dan Relasi Entitas

Tabel basis data yang dikelola modul Platform menggunakan prefiks terisolasi `plt_`:
- `plt_sandboxes`: Menyimpan profil sandbox, slug unik, owner_id, status siklus hidup, alokasi memori/CPU, dan batas waktu kedaluwarsa (`expires_at`).
- `plt_sandbox_transitions`: Catatan transisi state machine (status asal, status tujuan, pemicu, timestamp, payload audit).
- `plt_phase_metrics`: Metrik kualitas fase rekayasa (B1 LOC, B3 docs, skor duplikasi, cyclomatic density, jumlah pelanggaran arsitektur).
- `plt_service_slos`: Target ketersediaan layanan (target persentase 99.0% - 99.99%), jendela observasi (24 jam, 7 hari, 30 hari), dan anggaran galat (error budget).
- `plt_postmortems`: Dokumentasi insiden ketika terjadi konsumsi error budget darurat, memuat root cause, time to restore (TTR), dan rencana tindakan pencegahan.
- `plt_synth_datasets`: Metadata dataset sintetik yang dihasilkan oleh generator data deterministik.
- `plt_mask_rules`: Aturan penyaringan kolom sensitif per tabel modul.
- `plt_audit_runs`: Jejak integritas eksekusi audit otomatis beserta SHA-256 fingerprint dari setiap run.

