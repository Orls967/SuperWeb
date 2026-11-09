# Spesifikasi Dunia Simulasi Platform (Duta Mall & Ekosistem Internal)

## 1. Entitas dan Batas Simulasi

Modul Platform beroperasi dalam konteks simulasi ekosistem korporasi Duta Mall Group yang menaungi berbagai divisi:
- **PULSE**: Divisi platform dan developer tooling internal yang mengelola CI/CD, metrik kualitas, sandbox lingkungan, dan kepatuhan privasi data.
- **SUNSET**: Unit pemantauan keandalan sistem (Site Reliability Engineering / SRE) yang bertugas menjaga ketersediaan layanan dan alokasi error budget.
- **Duta Mall Retail & Finance**: Unit bisnis pemanfaat platform yang mengonsumsi layanan sandbox untuk pengujian fitur pembayaran dan inventori.

## 2. Persona dan Aktor Simulasi

Dalam seeder deterministik dan pengujian acceptance, disimulasikan 4 persona kunci:
1. **Radit Pratama (`radit.platform@dutamall.internal`)**:
   - Role: Lead Platform Engineer.
   - Wewenang: Mengelola konfigurasi seluruh sandbox, mengatur alokasi kuota CPU/Memory, dan memicu garbage collection.
2. **Siti Aisyah (`siti.sre@dutamall.internal`)**:
   - Role: SRE Observability Specialist.
   - Wewenang: Menentukan Service Level Objective (SLO), mengaudit laporan postmortem, dan memantau multi-window burn rate.
3. **Budi Santoso (`budi.developer@dutamall.internal`)**:
   - Role: Senior Software Engineer (Pengadaan & Finansial).
   - Pengguna aktif sandbox ephemeral, mengeksekusi pengujian migrasi basis data, dan memverifikasi korelasi trace HTTP.
4. **Dewi Lestari (`dewi.qa@dutamall.internal`)**:
   - Role: QA & Data Compliance Officer.
   - Bertanggung jawab memverifikasi kebersihan masking PII pada dataset pengujian staging.

## 3. Skenario Uji T1 dan Kejutan

Simulasi lingkungan T1 memuat skenario operasional realistis:
- **Skenario Normal**:
  - 12 sandbox aktif tersebar dalam 7 status siklus hidup (`requested`, `provisioning`, `seeded`, `ready`, `expiring`, `expired`, `destroyed`).
  - 4 target SLO terkonfigurasi dengan error budget sehat (burn rate < 1.0).
  - 3 dataset sintetik ter-masking sempurna tanpa kebocoran PII.
- **Skenario Kejutan (Chaos & Outage)**:
  - Lonjakan galat tiba-tiba pada layanan pembayaran yang menyebabkan konsumsi error budget darurat (burn rate 14.4x) sehingga memicu deployment hold.
  - Kegagalan serentak proses provisioning pada beberapa sandbox yang memicu transisi pemulihan darurat dan pencatatan audit kegagalan.
  - Upaya pembacaan sandbox milik developer lain yang dicegah secara absolut oleh `SandboxEnvironmentPolicy` (Anti-IDOR).

