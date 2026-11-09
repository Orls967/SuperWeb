# ADR-0238: Release Train, Change Management & Keamanan Deploy

## Konteks & Latar Belakang
Dalam inisiatif Wave 8 (Fase 238), sistem orkestrasi rilis konglomerasi SuperWeb membutuhkan kontrol perubahan (Change Management), orkestrasi rilis berkala (Release Train), linter keselamatan skema database (MySQL 8.4 DDL Linter), dan mekanisme canary error detection dengan auto-rollback otomatis.

Keputusan arsitektur ini mencakup tiga keputusan utama: **W8-D06**, **W8-D07**, dan **W8-D08**.

---

## Keputusan Arsitektur

### W8-D06: State Machine Change Request 9-Status & Algoritma A4 ChangeRiskScorer
- **Keputusan**: Seluruh perubahan produksi wajib memiliki tiket `ChangeRequest` dengan state machine ketat 9 status: `draft` $\rightarrow$ `assessed` $\rightarrow$ `cab_review` $\rightarrow$ `approved` $\rightarrow$ `scheduled` $\rightarrow$ `deploying` $\rightarrow$ `verified` / `rolled_back` $\rightarrow$ `closed`.
- **Aturan Transisi**: Transisi hanya diizinkan melalui guard method `canTransitionTo()`. Tiket dengan skor risiko A4 $> 65$ wajib melewati status `cab_review` sebelum dapat di-approve.
- **Konsekuensi Positif**: Standarisasi audit trail ITIL enterprise, zero undocumented production deployment, kepatuhan audit SOC2/ISO27001.

### W8-D07: Algoritma A5 Database Migration Safety Linter
- **Keputusan**: Mengimplementasikan `MigrationSafetyLinter` untuk memvalidasi seluruh migrasi skema basis data sebelum dieksekusi di produksi. Linter mendeteksi operasi berisiko tinggi pada MySQL 8.4: penambahan kolom NOT NULL tanpa default, penghapusan kolom/tabel tanpa fase deprecation, dan perubahan tipe kunci asing yang memicu eksklusif table lock.
- **Aturan Enforcement**: Integrasi pra-deploy via `php artisan platform:migration-lint`. Kegagalan linter menghentikan deployment pipeline dengan exit code 1.
- **Konsekuensi Positif**: Mencegah insiden downtime produksi akibat table locks, menjamin zero-downtime deployment (ZDD) skema.

### W8-D08: Algoritma A6 Canary Error Evaluator & Automated Rollback
- **Keputusan**: Menerapkan evaluasi statistik deviasi rasio galat (Z-Score Hypothesis Test) pada lalu lintas canary vs baseline traffic.
- **Ambang Batas**: Jika $Z \ge 3.0$ atau rasio error canary $\ge 3.0\times$ baseline (dengan minimum observasi $n \ge 100$), sistem otomatis menerbitkan rekomendasi `rollback` dan menjalankan rencana pemulihan (`plt_rollback_plans`) tanpa menunggu intervensi manual.
- **Konsekuensi Positif**: Meminimalkan blast radius regresi rilis dari menit/jam menjadi detik, melindungi SLO ketersediaan layanan.

---

## Status
Diterima & Diimplementasikan (Fase 238 — Inisiatif W8-P1).

