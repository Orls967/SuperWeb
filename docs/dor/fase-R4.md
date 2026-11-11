# Definition of Ready (DoR) — Fase R4: Batas Modul & Kepemilikan Data

**ID Fase**: Fase R4  
**Modul Pemilik**: `Core` (`core_`)  
**Tautan Rencana**: `rencana-gelombang-8-25.md` (§W8-P4)  
**Target Garis Kode (B1)**: ≥ 6.000 baris logika murni  
**Target Dokumentasi (B3)**: ≥ 1.500 baris tulis-tangan  
**Target Baseline ArchScan**: Penurunan total $A1 + A2 \ge 50\%$ (dari 39 menjadi $\le 19$; target riil $\le 15$), $A12 \le 300$ tanpa kenaikan.

---

## 1. Identitas & Ruang Lingkup
- **Nama Modul**: Core Boundaries (`Modules\Core\Application\Boundaries`, `Modules\Core\Domain\Boundaries`, `Modules\Core\Contracts`)
- **Prefiks Tabel**: `core_`
- **Tabel Utama Fase R4**:
  - `core_boundary_violations`: Penyimpanan fakta pelanggaran batas arsitektur (A1, A2, A3) berstatus state machine 7 tahap.
  - `core_boundary_violation_transitions`: Riwayat transisi state machine append-only (siapa, kapan, alasan, commit, hash bukti).
- **Roles Berhak**: `platform_architect`, `auditor`, `admin`
- **Tingkat Simulasi**: S2 (Pemindaian statis AST deterministik berbasis token PHP; simulasi dependensi modul dan resolusi prefiks tabel tanpa efek samping mutasi basis data).
- **Tier Skala**: T1 (≥ 65 modul terdaftar di `config/modules.php`, pemindaian seluruh file PHP di `modules/`, ≥ 25 pelanggaran terdaftar mencakup seluruh 7 status SM, ≥ 5 waiver bertenggat waktu $\le 90$ hari).

---

## 2. Rencana Anggaran Baris (RAB) — Tabel T-2

| Lapisan | Rentang Rencana (Baris) | Penjelasan Komponen |
|---|---|---|
| **Algoritma & Verifier** | 1.100–1.600 | `StronglyConnectedComponents` (Tarjan SCC deterministik dengan kondensasi DAG terurut alfabet), `TablePrefixOwnershipResolver` (longest prefix matching dengan hierarki bertumpuk), `ContractCoverageScorer` (rasio permil bilangan bulat tanpa float), serta `SccCertificateVerifier`, `PrefixCertificateVerifier`, `CoverageCertificateVerifier`. |
| **State Machine & Model** | 800–1.200 | `BoundaryViolationStateMachine` (7 status: `detected`, `triaged`, `contract_designed`, `migrating`, `verified`, `closed`, `waived`), Guard checks, Model `CoreBoundaryViolation` & `CoreBoundaryViolationTransition` (append-only facts), Enum `ViolationCategory`, `ViolationStatus`. |
| **Aplikasi (Action & Service)** | 1.500–2.200 | `TriageViolationAction`, `WaiveViolationAction`, `TransitionViolationAction`, `VerifyViolationAction`, `ExpireWaiversAction`, `BoundariesAnalyzerService` (analisis statis use/DB/Schema berbasis `token_get_all`), integrasi `ApprovalEngine` dan `TaskInboxService::createTask()`. |
| **Kontrak & Migrasi Nyata** | 700–1.100 | `Modules\Core\Contracts\VehicleDirectory`, `VehicleSnapshot`, `VehicleModelResolver`, pembaruan `Modules\Core\Contracts\Vehicle`, serta refaktor pada 14 file di AutoServe, Logistics, Mall, dan Store yang menggantikan import langsung ke `Modules\Core\Domain\Models\Vehicle`. |
| **HTTP (Controller, FormRequest, View)** | 1.000–1.500 | `ModuleMapController`, `ViolationController`, `WaiverController`, `ModuleMapExportController`, FormRequests validasi, dan Blade views berbasis 31 komponen `x-ds.*` (responsif 375px, 0 error `ui:lint`). |
| **Command & Audit Dual-Source** | 700–1.100 | Artisan commands: `arch:boundaries` (--format=table\|json\|md\|svg), `boundary:triage`, `boundary:expire-waivers`, `boundary:audit` (pemeriksaan dual-source: scan vs DB, baseline vs scan, waiver anchor vs DECISIONS, siklus SCC, status closed vs baseline), fixture `BoundaryAuditCorruptionFixture` dan registrasi di `AuditCommandRegistry`. |
| **Migrasi Skema & Seeder** | 200–400 | Migrasi tabel `core_boundary_violations` dan `core_boundary_violation_transitions` dengan indeks unik `(rule_id, file_path, signature)`, serta seeder data T1. |
| **Total Target B1** | **6.000–9.100** | Memenuhi ambang batas keras B1 ≥ 6.000 dengan margin aman ≥ 5%. |

---

## 3. Matriks Keputusan Arsitektur (ADR)
1. **W8-D41 (ADR-0041)**: Peta Dependensi Modul, Directed Acyclic Graph (DAG) & Algoritma Tarjan SCC deterministik.
2. **W8-D42 (ADR-0042)**: Kepemilikan Tabel Basis Data Modular & Aturan Longest Prefix Match.
3. **W8-D43 (ADR-0043)**: Tata Kelola State Machine Boundary Violation (7 Status) & Siklus Hidup Waiver Bertenggat $\le 90$ Hari.
4. **W8-D44 (ADR-0044)**: Migrasi Kontrak Domain Cross-Module Vehicle (AutoServe, Logistics, Mall, Store) ke `VehicleDirectory` dan `VehicleModelResolver`.

---

## 4. Kondisi Awal (Baseline Verification di L.0)
- Commit Master: `17d9bac88ba62b5fdd3169c11ef7a156d6d6917e`
- Aturan `arch:scan`:
  - **A1** (CrossModuleDomainImport): 14 pelanggaran (seluruhnya penggunaan `Modules\Core\Domain\Models\Vehicle` di AutoServe, Logistics, Mall, Store).
  - **A2** (ForeignTablePrefix): 25 pelanggaran (Agri: 3, CloudKitchen: 3, Hcm: 9, Pricing: 5, Vending: 5).
  - **A12** (MissingForeignKey): 300 pelanggaran (terkunci, tidak boleh bertambah).
  - **A9, A10, A11**: 0 pelanggaran.
- Target Penurunan:
  - A1: 14 $\to$ 0 (100% terselesaikan via kontrak nyata).
  - A2: 25 $\to$ 15 (10 pelanggaran di-waive ber-anchor pada Pricing dan Vending; kontribusi waiver $\frac{10}{24} = 41.7\% \le 50\%$).
  - Total A1 + A2: 39 $\to$ 15 (penurunan 61.5%, melampaui syarat $\ge 50\%$).

---

## 5. Kriteria Penerimaan (Acceptance Criteria)
1. **B1 & B3**: Garis logika baru B1 $\ge 6.000$ baris, dokumentasi teknis tulis-tangan B3 $\ge 1.500$ baris.
2. **Anti-Filler**: $M-2 \ge 55\%$, $M-3 \le 20\%$, $M-4 = 0$, $M-5 = 0$.
3. **Ratchet Baseline**: `arch:scan` berjalan bersih dengan baseline baru total $A1+A2 \le 19$ (target 15), `ArchScanBaselineTest` hijau.
4. **State Machine 7 Status**: Seluruh transisi `detected \to triaged \to contract_designed \to migrating \to verified \to closed` dan `triaged \leftrightarrow waived` divalidasi dengan guard ketat dan riwayat fakta append-only.
5. **UI & Ekspor**: Tampilan responsif 375px menggunakan komponen `x-ds.*` bebas galat `ui:lint`, ekspor CSV terverifikasi.
6. **Audit Dual-Source**: `boundary:audit` memvalidasi konsistensi penuh antara scan graf, tabel DB, file baseline, dan DECISIONS.md anchor. Fixture korupsi mendeteksi inkonsistensi secara deterministik.

