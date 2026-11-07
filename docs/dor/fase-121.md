# Definition of Ready (DoR) & Definition of Done (DoD): Fase 121

## 1. Lingkup Modul & Mandat Fase 121

- **Modul**: `Modules\Mining` (Pertambangan & Hilirisasi Mineral)
- **Fase**: `Fase 121` — Minerals Processing, Smelter RKEF & Metals Trading Desk
- **Mandat**: KONSEP.md §12F baris 1211–1235 (Standar Ketat W8)
- **Status Sasaran**: `[🔵]` (Tingkat sertifikasi tertinggi pelaksana, tidak pernah memberi `[✅]`)

---

## 2. Kriteria Kesiapan (Definition of Ready - DoR)

Sebelum implementasi teknis dimulai, seluruh prasyarat arsitektur berikut harus terpenuhi:
1. **Audit Base Commit & Worktree**: Worktree aktif `sw-g7-p4` terisolasi, bersih dari sisa commit fase sebelumnya, dan branch dibuat langsung dari `origin/master`.
2. **Kepatuhan Larangan Modul Terisolasi**: Dilarang menyentuh folder `modules/Venue/**` (zona G7-P5) atau `modules/Integration/**`.
3. **Penyelarasan Arsitektur Double-Entry Ledger**: Akun buku besar standar hilirisasi terdaftar di `MiningAccounts.php` dan tipe transaksi terdaftar di `MiningTransactionTypes.php` dengan panjang string $\le 32$ karakter (Aturan A5).
4. **Penyelarasan Tipe Data Finansial IDR**: Seluruh representasi moneter mata uang rupiah wajib bertipe integer/bigInteger tanpa penggunaan tipe float atau double (Aturan A3).
5. **Skema Relasi Foreign Key**: Seluruh kolom berakhiran `*_id` pada skema migrasi database memiliki relasi foreign key eksplisit `foreignUuid(...)->constrained(...)` (Aturan A12).
6. **Dokumentasi Keputusan Arsitektur**: Keputusan teknis D121-1 s/d D121-8 tercatat di `docs/DECISIONS.md` dan ADR terinci di `docs/decisions/ADR-0121-minerals-processing-smelter-and-metals-trading.md`.

---

## 3. Kriteria Penyelesaian (Definition of Done - DoD)

Implementasi Fase 121 dinyatakan tuntas dan layak diajukan ke Pull Request produksi bila memenuhi seluruh ambang ketat W8:
1. **Ambang Keras Logika B1**: Penambahan baris logika baru di `modules/Mining/**` dan `modules/Shared/Domain/Algorithms/**` mencapai $\ge 6.000$ baris tanpa baris kosong/pindahan.
2. **Ambang Keras Dokumentasi B3**: Penambahan dokumentasi teknis mendalam di `docs/**/*.md` (di luar `CODEBASE.md` dan `PROGRESS.md`) mencapai $\ge 1.500$ baris.
3. **Metrik Kualitas Anti-Isian**:
   - `M-2 (Behavioral Ratio)` $\ge 55\%$.
   - `M-3 (Data-Only Ratio)` $\le 20\%$.
   - `M-4 (Duplication Block)` $= 0$ (tidak ada duplikasi $\ge 10$ baris).
   - `M-5 (Uncalled Public Classes)` $= 0$ (seluruh kelas action dipanggil di Controller, Command, atau Test).
4. **Algoritma Bersertifikat**:
   - `M10 SmelterPyrometallurgicalEnergyBalanceSolver` + Verifier SHA-256.
   - `M11 LmeMetalMarkToMarketValuator` + Verifier SHA-256.
   - `M12 WarehouseReceiptDigitalCollateralHaircutModel` + Verifier SHA-256.
5. **State Machines & Saga**:
   - Minimal 2 state machine dengan $\ge 8$ status (`FurnaceRunStateMachine`, `MetalsTradingContractStateMachine`, `WarehouseReceiptCollateralStateMachine`).
   - 1 Saga lintas modul dengan kompensasi otomatis (`MetalsHilirisasiLifecycleSaga`).
6. **Buku Besar Finansial**: Layanan `MiningFase121LedgerPostingService` dengan 6 jenis pembukuan double-entry akrual dan settlement.
7. **Antarmuka UI & Gateway**:
   - 4 antarmuka Blade responsif mendukung layar mobile 375px (`smelter`, `trading`, `warehouse_receipt`, `byproduct`).
   - 3 gateway integrasi eksternal (`MetalsTradingLmeGateway`, `WarehouseReceiptRegistryGateway`, `ByProductResidueOfftakeGateway`).
8. **Pengujian & Kerapian Kode**:
   - Seeder T1 kaya 16 skenario bernama teruji idempoten 2 kali (`MiningFase121Seeder`).
   - $\le 5$ acceptance test E2E dan unit test vektor algoritma lulus $100\%$.
   - `arch:scan` 100% bersih (0 BARU dan 0 BASI).
   - Pint PSR-12 tuntas tanpa pelanggaran format.
