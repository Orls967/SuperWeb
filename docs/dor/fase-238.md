# Definition of Ready (DoR) — Fase 238

**Fase**: 238 — Release Train, Change Management & Keamanan Deploy  
**Inisiatif**: W8-P1 (Kualitas & DX / Platform)  
**Tanggal**: 2026-11-10  
**Status**: READY 🔵

---

## 1. Latar Belakang & Urgensi Bisnis
Sebagai platform konglomerasi skala enterprise dengan 46 lini bisnis terdistribusi, frekuensi rilis dan migrasi basis data membutuhkan kontrol perubahan (Change Management) yang deterministik, aman, dan dapat diaudit secara ketat. Kegagalan migrasi skema database di lingkungan produksi atau lonjakan tingkat galat (*error rate*) selama rilis tanpa mekanisme rollback otomatis membawa dampak kerugian operasional dan pelanggaran SLO.

Fase 238 menghadirkan fondasi rilis enterprise:
1. **Change Management Governance**: State Machine Change Request 9 status dengan penilaian risiko otomatis (A4).
2. **Keamanan Migrasi Database**: Linter keamanan DDL MySQL 8.4 (A5) untuk memblokir migrasi berbahaya (*blocking table locks*, *zero-downtime breaking changes*).
3. **Canary Deployment & Auto-Rollback**: Evaluator statistik canary (A6) yang memonitor distribusi error secara kuantitatif dan otomatis memicu rollback jika rasio kesalahan melebihi batas kendali kritis.
4. **Release Train Orchestrator & Backfill**: Manajemen jadwal rilis periodik berkala, penanganan hotfix ganda, dan eksekutor migrasi data besar (*backfill*) idempoten berbasis chunk dengan checkpoint resume.

---

## 2. Kriteria Penerimaan (Acceptance Criteria)
1. **AC-238-1 (State Machine Change Request)**:
   - Mendukung 9 status teratur: `draft` $\rightarrow$ `assessed` $\rightarrow$ `cab_review` $\rightarrow$ `approved` $\rightarrow$ `scheduled` $\rightarrow$ `deploying` $\rightarrow$ `verified` / `rolled_back` $\rightarrow$ `closed`.
   - Transisi dilarang melompat (*guard evaluation* ketat) dan seluruh transisi tercatat di `plt_change_transitions`.
2. **AC-238-2 (Algoritma A4: ChangeRiskScorer)**:
   - Menghitung skor numerik $0.0 \dots 100.0$ berdasarkan bobot LOC diff, kompleksitas modul target, risiko migrasi DDL, dan tingkat kegagalan historis.
   - Menggolongkan perubahan ke dalam tier risiko: `low` ($<30$), `medium` ($30\dots 65$), `high` ($>65$), dan `critical` ($>85$). Skor $>65$ mewajibkan peninjauan formal CAB (*Change Advisory Board*).
3. **AC-238-3 (Algoritma A5: MigrationSafetyLinter)**:
   - Memeriksa file migrasi Laravel terhadap katalog bahaya MySQL 8.4:
     - Penambahan kolom NOT NULL tanpa DEFAULT pada tabel besar.
     - Penghapusan tabel/kolom tanpa tahap transisi deprecation.
     - Penambahan indeks tanpa ALGORITHM=INPLACE / LOCK=NONE pada tabel berpartisi.
     - Perubahan tipe data yang memicu full table rewrite.
   - Command `platform:migration-lint` keluar dengan kode 0 (aman) atau 1 (ada pelanggaran kritis).
4. **AC-238-4 (Algoritma A6: CanaryErrorEvaluator & Auto-Rollback)**:
   - Menghitung Z-score selisih error rate canary vs baseline traffic.
   - Jika error canary melebihi ambang batas ($\ge 3\times$ baseline atau $Z > 3.0$), otomatis menerbitkan status `rollback_recommended` dan memicu eksekusi rollback plan terdaftar.
5. **AC-238-5 (Release Train & Hotfix Governance)**:
   - Release Train mengelompokkan pull request/change request ke dalam jadwal peluncuran bertahap (*weekly train*).
   - Hotfix dapat disisipkan ke dalam train aktif dengan persetujuan release manager dan pengujian regresi parsial.
6. **AC-238-6 (Backfill Engine)**:
   - Eksekutor data backfill idempoten yang memproses data per chunk dengan pencatatan cursor / checkpoint di `plt_backfill_chunks`.
   - Resisten terhadap interupsi proses; saat dijalankan ulang melanjutkan dari chunk terakhir yang belum sukses tanpa menduplikasi data.
7. **AC-238-7 (Platform Audit & Corruption Fixture)**:
   - Command `platform:audit` diperluas memvalidasi konsistensi integritas Change Request, Release Train, dan status migrasi.
   - Dilengkapi fixture korupsi data khusus di `app/Quality/Audit/Fixtures/PlatformReleaseAuditCorruptionFixture.php` yang didaftarkan di `AuditCommandRegistry`.

---

## 3. Batasan Teknis & Standar Arsitektur
- **B1 Logika**: $\ge 7.500$ LOC kode murni PHP (tanpa Blade, seeder, test, database, docs).
- **B3 Dokumentasi**: $\ge 1.500$ baris dokumentasi mendalam.
- **Anti-Padding T-10**: Lulus 100% tanpa pelanggaran M-3, M-4, M-5.
- Dilarang tipe data float untuk metrik akurasi finansial atau kuota absolut; gunakan integer minor unit / fixed-point basis point.
- Dilarang panggilan `now()` langsung di domain logic; gunakan parameter timestamp atau `ClockInterface`.
- Semua rute HTTP baru berada di bawah prefix `platform` dan dilindungi middleware `['web', 'auth', 'role:admin,superadmin']`.
