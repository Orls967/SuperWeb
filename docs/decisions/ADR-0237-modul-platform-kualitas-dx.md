# ADR-0237: Modul Platform, Kualitas Kode, Metrik Fase, Sandbox State Machine & Pure PHP Vector Renderer

## Status
DITERIMA (2026-11-09) — Disetujui melalui Keputusan Pemilik IZIN-1, IZIN-2, IZIN-3 (W8-P1).

## Konteks
Sebagai gerbang awal Gelombang 8, modul `Platform` (`plt_`) dan engine audit kualitas kode di `app/Quality` bertanggung jawab menegakkan tolok ukur integritas kode, mengukur metrik fase (`quality:phase-metrics`), mengelola lingkungan pengujian developer (`SandboxEnvironmentStateMachine`), serta merender dokumentasi visual arsitektur secara native PHP tanpa ketergantungan runtime eksternal (Python/Node).

## Keputusan
1. **Modul Platform & Prefiks plt_**: Modul didaftarkan pada `config/modules.php` dengan prefiks tabel `plt_`. Baseline allowlist `tests/Architecture/baselines/config-allowlists.json` dinaikkan secara resmi dengan anchor ke ADR ini.
2. **Algoritma Bersertifikat A1, A2, A3**:
   - A1: `DuplicateWindowDetector` mendeteksi token duplikasi identik menggunakan rolling hash Rabin-Karp (basis 257 mod 1.000.000.007) dengan sliding window 25 token.
   - A2: `DecisionDensityCounter` menghitung rasio titik percabangan keputusan eksekutabel terhadap LOC.
   - A3: `ClassReachabilityGraph` menelusuri graf dependensi kelas dari root set (Controllers, Commands, Listeners, Providers) guna menangkap kelas yatim (M-5).
3. **Sandbox Lifecycle (7 Status)**: Menggunakan state machine berstatus `requested`, `provisioning`, `seeded`, `ready`, `expiring`, `expired`, `destroyed` dengan aturan transisi ketat dan guard kuota/TTL.
4. **Pure PHP SVG Vector Generator**: Implementasi rendering SVG mandiri di `app/Quality/Docs/Render/` yang mengompilasi representasi diagram Mermaid ke SVG standar tanpa dependensi Python atau headless browser.
5. **Retirement Layanan Lama**: Layanan legacy di `Modules/Integration` (`DeveloperExperienceQualityService` dan `ReleaseTrainDeploymentSafetyService`) ditandai `@deprecated` dan dicatat di `docs/catalog/integration-retirement.md` tanpa menghapus atau menduplikasi kode.
6. **Core Aditif Terkendali (T-9)**: Penambahan correlation tracking di Core dibatasi maksimal 40 baris aditif untuk `X-Correlation-Id`.

## Konsekuensi
- Memastikan seluruh fase berikutnya di Gelombang 8 hingga 25 memiliki alat ukur resmi yang obyektif dan mampu menangkap manipulasi/padding kode (M-2 hingga M-5).
- Menjaga pipeline CI tetap bersih, cepat, dan mandiri tanpa overhead instalasi toolchain dokumen pihak ketiga.

