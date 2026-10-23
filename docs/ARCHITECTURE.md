# Arsitektur Sistem AutoServe

AutoServe adalah platform otomotif terpadu (*superwebsite*) berskala enterprise yang dirancang dengan pola **Modular Monolith** di atas framework Laravel 13 (PHP ^8.4). Arsitektur ini menggabungkan fleksibilitas pengembangan monolit dengan isolasi batas domain yang tegas antar-modul bisnis.

---

## 1. Prinsip Desain & Pola Arsitektur

### 1.1 Modular Monolith
Setiap kapabilitas bisnis dikelompokkan ke dalam modul independen di bawah direktori `modules/{ModulName}`. Modul memiliki siklus hidup, migration, domain model, logic aplikasi, UI view, dan test tersendiri.

```
modules/{Modul}/
├── Application/               # Use cases, Action classes, Domain services, Event listeners
├── Contracts/                 # Interface publik untuk konsumsi lintas modul
├── Console/                   # Artisan commands terjadwal / operasional
├── Domain/
│   ├── Enums/                 # PHP 8.4 Backed Enums untuk state & tipe
│   ├── Events/                # Domain events untuk integrasi asinkron / loose-coupling
│   └── Models/                # Eloquent models dengan business rules terenkapsulasi
├── Http/Controllers/          # HTTP request handlers & view presenters
├── database/
│   ├── migrations/            # Migrasi database dengan prefix tabel per modul
│   └── seeders/               # Data awal modul
├── resources/views/           # Blade templates (namespaced: {modul}::...)
├── routes/web.php             # Deklarasi rute HTTP web & API internal
├── tests/Feature/             # Pest / PHPUnit test spesifik modul
└── {Modul}ServiceProvider.php # Registrasi service, contract binding, view namespace
```

### 1.2 Batasan Modul & Aturan Dependensi (Module Boundaries)
Untuk mencegah *tight coupling* ("spaghetti monolith"):
1. **Tidak Ada Akses DB Facade Langsung di Controller**: Controller hanya mendelegasikan request ke Action/Service atau memanggil Query Builder via Model Eloquent.
2. **Domain Layer Bebas dari HTTP**: Layer Domain dan Application tidak boleh mengimpor kelas dari namespace `Illuminate\Http` atau controller.
3. **Isolasi Domain Antar-Modul**:
   - Modul `AutoServe` tidak boleh mengimpor namespace Domain dari `AutoDex`, dan sebaliknya.
   - Komunikasi antar-modul **wajib** melalui:
     - **Contracts / Interfaces** (contoh: `AcquiresVehicle`, `TransfersVehicleOwnership`).
     - **Domain Events** (contoh: `PricesTicked` didengar oleh `MonitorLoanRisk`).
     - **Payment / Ledger Services** yang terdaftar di container.
4. **Verifikasi Arsitektur Otomatis**: aturan di atas diperiksa `php artisan arch:scan` (aturan A1 import Domain lintas modul, A2 tabel berprefiks modul lain, A9 kernel bergantung pada modul bisnis; lihat §1.3) dan `tests/Architecture/ModuleBoundariesTest.php`. Pelanggaran yang sudah ada per 10 Okt 2026 tercatat sebagai baseline yang hanya boleh turun (`KNOWLEDGE.md` K-04/K-05). Artinya aturan ini **belum** dipatuhi seluruh kode lama, tetapi kode baru yang melanggar langsung menggagalkan gate.

### 1.3 Pagar Otomatis & Quality Gate (`app/Quality`)

Sejak Fase R0, aturan proses di `PROGRESS.md` (P1–P13) dan aturan desain di `KONSEP.md` §A14 ditegakkan oleh kode, bukan hanya oleh dokumen. Komponennya berada di `app/Quality/` (tooling lintas modul, tanpa tabel) dan diuji di `tests/Architecture/`.

**Pola ratchet.** Pelanggaran lama dicatat di `tests/Architecture/baselines/*.json`:
- `arch:scan` dan TestHygiene memakai sidik jari per aturan → file → tanda tangan pelanggaran.
- Detektor lain memakai himpunan entri.

Test gagal bila ada entri **baru** (perbaiki kodenya) atau entri yang **sudah hilang** (turunkan baseline di commit yang sama). Entri baru hanya bisa masuk lewat `approved_additions` yang menunjuk anchor `docs/DECISIONS.md` yang benar-benar ada (diperiksa `app/Quality/Docs/DecisionLog`). Cara memperbarui baseline: `RUNBOOK.md` §6.1.

| Pagar | Menolak | Komponen | Dijalankan oleh |
|---|---|---|---|
| `arch:scan` (A1–A13) | import Domain/tabel lintas modul, float uang, idempotency key acak, type transaksi liar, parameter bool kontrol, hash tanpa kunci, `setTestNow` di produksi, kernel → modul bisnis, `$guarded = []`, tanpa `strict_types`, `*_id` tanpa FK, `back()->errors()` | `ArchScan/` (tokenizer PHP bawaan), `Modules/ModuleRegistry` + `config/modules.php` (registry prefiks tabel) | `ArchScanBaselineTest`, `php artisan arch:scan` |
| Integritas PROGRESS | centang tanpa Bukti valid, commit yang tidak menyentuh file bukti, rute tanpa role, ✅ tanpa Verifikasi, minus P0/P1 terbuka saat ✅, teks item diubah tanpa ⬇️ | `Progress/` (parser, `GitCommitInspector`), snapshot `progress-snapshot-8c8369d.json` | `ProgressIntegrityTest` |
| Matriks otorisasi rute | rute tanpa entri di `tests/Architecture/route-roles.php`, proteksi rute melemah, role tak berhak tidak 403 | `Routing/RouteAuthorizationScanner` | `RouteAuthorizationMatrixTest` |
| Kontrak audit | `*:audit`/`*:reconcile`/`verify-*` tanpa fixture korupsi (bersih → exit 0, rusak → exit ≠ 0) | `Audit/` (`AuditCommandRegistry`, `Fixtures/`) | `AuditCommandContractTest` |
| Ledger | kode akun produksi tanpa provisi seeder; saldo berlawanan sisi normal | `Ledger/` | `LedgerAccountRegistryTest`, `LedgerNormalBalanceTest` |
| Pembekuan Integration | file/tabel baru di `modules/Integration` di luar allowlist adapter | `Freeze/IntegrationFreeze` | `IntegrationFreezeTest` |
| Higiene test (T1–T4) | assertion yang tidak bisa gagal, `LedgerAccount::create` di test, skip tanpa rujukan BLOCKERS, modul tanpa test HTTP | `TestHygiene/` | `TestHygieneTest` |
| Portabilitas | path kelas ≠ namespace (case-sensitive); identifier > 64; DDL yang ditolak MySQL 8.4 | `Autoload/Psr4ComplianceChecker`, `Database/MysqlDdlReplay` + `MysqlSchemaChecker` | `Psr4ComplianceTest`, `SchemaIdentifierLengthTest`, `MysqlSchemaCompatibilityTest`, job CI MySQL |
| Nama command unik | dua kelas mendaftarkan signature yang sama (kasus `api:audit`) | `Commands/CommandNameCollector` | `CommandSignatureUniqueTest` |
| Kejujuran dokumen | versi Laravel/PHP yang diklaim ≠ `composer.lock`, akun fiktif di CODEBASE | — | `DocsVersionConsistencyTest` |
| Kontrak CI & repo | job CI dihapus/dilemahkan, CODEOWNERS hilang, template PR tanpa V1–V12/C1–C14 | — | `CiWorkflowContractTest`, `CodeownersContractTest`, `GateTemplateContractTest`, `GateCommandContractTest`, `MutationTestingContractTest` |

**Quality gate.**
- `composer gate` → `php artisan gate:run` (`Gate/GateRunner`) menjalankan sembilan langkah P4 dan menulis manifest (commit, status dirty, exit code dan durasi tiap langkah). `migrate:fresh --seed` dan audit memakai database SQLite khusus gate.
- `php artisan gate:report --fase=N` (`Gate/GateReportGenerator`, `JUnitParser`) menulis `docs/gates/fase-N.md` hanya dari manifest dan JUnit asli. Laporan ditolak bila gate gagal, ada langkah hilang, commit ≠ HEAD, tree kotor, JUnit tidak hijau, atau test Bukti tidak lulus.
- `php artisan test:mutate` (`Mutation/MutationTargetResolver`) menjalankan mutation testing Pest pada kelas Action/Service yang diubah, dengan ambang 60%.
- Ketiganya dijalankan CI (`.github/workflows/ci.yml`): job SQLite (gate penuh), job MySQL 8.4 (migrasi, seeder, `@group db-portability`), dan job mutation (pull request).

File baseline, `route-roles.php`, workflow CI, `PROGRESS.md`, `DECISIONS.md`, `docs/gates/`, dan `app/Quality/` dimiliki pemilik lewat `.github/CODEOWNERS`.

---

## 2. Peta Modul

| Modul | Prefix Tabel | Tanggung Jawab Utama |
|---|---|---|
| **Core** | `core_` | Registri kendaraan terpadu, paspor digital hash-chain, notifikasi sistem, activity feed, dashboard terintegrasi. |
| **Banking** | `bank_` | Double-entry ledger multi-aset, akun internal/eksternal, dompet digital IDR & Kripto, transfer P2P, PIN keamanan. |
| **Payment** | `pay_` | Payment Hub sentral, orchestrator *charge*, *hold*, *capture*, *release*, dan *refund* berbasis kontrak `Payable`. |
| **Inventory** | `inv_` | Pelacak mutasi stok terpusat (`StockMovement`), reservasi stok atomik saat checkout/servis. |
| **Store** | `store_` | Katalog produk sparepart/aksesoris, checkout keranjang belanja, bursa jual-beli mobil bekas C2C dengan escrow. |
| **AutoServe** | `serve_` | Manajemen operasional bengkel: pendaftaran servis, estimasi biaya, alokasi mekanik, invoice & backorder part. |
| **AutoDex** | `dex_` | Ensiklopedia otomotif global, garasi virtual (*My Garage*), dan wishlist kendaraan. |
| **Crypto** | `crypto_` | Simulasi bursa kripto (BTC, ETH, SOL), engine kuotasi harga dengan time-lock 15 detik, trading fee, dan price alerts. |
| **Finance** | `fin_` | Program *HODL-to-Drive* (pembiayaan kendaraan beragun kripto), jadwal cicilan flat, scheduler denda, LTV risk monitor & likuidasi. |
| **Resto** | `resto_` | Jaringan resto Padang "RM Sari Ranah": multi-outlet, dapur sentral, menu, resep berlapis BOM, kalkulasi HPP, batch dapur, etalase hidang, POS shift, rantai pasok. |
| **Mall** | `mall_` | Pengelolaan "Duta Mall": leasing unit, tagihan sewa & utilitas, revenue sharing tenant, parkir terintegrasi, poin loyalty PTS, voucher mall, fasilitas. |
| **Shared** | - | Value objects (`Money`), base classes, komponen Blade seragam, Menu Registry global. |
| **Logistics** | `lgx_` | Sari Ranah Express: multi-modal routing, fleet & driver dispatch, hub scanning, LCL consolidation, customs clearance, live tracking. |
| **Party** | `pty_` | Registri pihak tunggal (perorangan / badan usaha), relasi entitas legal, KYC, verifikasi sanksi, profil kredit. |
| **Asset** | `ast_` | Register aset tetap grup (PSAK 16/73), depresiasi komersial/fiskal, revaluasi, work order pemeliharaan, asuransi, leasing. |
| **Supplier** | `sup_` | Master data pemasok/produsen, sertifikasi halal/ISO, matriks tiering harga, audit kualifikasi, supplier scorecard & risk scanning. |
| **Contract** | `ctr_` | Manajemen siklus kontrak korporat, templat klausul, approval berjenjang, e-sign simulasi, hash-chain append-only, jadwal termin. |
| **Procurement** | `prc_` | Siklus PR → RFQ → Tender → PO, budget encumbrance, receiving report GRN, 3-way match, landed cost allocation. |
| **Manufacturing** | `mfg_` | Master pabrik, BOM multi-level, routing, formula hash-chain, perencanaan MRP/MPS, shop floor tracking, costing WIP, QMS, OEE & HSE. |
| **WMS** | `wms_` | Gudang multi-zona, rak & bin, bin stock, task picking & putaway ber-wave, cross-docking, cycle count four-eyes, packing list. |
| **Distribution** | `dist_` | Jaringan distribusi/agen, teritori eksklusif, limit kredit AR, ATP reservation, faktur pajak seri resmi, konsinyasi, program rebate. |
| **Pricing** | `pric_` | Engine harga deterministik, waterfall diskon, price list wilayah/segmen, margin floor policy, immutable price lock. |
| **Agency** | `agy_` | Tata kelola agen penjualan, skema komisi multi-tier ber-override upline, hold masa retur, clawback, payout ber-PPh 21/23. |
| **Partner** | `ptn_` | Ekosistem kemitraan strategis, due diligence, Joint Business Plan (JBP), revenue share multi-party, direktori co-selling. |
| **Treasury** | `trs_` | Perbendaharaan multi-valas, kurs versi immutable scaled 1e6, revaluasi kurs periodik, cash pool sweeping, cash forecast 13 minggu. |
| **Trade** | `trd_` | Tata niaga lintas batas, kepatuhan Incoterms 2020, HS Code landed cost, kepabeanan PEB/PIB, pelacakan kargo perbatasan. |
| **Trade Finance** | `tf_` | Instrumen SCF internasional, Letter of Credit UCP 600, diskrepansi dokumen ekspor-impor, penagihan D/P-D/A, bank garansi tender. |
| **International** | `intl_` | Aliansi global PMA, joint venture equity/contractual, lisensi teknologi internasional & MAG royalti, tax treaty P3B. |
| **Intercompany** | `ic_` | Konsolidasi grup multi-entitas, transaksi cermin SO-PO/AR-AP, transfer pricing wajar (CUP/CPM/RPM/TNMM), eliminasi saldo resiprokal. |
| **Control Tower** | `sct_` | Menara kendali rantai pasok multi-eselon ABC/XYZ, demand forecasting S&OP, alokasi janji ATP/CTP, radar peringatan gangguan rantai pasok. |
| **Enterprise Finance** | `ef_` | Kontrol pagu anggaran unit bisnis (hard-stop/soft-stop), kalender kepatuhan pajak grup, matriks pemisahan tugas (SoD) anti-fraud. |
| **Integration** | `intg_` | B2B REST API v2 & EDI gateway (EDIFACT / ANSI X12), webhook publisher HMAC-SHA256, tiered rate limiting. |
| **HCM** | `hcm_` | Human Capital Management, master pegawai PKWT/PKWTT, payroll engine otomatis PPh 21 TER & BPJS, alokasi jam kerja ke SPK pabrik. |
| **PLM** | `plm_` | Product Lifecycle Management, pipeline riset Stage-Gate, konversi EBOM ke MBOM resep pabrik, ECO berantai hash SHA-256, ELN lab. |
| **ESG** | `esg_` | Pengukuran emisi GRK GHG Protocol Scope 1-3, bursa karbon IDX Carbon / Verra, offset retirement net-zero, ESG supplier scorecard GRI. |
| **B2B** | `b2b_` | Marketplace grosir tertutup, alur negosiasi RFQ komersial, balai lelang digital aset surplus & mesin pabrik anti-sniping, escrow akun. |
| **EPC** | `epc_` | Rekayasa konstruksi proyek properti & pabrik, hierarki WBS bobot 100%, kurva-S, Monthly Certificate MC retensi 5%, kapitalisasi CIP ke Aset. |
| **Hospital** | `hosp_` | Layanan kesehatan & farmasi, EMR paspor pasien hash-chain, ketersediaan bed rawat inap & ICU, contraindication engine, BPJS/asuransi billing. |
| **Venue** | `ven_` | Entertainment & beach club, ticketing non-fungible hash-chain, access control RFID/QR gate, zone crowd safety, VIP table escrow, festival bundle. |
| **Hotel** | `htl_` | Hospitality PMS, alokasi kamar anti-oversell, dynamic rate protection, smart lock keyless QR, HVAC energy twin setback, timeshare yield. |
| **Mining** | `min_` | Pertambangan & alat berat, mine planning, fleet dispatch, weighbridge digital hash-chain, akrual royalti PNBP, HSE work permit. |
| **Energy** | `egy_` | Pembangkit EBT/Fosil, transmisi SCADA, automated grid dispatch, microgrid islanding, SPKLU smart metering, sertifikat REC hash-chain. |
| **Telecom** | `tlx_` | Jaringan ISP & fiber optic, NOC monitoring, tower sharing, data center rack leasing & PUE cooling twin, RADIUS bandwidth throttling. |
| **Media** | `med_` | Stage-gate produksi film/musik, DRM watermark tamper-evident, distribusi SVOD/broadcast, royalti kreator, sponsorship escrow. |
| **Education** | `edu_` | Student cohort class, grading engine, sertifikasi kompetensi digital hash-chain, akreditasi kurikulum, tuition split & scholarship fee. |
| **Retail** | `ret_` | Omnichannel OMS, inventory allocation, dark store picking wave, q-commerce ultra-fast dispatch, dynamic loyalty sync. |
| **CloudKitchen** | `ck_` | Dapur satelit terdistribusi, katering industri, meal plan HCM, pembagian pesanan multi-brand dan integrasi POS. |
| **Ev** | `ev_` | Ekosistem kendaraan listrik, stasiun pengisian SPKLU, monitoring sesi daya kWh, dynamic pricing tarif beban puncak. |
| **Fleet** | `flt_` | Manajemen armada korporat, pemeliharaan preventif berbasis kilometer, monitoring utilisasi aset kendaraan grup. |
| **Insurance** | `ins_` | InsurTech terpadu, polis parametrik otomatis, underwriting risiko multi-vektor, penyelesaian klaim autopilot tanpa berkas manual. |
| **Proptech** | `prp_` | Manajemen gedung pintar IoT, model BIM digital twin, kapitalisasi konstruksi dan pemesanan flex-space anti-overlap. |
| **Rwa** | `rwa_` | Tokenisasi Real World Asset, alokasi dividen pro-rata integer minor unit, pengamanan deviasi rounding reserve. |
| **Telematics** | `tlm_` | Ingestion data sensor IoT kendaraan, CAN-bus OBD-II telemetri real-time, deteksi kode diagnostik kerusakan (DTC) dan skor pengemudi. |
| **Vending** | `vnd_` | Jaringan mesin penjual pintar otomatis, pembayaran dompet QRIS instan, pemantauan stok rak nirkabel dan auto-replenishment. |
| **Wealth** | `wm_` | Wealth management terpadu, profil risiko investor, portofolio robo-advisor otomatis, guardrail dana darurat. |

---

## 3. Sistem Inti & Invarian Data

### 3.1 Double-Entry Ledger (Banking Module)
Seluruh pergerakan nilai moneter dan aset digital dicatat secara berpasangan dalam buku besar akuntansi:
- **Aturan Fundamental**: Setiap transaksi terdiri atas minimal 2 entri jurnal dengan mata uang/aset yang sama. Jumlah total debit dan kredit pada setiap transaksi harus tepat seimbang:
  $$\sum \text{amount} = 0$$
- **Multi-Asset Uniformity**: Mendukung IDR (fiat) dan koin kripto (BTC, ETH, SOL, BNB, USDT) menggunakan presisi `decimal(36,18)`.
- **Lock Ordering Anti-Deadlock**: `LedgerService` mengurutkan ID akun yang terlibat secara ascending (`orderBy('id', 'asc')->lockForUpdate()`) sebelum mengunci baris di database.
- **Audit Rutin**: Perintah `php artisan bank:reconcile` memvalidasi bahwa `SUM(entries) == 0` global untuk semua aset dan saldo cache setiap akun sama persis dengan agregat historis entri jurnal.

### 3.2 Vehicle Passport Hash-Chain (Core Module)
Setiap kendaraan (`core_vehicles`) memiliki paspor riwayat digital yang anti-pemalsuan:
- **Algoritma**: SHA-256 Hash Chain berantai:
  $$H_i = \text{SHA256}(H_{i-1} \,\|\, \text{event\_type} \,\|\, \text{payload\_json} \,\|\, \text{created\_at})$$
  Blok pertama (*Genesis Block*) memiliki `previous_hash = 0000000000000000000000000000000000000000000000000000000000000000`.
- **Append-Only & Immutability**: Model Eloquent `VehicleEvent` membatasi operasi `update` dan `delete` via exception.
- **Integritas Publik**: Paspor dapat dibagikan secara publik menggunakan Laravel Signed URLs yang kedaluwarsa.
- **Audit Otomatis**: Perintah `php artisan core:verify-passports` memindai seluruh blok rantai dan memverifikasi keutuhan hash.

### 3.3 Centralized Payment Hub (Payment Module)
Modul Payment bertindak sebagai gerbang pembayaran modular yang mengisolasi urusan kasir dari modul bisnis:
- **Model Interface `Payable`**: Setiap entitas yang dapat ditagih (Order Toko, Invoice Bengkel, Order C2C) mengimplementasikan `Payable`:
  - `payableAmount()`: Jumlah tagihan bersih.
  - `revenueSplits()`: Pemetaan pembagian dana ke akun pendapatan/seller.
- **Siklus Hidup Pembayaran (Two-Phase)**:
  - *Direct Charge*: Pengurangan saldo seketika untuk pembelian langsung.
  - *Two-Phase (Hold & Capture)*:
    1. `hold()`: Menahan dana pembeli ke akun escrow sistem (`escrow:payment:IDR`).
    2. `capture()`: Menyalurkan dana ke penerima dan mengembalikan kelebihan dana (*remainder*) ke pembeli secara otomatis jika biaya akhir lebih kecil dari estimasi.
    3. `release()`: Membatalkan hold dan mengembalikan seluruh dana ke pembeli jika transaksi dibatalkan atau kedaluwarsa.

### 3.4 Inventory Movements & Atomisitas Stok (Inventory Module)
- **Zero Phantom Stock**: Stok produk dihitung melalui rekonsiliasi tabel append-only `inv_stock_movements`.
- **Reservasi Dua Langkah**:
  1. Saat checkout keranjang atau konfirmasi estimasi bengkel, stok direservasi (`RESERVATION`, pergerakan kuantitas negatif pada stok tersedia).
  2. Saat pembayaran sukses, alasan mutasi diubah menjadi penjualan pasti (`SALE`).
  3. Jika checkout gagal atau pesanan kedaluwarsa, mutasi pembatalan dicatat (`RESERVATION_RELEASE`).

### 3.5 Pembiayaan Kripto HODL-to-Drive (Finance Module)
- **Kolateral Kripto**: Peminjam mengunci aset kripto di dompet kolateral sistem (`escrow:finance:collateral:{ASSET}`).
- **Pencairan & Piutang**: Sistem mencairkan dana melalui akun piutang bersaldo negatif (`loan_receivable:IDR`), yang mencerminkan outstanding pokok pinjaman.
- **Risk Engine Berbasis Event**: Setiap kali harga kripto berfluktuasi (`PricesTicked`), risk engine mengevaluasi rasio LTV (*Loan-to-Value*):
  $$\text{LTV} = \frac{\text{Sisa Pokok}}{\text{Nilai Pasar Kolateral}} \times 100\%$$
  - $\text{LTV} \ge 80\%$: Memicu status `margin_call` dan mengirim peringatan ke pengguna.
  - $\text{LTV} \ge 90\%$: Memicu likuidasi otomatis; kolateral dijual, sisa pokok ditutup, dan sisa dana dikembalikan ke pengguna.

---

## 4. Keamanan & Proteksi Transaksi

1. **Proteksi PIN Transaksi**: Aksi berisiko tinggi (transfer saldo, konfirmasi checkout, eksekusi trading, pengajuan pinjaman) dilindungi verifikasi PIN 6-digit dengan mekanisme penguncian otomatis (maksimal 5 kesalahan berturut-turut memicu penguncian akun selama 15 menit).
2. **Role-Based Access Control (RBAC)**: Pemisahan peran menggunakan field `role` (`admin`, `mechanic`, `customer`) dan route middleware dedicated (`auth`, `role:admin`, `role:mechanic`).
3. **Pessimistic Concurrency**: Penggunaan `lockForUpdate()` pada record kunci (stok barang, saldo dompet, baris kendaraan C2C) untuk menangkal bahaya *race condition* dan *double-spending*.

---

## 5. Layanan Platform & Dashboard Terpadu (Fase 6)

- **Notification Service**: Penyimpanan notifikasi in-app pada database lokal dengan badge unread counter real-time dan modal dropdown di header navigasi.
- **Activity Logger**: Pencatatan riwayat aktivitas pengguna (servis, belanja, transfer, trading, cicilan) lintas modul dengan antarmuka feed terpadu.
- **Role-Tailored Dashboards**:
  - `Customer Dashboard`: Ringkasan saldo dompet, portofolio kripto, kendaraan di garasi, servis aktif, pinjaman berjalan, dan pesanan terbaru.
  - `Admin Dashboard`: Statistik pendapatan harian, total omzet bengkel & toko, metrik LTV portofolio pinjaman, antrean pesanan pending, dan peringatan sistem.
  - `Mechanic Dashboard`: Daftar antrean kendaraan bengkel, status servis aktif yang ditugaskan, pencatatan sparepart terpakai, dan riwayat pekerjaan selesai.

---

## 6. Modul Resto (RM Sari Ranah)

### 6.1 ERD Fondasi Menu, Bahan & Resep
```mermaid
erDiagram
    resto_outlets ||--o{ resto_staff_assignments : employs
    resto_outlets ||--o{ resto_ingredient_costs : tracks
    resto_outlets ||--o{ resto_menu_item_outlet : overrides
    resto_ingredients ||--o{ resto_unit_conversions : defines
    resto_ingredients ||--o{ resto_ingredient_costs : priced_at
    resto_menu_categories ||--o{ resto_menu_items : classifies
    resto_menu_items ||--o| resto_recipes : defines
    resto_recipes ||--o{ resto_recipe_lines : contains
    resto_ingredients ||--o{ resto_recipe_lines : used_in
    resto_recipes ||--o{ resto_recipe_lines : sub_recipe_in
```

### 6.2 Resep Berlapis (BOM) & Kalkulasi HPP Rekursif
- **Bumbu Dasar Sebagai Sub-Resep**: Bumbu dasar (misal Bumbu Dasar Merah Padang, Bumbu Gulai Minang) didefinisikan sebagai `Recipe` dengan `sub_recipe_name` tanpa `menu_item_id`.
- **Kalkulasi Rekursif**: `RecipeCostCalculator` menelusuri setiap baris resep. Jika baris merujuk ke sub-resep, biaya dihitung secara rekursif berdasarkan rasio yield, moving average cost bahan di outlet terkait, dan persentase waste (`waste_percent`).
- **Pencegahan Resep Sirkular**: Menggunakan penelusuran `$visitedRecipeIds`. Jika ditemukan dependensi siklis (A $\to$ B $\to$ A), sistem melempar `RecipeCycleDetected` exception.
- **Peringatan Batas Margin**:
  - `HPP > Harga Jual`: Peringatan bahaya (Rugi per porsi).
  - `Margin < 30%`: Peringatan margin rendah karena risiko biaya operasional dan waste etalase.

### 6.3 Akun Sistem Ledger Resto
| Kode Akun | Jenis (`kind`) | Sifat Saldo | Keterangan |
|---|---|---|---|
| `cash:drawer:{outlet}:IDR` | `cash` | Debet (+) | Uang fisik di laci kasir per outlet |
| `revenue:resto:{outlet}:food:IDR` | `revenue` | Kredit (+) | Omzet penjualan makanan Padang |
| `revenue:resto:{outlet}:beverage:IDR` | `revenue` | Kredit (+) | Omzet penjualan minuman |
| `revenue:resto:{outlet}:catering:IDR` | `revenue` | Kredit (+) | Omzet pesanan katering & nasi kotak |
| `inventory:resto:{outlet}:IDR` | `inventory` | Debet (+) | Nilai persediaan bahan & barang jadi |
| `expense:resto:cogs:IDR` | `expense` | Debet (+) | Harga Pokok Penjualan saat porsi terjual |
| `expense:resto:waste:IDR` | `expense` | Debet (+) | Beban makanan etalase kedaluwarsa / rusak |
| `expense:resto:cash_variance:IDR` | `expense` | Allow Negative | Selisih lebih/kurang kas saat tutup shift |
| `ap:supplier:{id}:IDR` | `ap` | Allow Negative | Utang dagang ke supplier bahan baku |
| `revenue:group:royalty:IDR` | `revenue` | Kredit (+) | Pendapatan royalti franchise grup |

### 6.4 Dapur, Batch Produksi & Siklus Etalase Hidang (Fase 8)
- **Pelacakan Stok Bahan & InventoryService**: Modul `Inventory` diperluas dengan metode decimal presisi `availableIngredient`, `deductIngredient`, `addIngredient`, dan `adjustIngredient`. Pergerakan dicatat di tabel `resto_ingredient_movements` dan saldo per outlet disimpan di `resto_ingredient_stocks`.
- **Alur Batch Produksi (`CookBatchAction`)**:
  1. Penelusuran resep rekursif untuk menghitung kebutuhan total bahan baku dasar (memperhitungkan `waste_percent`).
  2. Pengecekan ketersediaan stok tiap bahan. Jika ada kekurangan, sistem melempar `ShortageException` disertai daftar bahan yang kurang dan kalkulasi porsi maksimum yang dapat dimasak (`suggestedMaxPortions`).
  3. Pemotongan stok bahan baku melalui `InventoryService` dengan alasan `production`.
  4. Pencatatan pemakaian riil di `resto_batch_consumptions`.
  5. Posting double-entry ledger: internal transfer nilai dari bahan mentah ke barang jadi pada akun `inventory:resto:{outlet}:IDR` (debit senilai biaya batch, kredit senilai biaya batch). Saldo total persediaan outlet tetap seimbang dan global IDR sum = 0.
  6. Penempatan piring ke etalase hidang (`resto_display_trays`) dengan batas kedaluwarsa 6 jam.
- **Siklus Etalase Hidang Padang (`DisplayTray`)**:
  - `MAX_RECIRCULATION = 3`: Piring hidang yang dibawa ke meja dan **tidak disentuh** boleh kembali ke etalase maksimal 3 kali.
  - `MAX_DISPLAY_HOURS = 6`: Piring di etalase yang telah melewati batas 6 jam sejak dimasak tidak boleh dihidangkan lagi.
  - Piring yang disentuh sebagian dihitung **terjual penuh** (aturan rumah makan Padang) dan tidak boleh kembali ke etalase.
  - Resirkulasi ke-4 atau piring melewati batas waktu otomatis dialihkan ke status `discarded`.
- **Manajemen Limbah (Waste)**:
  - Command terjadwal `resto:expire-display` (tiap 15 menit) memindai piring kedaluwarsa.
  - Pembuangan piring mencatat kerugian HPP porsi tersisa ke ledger: Debet `expense:resto:waste:IDR`, Kredit `inventory:resto:{outlet}:IDR`.

### 6.5 POS Hidang, Sesi Meja, Shift Kasir & Tutup Harian (Fase 9)
- **Siklus Sesi Meja & Hidang**:
  - `resto_tables`: kode meja, jumlah kursi, zona (`indoor`, `outdoor`, `lesehan`, `vip`), status (`available`, `occupied`, `reserved`, `cleaning`).
  - `resto_table_sessions`: sesi tamu aktif per meja (`open`, `closing`, `closed`, `abandoned`).
  - `resto_order_items`: item yang disajikan memiliki snapshot harga & status konsumsi (`presented`, `consumed`, `returned`).
  - Piring hidang yang disajikan (`source = hidang`) berstatus `presented` dan belum menambah subtotal sampai diverifikasi pada layar *Hitung Hidangan*.
  - Item yang disentuh (`consumed`) dihitung harga penuh (aturan hidang Minang), memotong porsi tray, dan memposting HPP ke `expense:resto:cogs:IDR` vs `inventory:resto:{outlet}:IDR`.
  - Item utuh (`returned`) dikembalikan ke etalase via `RecirculateTrayAction` dengan resirkulasi +1.
  - PB1 10% dihitung dari subtotal setelah diskon; pembulatan dilakukan ke kelipatan Rp 100 terdekat dengan selisih pembulatan dicatat di field `rounding`.
- **Manajemen Kas & Shift Kasir**:
  - Kasir wajib memiliki shift berstatus `open` sebelum dapat memproses transaksi tunai. Satu kasir hanya boleh memiliki 1 shift aktif pada satu waktu.
  - Saat tutup shift (`CloseShiftAction`), kasir menginput hitungan fisik kas (`counted_cash`). Sistem membandingkan dengan `expected_cash = opening_float + cash_sales`.
  - Selisih kas (`variance = counted - expected`) diposting ke ledger: `expense:resto:cash_variance:IDR` vs `cash:drawer:{outlet}:IDR`.
  - Setoran uang tunai dari laci kasir ke rekening bank dicatat melalui `SettleCashAction` (`cash:drawer` $\to$ `clearing:external:IDR`).
- **Otorisasi Pembatalan (VOID)**:
  - Pembatalan pesanan (VOID) hanya dapat dilakukan oleh role `admin` atau `outlet_manager` dengan alasan wajib.
  - Jika pesanan sudah dibayar tunai, pembukuan dibalik melalui posting ledger `TransactionType::REFUND`. Jika dibayar via dompet digital (wallet), pengembalian dana diproses melalui `PaymentGateway::refund`.
- **Penutupan Harian (`resto:close-day`) & Audit Ledger**:
  - Berjalan otomatis tiap 23:59 atau secara manual.
  - Membuang sisa piring etalase ke waste, menutup shift kasir yang lupa ditutup, dan mengagregasi data ke tabel `resto_daily_summaries`.
  - Flag `--check` memvalidasi angka ringkasan harian terhadap akumulasi transaksi riil di buku besar (ledger) outlet tersebut.
  - Seluruh laporan & dashboard membaca tabel ringkasan `resto_daily_summaries` untuk menjaga efisiensi query.



---

## MODUL MALL — PARKIR, GATE, MEMBER & FOOTFALL (FASE 14)

### ERD Parkir

```mermaid
erDiagram
    mall_properties ||--o{ mall_parking_zones : "punya zona"
    mall_properties ||--o{ mall_parking_tariffs : "tarif per jenis kendaraan"
    mall_properties ||--o{ mall_parking_members : "langganan bulanan"
    mall_properties ||--o{ mall_footfall_counts : "penghitung pengunjung"
    mall_parking_zones ||--o{ mall_parking_sessions : "menampung sesi"
    mall_parking_members ||--o{ mall_parking_sessions : "sesi bebas biaya"
    mall_tenants ||--o{ mall_parking_sessions : "validasi belanja"
    mall_invoices ||--o{ mall_parking_sessions : "menagihkan validasi"
    core_vehicles ||--o{ mall_parking_members : "kendaraan My Garage"
    core_vehicles ||--o{ mall_parking_sessions : "plat dikenali"
    users ||--o{ mall_parking_sessions : "pembayar dompet"
```

### Tabel

| Tabel | Peran |
|---|---|
| `mall_parking_zones` | Zona parkir per jenis kendaraan dengan `total_capacity` dan `current_occupancy` |
| `mall_parking_tariffs` | Tarif progresif: `grace_period_minutes`, `first_hour_rate`, `subsequent_hour_rate`, `max_daily_rate`, `lost_ticket_penalty` |
| `mall_parking_sessions` | Satu sesi masuk–keluar, termasuk rincian tarif, validasi tenant, dan metode bayar |
| `mall_parking_members` | Langganan bulanan per plat, terhubung ke `core_vehicles`, dengan `auto_renew` |
| `mall_footfall_counts` | Kunjungan per properti per tanggal per jam per gate (unik pada kombinasi tersebut) |

Indeks `mall_parking_validation_billing_idx` pada `(validated_by_tenant_id, validation_invoice_id, exit_time)` dipakai agar penagihan validasi bulanan tidak memindai seluruh tabel sesi.

### Aturan tarif

1. Durasi dibulatkan ke jam penuh berikutnya: 61 menit ditagih 2 jam.
2. Di bawah masa tenggang (15 menit) bebas biaya.
3. Tarif satu siklus 24 jam dibatasi `max_daily_rate`; parkir 12 jam mobil = min(38.000, 30.000) = **30.000**.
4. Member aktif bebas biaya; setelah kedaluwarsa tarif normal berlaku kembali.
5. Tiket hilang menambah denda flat dan tidak bisa dibebaskan oleh status member.

### Validasi parkir oleh tenant

Tenant menanggung N jam pertama bila pelanggan berbelanja minimal X, keduanya parameter pada kontrak sewa (`parking_validation_hours`, `parking_validation_min_spend`).

- Yang disimpan saat validasi adalah **jumlah jam**, bukan rupiah, karena durasi akhir baru diketahui di gate keluar.
- Nominal potongan dihitung di gate keluar oleh `ParkingTariffCalculator::discountForFreeHours()`.
- Nominal itu **tidak hilang** dari pendapatan mall: pada tagihan bulanan tenant muncul baris `parking_validation` yang dikreditkan ke `revenue:mall:parking:IDR`.
- `validation_invoice_id` menandai sesi yang sudah ditagih; sesi yang belum tertagih otomatis ikut siklus berikutnya.

### Contracts

| Contract | Implementor | Dipakai oleh |
|---|---|---|
| `Modules\Mall\Contracts\ParkingValidator` | `ValidateParkingAction` | POS Resto (Fase 16.2), portal tenant |
| `Modules\Mall\Contracts\TenantSalesProvider` | Resto & AutoServe (Fase 16.1) | `TenantSalesService` saat menerbitkan tagihan |

`ParkingValidationResult` adalah DTO readonly di namespace `Contracts` supaya modul lain tidak perlu menyentuh `Mall\Domain`.

### Akun sistem parkir

| Kode akun | Kind | allow_negative | Konvensi |
|---|---|---|---|
| `revenue:mall:parking:IDR` | REVENUE | tidak | Dikreditkan positif saat tarif diterima |
| `cash:mall:parking:IDR` | CASH | ya | Dicatat negatif saat menerima tunai, sama seperti `cash:drawer:{outlet}:IDR` |
| `revenue:mall:membership:IDR` | REVENUE | tidak | Pendapatan langganan parkir bulanan |

### Command terjadwal

| Command | Jadwal | Fungsi |
|---|---|---|
| `mall:renew-parking-members` | harian 06:00 | Pengingat H-3 lalu debit perpanjangan otomatis; saldo kurang → kedaluwarsa |
| `mall:simulate-footfall` | manual | Mengisi data kunjungan (pola ramai akhir pekan & jam 17–21) untuk analitik dan demo |

### Catatan perbandingan tanggal

Cast `date` Eloquent menyimpan nilai lengkap `Y-m-d H:i:s`, sehingga `where('kolom_date', '<=', '2026-09-30')` bernilai **salah** pada SQLite karena dibandingkan sebagai string. Semua filter kolom bertipe tanggal memakai `whereDate()`.

---

## 3. MODUL MALL — LOYALITAS DUTA POINTS, VOUCHER, ATRIUM & FASILITAS (FASE 15)

### 3.1 Duta Points Sebagai Aset Multi-Currency (`PTS`)
Program loyalitas mall tidak dicatat sebagai *integer counter* statis di tabel user, melainkan diperlakukan sebagai **mata uang buku besar umum berjenis `PTS`**:
- **Akun Pengguna**: `points:user:{id}:PTS` (`AccountKind::CUSTOMER_WALLET`, `allow_negative=false`).
- **Akun Kewajiban Mall**: `liability:mall:points:PTS` (`AccountKind::LIABILITY`, `allow_negative=true`).
- **Batch Kedaluwarsa FIFO**: Tabel `mall_point_batches` melacak penerbitan poin per transaksi dengan masa kedaluwarsa 12 bulan. Pemakaian poin mendahulukan batch terlama (First In, First Out).

### 3.2 Siklus Hidup Voucher & Breakage Accounting
1. **Penerbitan Voucher**:
   Pengguna menukarkan `PTS` menjadi voucher belanja senilai nominal IDR.
   - Sisi PTS: Debet `PTS` user, Kredit `liability:mall:points:PTS`.
   - Sisi IDR: Debet beban promosi `expense:mall:loyalty:IDR`, Kredit kewajiban voucher `liability:mall:voucher:IDR`.
2. **Penyelesaian Voucher (Settlement)**:
   Saat voucher digunakan di tenant (Resto atau Toko Mall), klaim dicairkan pada siklus mingguan via command `mall:settle-vouchers`:
   - Debet `liability:mall:voucher:IDR`, Kredit dompet tenant `wallet:user:{tenant_id}:IDR`.
3. **Voucher Kedaluwarsa (Breakage)**:
   Voucher yang tidak digunakan hingga batas waktu kedaluwarsa diproses oleh `mall:expire-vouchers`:
   - Debet `liability:mall:voucher:IDR`, Kredit pendapatan *breakage* `revenue:mall:voucher_breakage:IDR`.

### 3.3 Pemesanan Atrium Event & Proteksi Jadwal
- Manajemen ruang event (`mall_event_spaces`) dan pemesanan atrium bazaar (`mall_event_bookings`).
- **Pendeteksian Bentrok Jadwal**: `EventScheduleConflictException` dilempar saat terjadi irisan tanggal mulai/selesai untuk status `CONFIRMED` atau `ONGOING`.
- Perhitungan tarif sewa harian digabung dengan biaya sewa booth UMKM tambahan.

### 3.4 Manajemen Fasilitas (Work Order & Tagihan Tenant)
- Pemeliharaan preventif berkala terhadap fasilitas gedung (HVAC, Genset, Eskalator, Pompa Kebakaran). Command `mall:generate-pm` membuat *work order* otomatis saat mencapai `next_pm_date`.
- Kerusakan yang disebabkan kelalaian tenant dapat ditagihkan langsung ke invoice sewa tenant sebagai `InvoiceLineType::REPAIR_COST` (prioritas alokasi ke-6, setelah parkir dan sebelum service charge).

---

## 4. INTEGRASI LINTAS LINI BISNIS & HOLDING MONOLITH (FASE 16)

### 4.1 Arsitektur Holding Terpadu
Ekosistem mengintegrasikan seluruh lini bisnis ke dalam holding konglomerasi yang kohesif:
1. **Resto RM Sari Ranah & AutoServe Express Beroperasi Sebagai Tenant Mall**:
   - Modul Mall mengekspos contract `Modules\Mall\Contracts\TenantSalesProvider`.
   - Modul Resto mendaftarkan `RestoTenantSalesProvider` (tagged `mall.tenant_sales_provider`) yang mengonsolidasi omzet dari `resto_daily_summaries` berdasarkan `mall_tenants.external_ref = 'DM-01'`.
   - Modul AutoServe mendaftarkan `AutoServeTenantSalesProvider` berdasarkan `mall_tenants.external_ref = 'AUTOSERVE-DM'`.
   - Saat `mall:generate-invoices` dijalankan, omzet ditarik otomatis untuk menghitung top-up bagi hasil sewa (*revenue share* / *greater of*).
2. **Validasi Parkir dari POS Resto**:
   - Kasir Resto menyematkan potongan parkir pada pesanan hidang via `Modules\Mall\Contracts\ParkingValidator`.
   - Jam gratis disimpan di sesi parkir, dan nominal diskon dibebankan sebagai piutang tenant pada tagihan bulanan `parking_validation`.
3. **Duta Points & Voucher Belanja Lintas Modul**:
   - Resto dan Store mengonsumsi contract `Modules\Mall\Contracts\LoyaltyLedger`.
   - Makan di Resto memperoleh Duta Points `PTS`.
   - Voucher mall dapat dipakai memotong pembayaran tagihan makan hidang atau suku cadang Store.
4. **Sinkronisasi Paspor Kendaraan & Hak Akses Parkir**:
   - Event `VehicleOwnershipTransferred` dari modul Core didengar oleh `CancelParkingMembershipOnVehicleTransfer` di modul Mall.
   - Kartu langganan parkir pemilik lama otomatis dibatalkan, mencegah penyalahgunaan hak akses gerbang parkir.
5. **Dashboard Grup Konsolidasi (Holding Executive P&L)**:
   - `ConsolidatedPlQuery` mengagregasi pendapatan dan beban dari `bank_ledger_entries` ke dalam 4 pilar usaha dalam **hanya 2 query SQL**.

### 4.2 Sequence Diagram: Siklus Hidup Transaksi Lintas Lini 1 Hari Penuh

```mermaid
sequenceDiagram
    autonumber
    actor C as Pelanggan (Customer)
    participant Gate as Gate Masuk Parkir
    participant Core as Modul Core (Vehicle)
    participant POS as POS Resto (RM Sari Ranah)
    participant Mall as Modul Mall (Duta Mall)
    participant Bank as Core Banking (Ledger)
    participant Exit as Gate Keluar Parkir
    actor Admin as Admin Mall / Holding

    %% 1. Masuk Parkir
    C->>Gate: Masuk Gate Parkir (Plat B 1234 ABC)
    Gate->>Core: Cocokkan Plat dengan My Garage
    Gate->>Mall: Terbitkan Tiket Sesi Parkir (mall_parking_sessions)

    %% 2. Makan di Resto
    C->>POS: Pesan Hidang Meja & Santap Makanan
    C->>POS: Minta Validasi Parkir & Bayar via Wallet IDR
    POS->>Mall: Validasi Tiket Parkir via ParkingValidator (Gratis 2 Jam)
    POS->>Bank: Potong Saldo Dompet Customer (PayOrderAction)
    Bank-->>POS: Konfirmasi Pembayaran Sukses (0 Selisih)
    POS->>Bank: Tambah Duta Points (+100 PTS) via LoyaltyLedger

    %% 3. Keluar Parkir
    C->>Exit: Tiba di Gate Keluar Parkir
    Exit->>Mall: Hitung Tarif Parkir (3 Jam - 2 Jam Diskon Validasi = 1 Jam)
    Exit->>Bank: Potong Saldo Dompet IDR untuk Biaya 1 Jam (SettleParkingSessionAction)
    Exit->>Mall: Catat Piutang Validasi Tenant Resto (validation_free_hours)
    Exit-->>C: Buka Palang Otomatis

    %% 4. Penagihan Akhir Bulan
    Admin->>Mall: Jalankan Artisan mall:generate-invoices
    Mall->>POS: Tarik Omzet Bersih Resto via TenantSalesProvider
    Mall->>Mall: Hitung Sewa (Greater-Of: Minimum Base vs 10% Revenue Share)
    Mall->>Mall: Tambahkan Baris Piutang Validasi Parkir & Biaya Utilitas
    Mall->>Bank: Eksekusi Auto-Debit Tagihan Sewa dari Dompet Tenant
    Bank-->>Mall: Konfirmasi Pembukuan Kredit Pendapatan Sewa Mall

    %% 5. Audit & Rekonsiliasi
    Admin->>Bank: Jalankan php artisan bank:reconcile
    Bank-->>Admin: ✓ 64 Akun Seimbang, Selisih = 0
    Admin->>Mall: Jalankan php artisan mall:audit-billing
    Mall-->>Admin: ✓ Seluruh Tagihan Sinkron Sempurna dengan Ledger
```

---

## 5. SKALA, HARDENING & OBSERVABILITAS (FASE 17)

### 5.1 Seeder Skala Besar (`DemoLargeSeeder`)
- Menginisialisasi 3 outlet resto, 60 tenant/unit mall, 12 bulan penagihan historis, dan **150.000 sesi parkir**.
- Menggunakan chunk 500 baris dalam satu transaksi database tunggal untuk menyelesaikan penyisipan ratusan ribu data dalam **< 4 detik**.
- Penagihan historis ditandai status `OVERDUE` dan `ISSUED` dengan `paid_amount = 0` guna merepresentasikan piutang berumur tanpa menciptakan posting saldo fiktif di buku besar.

### 5.2 Anggaran Query SQL Teruji (`QueryBudgetTest`)
Ambang batas query ketat pada rute tersibuk dipantau melalui test otomatis:
- Main Dashboard: $\le 25$ query (Aktual: 11)
- AutoDex Catalog: $\le 15$ query (Aktual: 3)
- Store Catalog: $\le 15$ query (Aktual: 3)
- Resto POS: $\le 20$ query (Aktual: 14)
- Mall Site Plan: $\le 20$ query (Aktual: 4)
- Mall Billing Index: $\le 25$ query (Aktual: 14)
- Mall Parking Realtime: $\le 20$ query (Aktual: 11)
- Group P&L Dashboard: $\le 15$ query (Aktual: 2)
- Global Search API: $\le 15$ query (Aktual: 9)

### 5.3 Promosi Contract `VerifiesWalletPin`
Untuk menghilangkan pelanggaran batas arsitektur di mana 6 modul eksternal mengimpor implementasi konkrit `Modules\Banking\Application\Actions\VerifyPinAction`:
- Dibuat interface publik `Modules\Banking\Contracts\VerifiesWalletPin`.
- Seluruh modul eksternal (`AutoServe`, `Resto`, `Mall`, `Crypto`, `Finance`, `Store`) beralih ke tipe data contract.
- Arch test Pest menegakkan bahwa tidak ada kode di luar Banking yang mengimpor `VerifyPinAction` secara langsung.

### 5.4 Pertahanan Keamanan Platform (`SecurityTest`)
1. **IDOR (Insecure Direct Object Reference)**: Verifikasi isolasi kepemilikan invoice dan portal mandiri antar-tenant dengan `abort(403)`.
2. **Perlindungan Mass Assignment**: Atribut kritis terlindungi dari injeksi massal di model `Invoice`, `Order`, dan `Vehicle`.
3. **PIN Brute Force Lockout**: Sistem otomatis mengunci akun pengguna selama 15 menit setelah 5 kali gagal berturut-turut (`PinLockedException`).
4. **Verifikasi Signed URL**: Mencegah pemalsuan parameter atau manipulasi URL bertandatangan kriptografis.
5. **Pencegahan XSS**: Seluruh data dinamis di Blade template diproteksi dengan sanitasi entitas HTML (`{{ ... }}`).

### 5.5 Observabilitas 8 Pilar Platform (`super:health-check`)
Artisan command `super:health-check` dan dashboard `/admin/health` mengevaluasi kesehatan sistem menyeluruh:
1. **Primary Database**: Latensi ping koneksi dan integritas driver SQLite/MySQL.
2. **Cache Store**: Kesiapan pembacaan dan penulisan *in-memory cache*.
3. **Direktori Storage**: Verifikasi izin tulis (*writable*) pada filesystem direktori kerja framework dan log.
4. **Buku Besar Double-Entry**: Rekonsiliasi nol selisih seluruh akun buku besar (`bank:reconcile`).
5. **Paspor Kendaraan**: Integritas kriptografis rantai hash SHA-256 (`core:verify-passports`).
6. **Tagihan & Revenue Mall**: Audit kecocokan penerbitan dan pelunasan invoice terhadap ledger (`mall:audit-billing`).
7. **Shift & Kasir Resto**: Validasi konsistensi laci kasir dan penutupan harian (`resto:close-day --check`).
8. **Logistik (Billing & Rantai Kustodi)**: Audit integritas billing logistik vs ledger (`lgx:audit-billing`) dan keabsahan rantai hash lacak balak (`lgx:verify-custody`).

---

## 6. MODUL LOGISTIK — SARI RANAH EXPRESS (FASE 20–25)

### 6.1 Fondasi Jaringan, Armada Multimoda & Kustodi Kriptografis
- **Jaringan Hub-and-Spoke**: Berpusat di Kalimantan Selatan (Banjarmasin HUB-BDJ & HUB-BJB), pelabuhan UN/LOCODE, bandara IATA, CFS, dan depot kontainer.
- **Armada Multimoda**:
  - Truk terhubung ke Vehicle Passport (`core_vehicles`) plat DA dengan verifikasi rantai paspor.
  - Kapal laut (`lgx_vessels`) dengan verifikasi check-digit IMO dan kontainer (`lgx_containers`) dengan check-digit ISO 6346.
  - Pengemudi (`lgx_drivers`) dengan penegakan regulasi batas jam kerja maks 8 jam/hari & istirahat 30 menit per 4 jam.
- **Rantai Kustodi Kriptografis (`lgx_tracking_events`)**: Append-only hash chain SHA-256 per shipment `hash = SHA256(prev_hash || payload)`. Terverifikasi secara periodik via command `lgx:verify-custody`.

### 6.2 Alur Moneter & Akun Buku Besar Logistik
- **Unearned vs Recognized Freight**:
  - Booking prabayar mendebit dompet pelanggan ke `lgx:unearned_freight:IDR`.
  - Saat pengiriman selesai (`ShipmentDelivered`), diposting debit `lgx:unearned_freight:IDR` dan kredit `lgx:freight_revenue:IDR`.
- **Shipper Pascabayar B2B**:
  - Resi pascabayar diakui saat Delivered dengan mendebit piutang `lgx:ar:{shipper}:IDR` dan kredit `lgx:freight_revenue:IDR`.
  - Pembayaran invoice bulanan (`lgx:invoice-shippers`) membalik saldo piutang menjadi nol.
- **Cash on Delivery (COD)**:
  - Alur 3 tahap: penagihan kas oleh driver -> setoran fisik di hub -> settlement D+N ke shipper dikurangi fee COD (`lgx:cod_fee_revenue:IDR`).
- **Carrier Subkontrak, D&D, Bea Cukai & BBM**:
  - Akrual biaya leg carrier, termin mingguan (`lgx:pay-carriers`).
  - Demurrage & Detention per hari kalender lokasi (`lgx:accrue-dd`).
  - Bea cukai simulasi (PIB/PEB) dengan pembayaran via dompet digital.
  - Log konsumsi bahan bakar (integer ml & meter) dengan proteksi anomali > 30%.

### 6.3 Integrasi Lintas Lini (Fase 24)
- **Store -> Logistik**: Pesanan belanja online terbayar otomatis menerbitkan shipment via contract `ShipmentBooking`.
- **Pengiriman Kendaraan**: Ekspedisi mobil terintegrasi mencatat event `DELIVERED_BY_CARRIER` langsung ke paspor kendaraan digital.
- **Armada -> AutoServe**: Truk yang mencapai batas jarak servis memicu event `FleetServiceDue`, otomatis memesan perawatan di AutoServe, dan mengunci truk ke status Maintenance.
- **Resto Cold Chain**: Pengiriman bahan baku dapur pusat (CK-01) menggunakan truk reefer berpendingin dengan monitoring `lgx_temperature_readings` dan notifikasi deviasi suhu.
- **Mall Loading Dock**: Reservasi slot bongkar muat Duta Mall (`lgx_dock_appointments`) dengan sistem antrean anti-bentrok waktu.

### 6.4 Skala, API & Control Tower (Fase 25)
- **LogisticsLargeSeeder**: Seeder performa tinggi untuk pengujian skala ratusan ribu pengiriman dan jutaan event kustodi.
- **RESTful API v1 (Laravel Sanctum)**: Endpoint `/api/v1/logistics` dengan token abilities granular (`quote:create`, `shipment:create`, `shipment:read`), rate limit token-bucket, dan header idempotensi `Idempotency-Key`.
- **Webhook Outbox**: Pengiriman event asinkron bergaransi dengan penandatanganan HMAC-SHA256, exponential backoff hingga 8 kali retry, dan command `lgx:retry-webhooks`.
- **Control Tower Dashboard**: Pusat kendali eksekutif untuk `logistics_admin` menyajikan metrik OTIF, utilisasi armada, dwell time, saldo titipan COD, dan margin per rute.

---

## 7. KONVENSI BUKU BESAR DOUBLE-ENTRY MULTI-ASET

Seluruh transaksi finansial di platform ini diatur oleh tabel `bank_ledger_accounts` dan `bank_ledger_entries`:

$$\sum \text{Entries per Akun} = \text{cached\_balance}$$
$$\sum \text{Entries Seluruh Akun per Aset} = 0$$

### Peta Akun Sistem Utama

| Kode Akun | Jenis Aset | Kind | allow_negative | Fungsi Moneter |
|---|---|---|---|---|
| `wallet:user:{id}:IDR` | IDR | CUSTOMER_WALLET | false | Saldo dompet rupiah pengguna / customer |
| `points:user:{id}:PTS` | PTS | CUSTOMER_WALLET | false | Saldo Duta Points loyalitas pengguna |
| `clearing:external:IDR` | IDR | CLEARING | true | Penampung arus dana masuk eksternal (Payment Gateway, Top Up) |
| `escrow:trade:{uuid}:IDR` | IDR | ESCROW | false | Rekening penampung escrow servis bengkel / bursa C2C |
| `revenue:resto:{outlet}:IDR` | IDR | REVENUE | false | Pendapatan penjualan makanan & minuman outlet resto |
| `revenue:mall:rent:IDR` | IDR | REVENUE | false | Pendapatan sewa dasar tenant Duta Mall |
| `revenue:mall:rev_share:IDR` | IDR | REVENUE | false | Pendapatan bagi hasil omzet tenant Duta Mall |
| `revenue:mall:parking:IDR` | IDR | REVENUE | false | Pendapatan tiket dan langganan parkir mall |
| `revenue:holding:royalty:IDR`| IDR | REVENUE | false | Pendapatan royalti waralaba holding dari franchise resto |
| `liability:mall:points:PTS` | PTS | LIABILITY | true | Penampung kewajiban poin Duta Points mall |
| `liability:mall:voucher:IDR` | IDR | LIABILITY | true | Penampung kewajiban klaim voucher belanja mall |
| `expense:mall:loyalty:IDR` | IDR | EXPENSE | false | Beban promosi penerbitan voucher loyalitas mall |
| `cash:drawer:{outlet}:IDR` | IDR | CASH | true | Posisi fisik uang tunai di laci kasir resto |
| `cash:mall:parking:IDR` | IDR | CASH | true | Posisi fisik uang tunai di pos kasir keluar parkir |
| `lgx:unearned_freight:IDR` | IDR | LIABILITY | true | Pendapatan freight diterima di muka (resi belum Delivered) |
| `lgx:freight_revenue:IDR` | IDR | REVENUE | false | Pendapatan freight yang telah diakui pasca pengantaran |
| `lgx:ar:{shipper}:IDR` | IDR | CLEARING | true | Piutang freight dan D&D shipper pascabayar B2B |
| `lgx:carrier_payable:{id}:IDR`| IDR| LIABILITY | true | Utang ongkos angkut carrier subkontrak |
| `lgx:carrier_cost:IDR` | IDR | EXPENSE | false | Beban biaya jasa carrier subkontrak |
| `lgx:dd_revenue:IDR` | IDR | REVENUE | false | Pendapatan denda Demurrage & Detention |
| `lgx:customs_duty_payable:IDR`| IDR| LIABILITY | true | Titipan pungutan bea masuk & pajak impor kepabeanan |
| `lgx:cod_clearing:{shipper}:IDR`| IDR| LIABILITY| true | Titipan dana COD sebelum dicairkan ke shipper |
| `lgx:cod_fee_revenue:IDR` | IDR | REVENUE | false | Pendapatan fee layanan COD |
| `lgx:cash:driver:{id}:IDR` | IDR | CASH | true | Posisi uang tunai COD di tangan pengemudi |
| `lgx:cash:hub:{id}:IDR` | IDR | CASH | true | Posisi kas setoran COD di hub operasi |
| `lgx:claim_expense:IDR` | IDR | EXPENSE | false | Beban pembayaran klaim kerusakan / kehilangan barang |
| `lgx:fuel_expense:IDR` | IDR | EXPENSE | false | Beban pembelian bahan bakar minyak armada |
| `rwa:dust_reserve:IDR` | IDR | REVENUE | false | Penampung residu pembulatan pro-rata dividen token RWA |
| `rwa:dividend_pool:IDR` | IDR | LIABILITY | true | Pool penampung dividen siap disalurkan ke investor RWA |
| `escrow:gig:task:{id}:IDR` | IDR | ESCROW | false | Rekening penampung upah talenta gig board hingga pekerjaan tervalidasi |

---

## 8. ARSITEKTUR INTEGRASI KONGLOMERASI 12 LINI BISNIS (FASE 96–100)

Platform AutoServe telah mencapai tingkat kematangan arsitektur enterprise konglomerasi 12 lini bisnis terpadu dengan 5 pilar gelombang evolusi:

```mermaid
graph TD
    subgraph "FinTech & Aset Riil (Fase 96)"
        RWA["RWA Token Engine<br/>Pro-Rata & Dust Reserve"]
        INS["InsurTech 12 Parametric<br/>Auto-Disbursement"]
        CREDIT["360° Credit Scoring<br/>Multi-Vector Analytics"]
        USDC["24/7 USDC Locked FX<br/>Dynamic Cash Sweep"]
    end

    subgraph "HCM, ESG & Event Spine (Fase 97)"
        GIG["Universal Talent Gig Board<br/>UU 13/2003 Overtime Guardrail"]
        ESG["Consolidated Scope 1-3 ESG<br/>Carbon Emission Engine"]
        SPINE["Universal Event Spine<br/>Transactional Outbox & DLQ"]
        CMD["Executive Command Center<br/>12-Lines Consolidated P&L"]
    end

    subgraph "Skalabilitas & Resiliensi (Fase 98)"
        SEEDER["Twelve Lines Ultra Seeder<br/>Memory-Capped < 64MB"]
        INDEX["Composite Scale Indexes<br/>Query Budget CI Guardrails"]
        LOCK["1.000 Req Pessimistic Lock<br/>Zero Room/Seat Oversell"]
        CHAOS["Mid-Tx Crash Rollback<br/>Clean DLQ Worker Recovery"]
    end

    subgraph "AI & Analitik Prediktif (Fase 99)"
        PRICE["Dynamic Pricing Engine<br/>CONTRACT_PRIORITY Wins"]
        FORECAST["Multi-Vector Forecasting<br/>MAPE ≤ 12.5% Target"]
        QUARANTINE["AI Anomaly Quarantine<br/>5-Vector Risk Score ≥ 60"]
        OPS["Prescriptive Operations<br/>Autonomous vs Managerial"]
    end

    subgraph "Keamanan & Tata Kelola (Fase 100)"
        RBAC["60+ Enterprise Roles<br/>Granular Conglomerate RBAC"]
        ENCRYPT["AES-256 Medical Encryption<br/>Field-Level NIK & Diagnosis"]
        CALENDAR["12-Lines Compliance Calendar<br/>Permit Expiry Auto-Alerts"]
        ABUSE["Anti-Abuse Rate Limiter<br/>Biometric & IoT Token Rotation"]
        HEALTH["super:health-check<br/>12 Platform Pillars Observability"]
    end

    RWA --> SPINE
    INS --> SPINE
    GIG --> SPINE
    SPINE --> CMD
    CMD --> HEALTH
    PRICE --> QUARANTINE
    QUARANTINE --> HEALTH
    RBAC --> HEALTH
```

### 8.1 FinTech, Tokenisasi Aset Riil & Asuransi Parametrik (Fase 96)
- **RWA Pro-Rata Dividend Invariant**:
  $$\sum_{i=1}^n \text{payout}_i + \text{dust} = \text{total\_pool}$$
  Alokasi dividen per token dihitung proporsional berbasis minor-unit integer. Selisih pembulatan (*dust*) dikreditkan langsung ke rekening penampung holding `rwa:dust_reserve:IDR`, menjamin nol kebocoran moneter.
- **Parametric Insurance Engine**: 12 pemicu parametrik otomatis (cuaca ekstrem, delay logistik > 120 menit, suhu reefer cold chain $> 4^\circ\text{C}$, dsb.) memicu pembayaran klaim instan tanpa intervensi manual saat sensor telemetri tervalidasi.
- **USDC Locked Clearing**: Kliring valas 24/7 menggunakan settlement stablecoin dengan kurs terkunci saat order quote terbit.

### 8.2 Talent Gig Interoperability, ESG & Event Spine (Fase 97)
- **Universal Talent Gig Board**: Pertukaran tenaga kerja terampil antar-anak usaha (Hotel, Venue, RS, Tambang, Logistik). Penugasan otomatis dipagari aturan hukum ketenagakerjaan UU 13/2003 (maks 8 jam/hari, 14 jam lembur/minggu) dengan pencairan upah berbasis bukti kerja terverifikasi (anti-self-approval).
- **Consolidated Scope 1, 2, 3 ESG**:
  - Scope 1: Emisi bahan bakar armada dan alat berat tambang.
  - Scope 2: Konsumsi listrik gedung, mall, dan charging EV SPKLU.
  - Scope 3: Rantai pasok pengadaan bahan mentah dan vendor pihak ketiga.
- **Transactional Outbox Event Spine**: Publikasi event lintas modul terdistribusi dengan garansi delivery at-least-once, de-duplikasi idempoten, dan pengalihan ke Dead-Letter Queue (DLQ) setelah 3 kegagalan beruntun.

### 8.3 Skalabilitas Bertekanan Tinggi & Resiliensi Ekstrem (Fase 98)
- **Ultra Seeder Skala**: Dataset realistis ratusan ribu baris yang dibuat secara deterministik dalam chunk 500 baris dengan konsumsi memori terisolasi $< 64\text{ MB}$.
- **Pessimistic Concurrency Interlock**: Pemesanan kamar hotel dan tiket venue dilindungi klausa `lockForUpdate()`, membuktikan nol oversell di bawah 1.000 transaksi bersamaan.
- **Crash Rollback & DLQ**: Interupsi worker atau kegagalan transaksi seketika memicu rollback ACID bersih tanpa baris orphan, sementara pesan outbox yang gagal dieksekusi dialihkan ke DLQ untuk inspeksi pasca-insiden.

### 8.4 AI Prediktif, Penetapan Harga & Karantina Anti-Fraud (Fase 99)
- **Unified Dynamic Pricing Engine**:
  - Aturan mutlak: `CONTRACT_PRIORITY` menang tanpa syarat atas segala pengali lonjakan permintaan dinamis (*surge multiplier*).
  - Transaksi non-kontrak dibatasi ketat oleh batas lantai (*floor price*) dan batas atap (*ceiling price*).
- **Multi-Vector Demand Forecasting**: Memadukan data footfall mall, reservasi hotel, tren musim, dan kalender festival dengan target akurasi MAPE $\le 12.5\%$.
- **Karantina Anti-Fraud AI 5-Vektor**: Evaluasi transaksi seketika atas 5 indikator (kecepatan transaksi, anomali magnitudo, lonjakan geolokasi, kepatuhan kontrak, dan sidik jari perangkat). Skor risiko $\ge 60$ otomatis mengisolasi transaksi ke `core_ai_anomaly_quarantines` dan memblokir settlement buku besar.

### 8.5 Keamanan Enterprise, Enkripsi Medis & Observabilitas 12 Pilar (Fase 100)
- **RBAC 60+ Role Konglomerasi**: Matriks otorisasi data-driven melayani 60+ peran granular lintas lini (medis, hotel, venue, tambang, logistik, treasury, compliance).
- **Field-Level AES-256 Medical Encryption**: Data rekam medis sensitif (NIK pasien, diagnosis ICD-10, rincian obat resep) pada model `PatientMedicalRecord` terenkripsi AES-256-GCM secara native di database, mematuhi standar privasi UU PDP / HIPAA.
- **Compliance Calendar 12 Lini**: Pemantauan otomatis masa berlaku izin operasional (RS, keramaian venue, pariwisata hotel, IUP/AMDAL tambang, halal BPOM) dengan eskalasi alert `WARNING_EXPIRING_SOON` dan `CRITICAL_EXPIRED`.
- **Anti-Abuse Rate Limiting Engine**:
  - Verifikasi usia venue dengan biometrik simulasi & lockout bruteforce.
  - Akses darurat IGD terisolasi dengan auto-lockout terminal IP 15 menit pasca-3 kegagalan.
  - Pencegahan bot mass-booking (anti-scalping) tiket & kamar hotel.
  - Rotasi berkala token perangkat IoT telematika dengan grace period 60 detik.
- **Master Multi-Pillar Observability (`super:health-check`)**: Memindai kesehatan 12 pilar utama ekosistem platform secara serentak dengan toleransi zero-discrepancy:
  1. Koneksi Database Primary (latensi $< 50$ ms)
  2. Cache & In-Memory Store
  3. Izin Akses Direktori Storage (writable)
  4. Konsistensi Double-Entry Ledger (`bank:reconcile` 0 selisih)
  5. Integritas Hash-Chain Paspor Kendaraan (`core:verify-passports` rantai utuh)
  6. Audit Tagihan & Revenue Mall (`mall:audit-billing` 0 selisih)
  7. Operasional & Shift Kasir Resto (`resto:close-day --check` seimbang)
  8. Logistik: Billing & Rantai Kustodi (`lgx:audit-billing` & `lgx:verify-custody` bersih)
  9. Aset: Subledger & Rantai Hash (`ast:audit` 0 selisih)
  10. Pembelian: Subledger AP & GR/IR (`proc:audit` 0 selisih)
  11. Kontrak: Subledger Plafon & Rantai Versi (`ctr:audit` & `contracts:verify-chain` valid)
  12. Manufaktur: Biaya Produksi WIP & Mutu QMS (`mfg:audit-costing` & `mfg:qms-audit` seimbang)

### 8.6 Skenario Emas 12 Lini, Krisis Recall Produk & DR Kuantum (Fase 101)
- **Skenario Emas Otomatis 12 Lini Bisnis**:
  Alur data terpadu mengaitkan rantai nilai hulu-ke-hilir: panen petani plasma `agri` $\to$ distribusi cold-chain `lgx` $\to$ pengolahan pabrik `mfg` $\to$ penjualan resto/hotel/venue $\to$ perbekalan medis rumah sakit `hsp` $\to$ operasional armada listrik `ev` $\to$ perdagangan komoditas `min` $\to$ konsolidasi buku besar holding grup dengan keseimbangan akuntansi absolut ($\sum = 0$).
- **Bidirectional Product Recall Traceability**:
  Saat kontaminasi terdeteksi, `CrisisProductRecallService` melakukan penelusuran maju (forward: dari bahan baku ke pasien penerima obat / konsumen restoran) dan mundur (backward: dari laporan insiden ke nomor lot vendor pemasok) dalam waktu $< 1$ detik, mengunci seluruh lot yang terdampak ke status `QUARANTINED`.
- **Multi-Region Active-Active DR & Konsensus Raft**:
  Arsitektur 3 node terdistribusi (Jakarta primary, Surabaya secondary, Singapore witness) dengan protokol konsensus Raft:
  - RPO = 0: Zero ledger entry loss pada saat failover.
  - RTO < 15 menit: Promosi sekunder otonom bila kuorum $2/3$ node tercapai.
  - Split-brain defense: Bila jaringan terputus parsial, cluster secara otomatis membatasi diri ke mode `READ_ONLY` guna mencegah dual-master write.
- **Kesiapan Kriptografi Pasca-Kuantum (PQC)**:
  Verifikasi berkala 8 rantai hash Merkle append-only (Paspor Kendaraan, Paspor Pasien, Tiket Acara, Kustodi Logistik, Timbangan Tambang, Kontrak Korporat, Aset Tetap, ECO Manufaktur) melalui `php artisan pq:audit-chains` dan skema rotasi kunci master envelop KMS tanpa merusak pembacaan data historis.

### 8.7 Enterprise REST API v3, Webhook Outbox & Mobile Offline Sync (Fase 102)
- **Enterprise REST API v3**:
  - Endpoint `/api/v3/executive/kpis` dan `/api/v3/executive/snapshot` menyediakan ringkasan operasional dan kesehatan cluster secara near-real-time.
  - Kepatuhan standar RFC 7807 Problem Details (`application/problem+json`) untuk seluruh jalur galat HTTP.
  - Proteksi idempotensi terdistribusi melalui header `Idempotency-Key` dengan kunci sewa cache atomik.
- **Mobile Offline-First Delta Synchronization**:
  - Endpoint `/api/v3/mobile/sync/pull` dan `/api/v3/mobile/sync/push` melayani pertukaran delta entitas staf lapangan (dokter, perawat, kurir, surveyor tambang).
  - Jam Vektor (*Vector Clocks*) melacak kausalitas mutasi konkuren lintas node perangkat dan server.
  - Strategi resolusi konflik deterministik:
    - Konflik catatan medis diselesaikan dengan `SERVER_HEALTHCARE_AUTHORITY_PRIORITY`.
    - Konflik stok fisik diselesaikan dengan pembuatan jurnal pembukuan penyesuaian kompensasi otomatis pada `core_sync_conflict_ledgers`.
- **Mitra Webhook Outbox & Dead-Letter Queue (DLQ)**:
  - Registrasi webhook mitra terisolasi multi-tenant (`X-Tenant-ID`) dengan penandatanganan payload HMAC-SHA256.
  - Mekanisme pengiriman ulang otomatis exponential backoff hingga 5 kali. Pesan yang gagal permanen dialihkan ke antrean DLQ dan dapat dipicu ulang melalui `POST /api/v3/partner/webhooks/{id}/replay`.

### 8.8 Playbook 60+ Peran Konglomerasi & Standard Operating Procedures (Fase 103)
- **Struktur Otorisasi 60 Peran Konglomerasi**:
  Matriks peran RBAC mencakup seluruh lini bisnis holding dengan batasan kewenangan pengeluaran anggaran dan approval berjenjang (Operational Staf $\le$ Rp 10 Jt, Manajer $\le$ Rp 100 Jt, Direktur Unit $\le$ Rp 1 M, Holding Board $>$ Rp 1 M).
- **Prosedur Standar Operasional (SOP) Lintas Lini**:
  - SOP-01: Prosedur Penarikan Produk Krisis Lintas Lini (Holding Crisis Protocol).
  - SOP-02: Triage Gawat Darurat & Evakuasi Medis Lintas Cabang Faskes.
  - SOP-03: Rekonsiliasi & Eliminasi Finansial Intercompany Tutup Buku Bulanan.

### 8.9 Layanan Kesehatan Digital, Telemedis & Farmasi FEFO (Fase 104)
- **Telemedis & E-Resep Terotentikasi**:
  - Triase digital konsultasi video/chat (`hsp_tele_consults`) terintegrasi EMR Paspor Pasien.
  - E-resep digital (`hsp_epharmacy_orders`) wajib memuat tanda tangan kriptografis dokter berlisensi (`doctor_signature_hash`); resep tanpa tanda tangan otomatis ditolak oleh sistem.
- **Safety Engine Alergi Silang & Toksisitas Ginjal**:
  - Penegakan kontraindikasi langsung dan alergi silang kelas obat (contoh: riwayat alergi kelas penisilin memblokir peresepan amoksisilin dengan status `CRITICAL_CONTRAINDICATION`).
  - Proteksi klirens kreatinin ginjal: pasien dengan estimasi $\text{CrCl} < 30\text{ mL/min}$ otomatis diblokir dari obat-obatan nefrotoksik.
- **Manajemen Inventori Farmasi FEFO**:
  - Alokasi stok apotek memprioritaskan tanggal kedaluwarsa terdekat (*First-Expired-First-Out*).
  - Lot obat berstatus `QUARANTINED` otomatis dikeluarkan dari alokasi pemenuhan resep.
- **Multi-Branch Pharmacy Routing & Langganan Obat Kronis**:
  - Routing pemenuhan otomatis mengarahkan pesanan ke apotek cabang terdekat dengan failover ke hub regional bila stok setempat habis.
  - Langganan obat kronis mengeksekusi pengiriman berulang berkala dengan auto-debit dompet dan buku besar berpasangan seimbang.

### 8.10 Jaringan Laboratorium LOINC, Kustodi Spesimen 3-Hop & PACS Imaging (Fase 105)
- **Katalog LOINC & Deteksi Nilai Panik Kritis**:
  - Standardisasi 10.000 parameter tes laboratorium LOINC dengan batas rentang nilai panik fisiologis (`hsp_lab_critical_ranges`).
  - Hasil tes dengan anomali kritis (Kalium $< 2.5\text{ mEq/L}$, Troponin $> 0.04\text{ ng/mL}$) otomatis memicu `PANIC_ALERT` dan penahanan rilis hingga diverifikasi langsung oleh dokter spesialis patologi.
- **Rantai Kustodi Spesimen 3-Hop Anti-Pelanggaran Rantai Dingin**:
  - Pelacakan berantai faskes $\to$ kurir cold-chain $\to$ lab rujukan berintegritas hash Merkle (`hsp_lab_custody_transfers`).
  - Sensor suhu IoT yang mendeteksi suhu $> 8^\circ\text{C}$ (pelanggaran rantai dingin) seketika memicu auto-rejection spesimen dan penerbitan instruksi penarikan sampel ulang.
- **Pencitraan DICOM PACS & SLA STAT**:
  - Penyimpanan metadata irisan seri radiologi DICOM PACS (`hsp_imaging_series`).
  - Penegakan SLA pembacaan citra radiologi: pemeriksaan gawat darurat STAT wajib selesai $\le 60$ menit, dan pemeriksaan rutin $\le 24$ jam.
- **Kliring Finansial Antar-Cabang Apotek & Faskes**:
  - Kliring resiprokal kewajiban antar-cabang faskes (`hsp_inter_branch_clearings`) dengan penyelesaian saldo buku besar double-entry 0-diskrepansi.




---

## 10. Diagram Sekuensial Lintas Lini Bisnis Konglomerasi

Bagian ini memodelkan interaksi mikro-layanan dan alur data end-to-end melintasi batas domain modular untuk 12 skenario bisnis utama konglomerasi.

### 10.1 Skenario 1: E2E Consumer Journey — Multi-Tenant Super-App Checkout to Instant Settlement

```mermaid
sequenceDiagram
    autonumber
    actor Customer as "Konsumen (Super-App)"
    participant Store as "Modul Store / Resto"
    participant PayHub as "Payment Hub Sentral"
    participant Ledger as "Banking Ledger"
    participant WMS as "Logistics & WMS"
    participant Mall as "Mall Property"
    Customer->>Store: Checkout multi-merchant cart
    Store->>PayHub: Request unified charge (kontrak Payable)
    PayHub->>Ledger: Atomic hold saldo dompet IDR / QRIS
    Ledger-->>PayHub: Hold confirmed (journal locked)
    PayHub->>Store: Capture payment confirmation
    Store->>WMS: Generate automated packing list & dispatch task
    Store->>Mall: Record tenant turnover sales for revenue share
    Store-->>Customer: Order confirmation & digital receipt
```

### 10.2 Skenario 2: Multi-Tier Procurement — 3-Way Match dengan Automated AP Ledger Posting

```mermaid
sequenceDiagram
    autonumber
    actor Buyer as "Procurement Buyer"
    participant PRC as "Procurement Core"
    participant SUP as "Portal Supplier"
    participant WMS as "Warehouse Receiving"
    participant FIN as "Accounts Payable (AP)"
    participant GL as "General Ledger"
    Buyer->>PRC: Create Purchase Order (PO)
    PRC->>SUP: Transmit approved PO via B2B EDI
    SUP->>WMS: Delivery with electronic Surat Jalan (DO)
    WMS->>PRC: Record Good Receipt Note (GRN)
    SUP->>FIN: Submit electronic Commercial Invoice
    FIN->>PRC: Execute automated 3-Way Match (PO, GRN, Inv)
    PRC-->>FIN: Match 100% within tolerance
    FIN->>GL: Post automated journal: Debit Inventory / Credit AP
```

### 10.3 Skenario 3: Cold-Chain Bio-Pharma Logistics — IoT Telemetry ke Blockchain Escrow

```mermaid
sequenceDiagram
    autonumber
    participant Reefer as "Reefer IoT Sensor (2-8C)"
    participant Telemetry as "Telematics Ingestion"
    participant ColdChain as "Cold Chain Supervisor"
    participant Escrow as "Smart Contract Escrow"
    participant Hospital as "Hospital Pharmacy"
    Reefer->>Telemetry: Ping encrypted GPS & temperature payload
    Telemetry->>ColdChain: Validate thermal compliance threshold
    alt Suhu Aman (2C - 8C)
        ColdChain->>Escrow: Append telemetry cryptographic seal
        Reefer->>Hospital: Physical handoff with ePoD scan
        Hospital->>Escrow: Confirm custody acceptance
        Escrow->>Escrow: Release carrier delivery fee payment
    else Ekskursi Suhu (> 8C)
        ColdChain->>Hospital: Alert thermal breach: AUTO-QUARANTINE LOT
        ColdChain->>Escrow: Freeze payout & trigger insurer claim
    end
```

### 10.4 Skenario 4: Employee Meal & Perk Deduction — Multi-Company HCM ke Central Kitchen POS

```mermaid
sequenceDiagram
    autonumber
    actor Emp as "Karyawan Holding"
    participant POS as "Cloud Kitchen / Kantin POS"
    participant HCM as "HCM Benefits & Meal Plan"
    participant Payroll as "Payroll PPh 21 Engine"
    participant GL as "Intercompany Ledger"
    Emp->>POS: Tap RFID employee badge for lunch package
    POS->>HCM: Verify employee meal allowance quota
    HCM-->>POS: Quota valid (subsidi Rp 35.000 approved)
    POS->>GL: Debit Entitas Pegawai / Credit Resto Unit
    opt Nilai Belanja Melebihi Kuota
        HCM->>Payroll: Queue payroll deduction for excess amount
        Payroll->>Payroll: Deduct at next monthly pay slip
    end
```

### 10.5 Skenario 5: Hospital Emergency Admission ke BPJS INA-CBG Automated Claim Reconciliation

```mermaid
sequenceDiagram
    autonumber
    actor Patient as "Pasien Gawat Darurat (IGD)"
    participant Triage as "Perawat IGD & Triase"
    participant EMR as "Electronic Medical Record (AES-256)"
    participant Pharmacy as "Apotek IGD (FEFO)"
    participant BPJS as "BPJS INA-CBG Grouper Gateway"
    participant Billing as "Hospital Billing Engine"
    Patient->>Triage: Triage assessment & NIK identification
    Triage->>EMR: Emergency admission record
    Triage->>Pharmacy: Emergency resus drugs order (bypass payment)
    Pharmacy-->>Triage: Instant dispensing & lot batch recording
    EMR->>BPJS: Transmit ICD-10 & ICD-9-CM surgical codes
    BPJS-->>Billing: Return INA-CBG package tariff approval
    Billing->>Billing: Generate split bill (BPJS covered vs excess)
```

### 10.6 Skenario 6: Beach Club VIP Cabana — Min-Spend RFID Wristband ke Ledger Split

```mermaid
sequenceDiagram
    autonumber
    actor VIP as "Tamu VIP Cabana"
    participant Turnstile as "Turnstile Gate Scanner"
    participant Venue as "Venue Booking & Cabana Host"
    participant POS as "Bar & Lounge POS"
    participant Ledger as "Escrow & Revenue Split Ledger"
    VIP->>Turnstile: Tap pre-paid VIP QR ticket
    Turnstile->>Venue: Issue active RFID wristband linked to folio
    Venue->>Ledger: Lock minimum spend escrow (e.g. Rp 15.000.000)
    VIP->>POS: Order premium champagne & platters via wristband
    POS->>Venue: Deduct order from minimum spend balance
    Venue->>Ledger: Split revenue: Venue Rental 40% / F&B 50% / Service 10%
    Venue-->>VIP: Real-time balance notification on Super-App
```

### 10.7 Skenario 7: Hotel Night Audit — Automated Folio Split, City Ledger & Housekeeping Turnover

```mermaid
sequenceDiagram
    autonumber
    actor NightAuditor as "Night Auditor (Cron / Staf FO)"
    participant PMS as "Property Management System (PMS)"
    participant RoomEngine as "Smart Room IoT & Minibar"
    participant Housekeeping as "Housekeeping Task Engine"
    participant CityLedger as "Corporate City Ledger (AR)"
    participant GL as "General Ledger"
    NightAuditor->>PMS: Initiate Automated Night Audit Run (02:00 AM)
    PMS->>RoomEngine: Ingest automated minibar weight sensors
    PMS->>PMS: Post Room Charge & Hotel Tax (PB1) to in-house folios
    PMS->>CityLedger: Transfer corporate accounts to City Ledger AR
    PMS->>Housekeeping: Generate morning room turnover schedule
    PMS->>GL: Post automated daily revenue journal batch
    PMS-->>NightAuditor: Trial balance locked & Day Closed
```

### 10.8 Skenario 8: Mining Fleet Dispatch — High-Precision Weighbridge ke Perhitungan Royalti

```mermaid
sequenceDiagram
    autonumber
    actor Truck as "Haul Truck Driver"
    participant Pit as "Excavator GPS & Dig Dispatch"
    participant WB as "Digital Weighbridge Scale"
    participant Lab as "Assay Coal Quality Lab"
    participant Royalty as "ESDM Royalty & PNBP Engine"
    Truck->>Pit: Loaded with thermal coal at Pit 3 North
    Pit->>Truck: Transmit digital manifest to truck tablet
    Truck->>WB: Drive onto weighbridge (RFID scan)
    WB->>WB: Record Gross Tare Net weight (certified calibration)
    Lab->>Royalty: Transmit Calorific Value (CV) & Moisture assay
    WB->>Royalty: Link weight ticket with assay record
    Royalty->>Royalty: Compute progressive statutory royalty (HBA rate)
    Royalty-->>Truck: Certified hauling ticket issued with SHA-256 seal
```

### 10.9 Skenario 9: Real-World Asset (RWA) — Fractional Tokenization & Automated Dividend Distribution

```mermaid
sequenceDiagram
    autonumber
    actor Investor as "Retail & Institutional Investor"
    participant RWA as "RWA Tokenization Engine"
    participant Asset as "Physical Asset Registry (PSAK 16)"
    participant Prop as "Property / SPKLU Revenue"
    participant DivEngine as "Dividend Pro-Rata Calculator"
    participant Wallet as "User Multi-Asset Wallets"
    Asset->>RWA: Tokenize physical commercial asset into 1.000.000 units
    Investor->>RWA: Purchase fractional tokens using IDR / Stablecoin
    Prop->>DivEngine: Deposit quarterly net operational rental revenue
    DivEngine->>DivEngine: Run exact integer minor unit pro-rata allocation
    DivEngine->>DivEngine: Route fractional rounding residue to reserve account
    DivEngine->>Wallet: Instant credit dividend to token holders' wallets
```

### 10.10 Skenario 10: Insurtech Parametric Flight Delay — Automated Weather Oracle ke Escrow Payout

```mermaid
sequenceDiagram
    autonumber
    actor Traveler as "Wisatawan Super-App"
    participant Insure as "Insurtech Micro-Policy Engine"
    participant Oracle as "Aviation / Weather Data Oracle"
    participant Escrow as "Insurance Reserve Escrow"
    participant Bank as "Instant Payout Gateway"
    Traveler->>Insure: Bind parametric policy during hotel / flight booking
    Insure->>Escrow: Lock indemnity reserve balance
    Oracle->>Insure: Ingest certified flight delay event (> 120 minutes)
    Insure->>Insure: Autopilot claim trigger (zero claim paperwork required)
    Insure->>Escrow: Release claim funds authorization
    Escrow->>Bank: Instant bank transfer / wallet credit to traveler
    Bank-->>Traveler: Push notification: Indemnity credited Rp 1.000.000
```

### 10.11 Skenario 11: Robo-Advisor Wealth Engine — Target-Allocation Drift ke Treasury Rebalance

```mermaid
sequenceDiagram
    autonumber
    actor Client as "Nasabah Wealth Management"
    participant Robo as "Robo-Advisor Portfolio Engine"
    participant Risk as "Risk Profile & Emergency Guardrail"
    participant Market as "Market Price Stream"
    participant Treasury as "Treasury Multi-Asset Order Router"
    Client->>Robo: Define Moderate Growth goal (60% Stock, 30% Bond, 10% Gold)
    Market->>Robo: Asset price fluctuations detect portfolio drift > 5%
    Robo->>Risk: Validate proposed rebalancing against liquidity guardrails
    Risk-->>Robo: Rebalancing plan approved
    Robo->>Treasury: Execute atomic sell overweighted asset / buy underweighted
    Treasury-->>Client: Rebalancing executed with zero slippage penalty
```

### 10.12 Skenario 12: Multi-Region Disaster Recovery — PostgreSQL Cross-DC Failover dengan Zero Ledger Loss

```mermaid
sequenceDiagram
    autonumber
    participant PrimaryDC as "Primary Data Center (DC-Jakarta)"
    participant Witness as "Raft Witness Node (DC-Surabaya)"
    participant StandbyDC as "Standby Data Center (DC-Bali)"
    participant AppCluster as "App Cluster & Route53 / BGP DNS"
    PrimaryDC->>StandbyDC: Synchronous streaming replication (WAL logs)
    Note over PrimaryDC: Primary DC network partition / physical disaster
    Witness->>PrimaryDC: Heartbeat ping failed (timeout > 3.000 ms)
    Witness->>StandbyDC: Request quorum voting for failover election
    StandbyDC->>Witness: Quorum 2/3 validated (Zero WAL lag confirmed)
    StandbyDC->>StandbyDC: Promote standby node to Read-Write Master
    StandbyDC->>AppCluster: Update connection pool DNS routing
    AppCluster-->>AppCluster: Resume transactions with RPO=0 and RTO < 30s
```

---

## 11. Diagram C4 Arsitektur Sistem Konglomerasi

### 11.1 C4 Context Diagram: Interaksi Platform dengan Pengguna & Sistem Eksternal

```mermaid
flowchart TB
    subgraph External_Users["Pengguna Sistem Eksternal"]
        UserCons["Konsumen / Pembeli Super-App"]
        UserB2B["Mitra B2B / Supplier / Tenant Mall"]
        UserCorp["Manajemen Korporat & Auditor Holding"]
    end
    subgraph Core_Platform["Superwebsite Konglomerasi Enterprise"]
        Gateway["API Gateway & Reverse Proxy Nginx"]
        Monolith["Modular Monolith (Laravel 12 / PHP ^8.4)"]
        EventSpine["Universal Event Spine (Redis Outbox & Kafka)"]
        Database[("PostgreSQL Primary & Standby Replica")]
    end
    subgraph External_Systems["Layanan & Regulator Eksternal"]
        PaymentGateways["Payment Gateways & BI-FAST"]
        GovRegulators["Regulator (OJK, BPOM, DJP Pajak, ESDM)"]
        CloudStorage["S3 Object Storage (DICOM & Dokumen)"]
    end
    UserCons -->|HTTPS / Mobile REST API| Gateway
    UserB2B -->|EDIFACT / REST API v2-v3| Gateway
    UserCorp -->|Web Portal & SSO RBAC| Gateway
    Gateway --> Monolith
    Monolith --> Database
    Monolith --> EventSpine
    Monolith -->|Settlement| PaymentGateways
    Monolith -->|Kepatuhan & Laporan| GovRegulators
    Monolith -->|Arsip Terenkripsi| CloudStorage
```

## 12. Matriks Protokol & Jaringan (Port & Network Matrix)

| Layanan / Komponen | Port | Protokol | Keamanan / Enkripsi | Batasan Akses (Firewall / Subnet) |
|---|---|---|---|---|
| HTTP / HTTPS Public | 80, 443 | TCP / TLS 1.3 | HSTS, Let's Encrypt / Wildcard | Publik (Cloudflare WAF) |
| API v1 / v2 / v3 | 443 | HTTPS REST | HMAC SHA-256 / Bearer Sanctum | Publik berkuota rate limit |
| PostgreSQL Primary | 5432 | TCP | TLS (Mutual TLS cert) | Hanya VPC Internal Aplikasi |
| Redis Cluster & Outbox | 6379, 16379 | TCP | AUTH + TLS | Hanya Subnet VPC Private |
| OCPP 2.0.1 EV Station | 8443 | WSS (WebSocket) | TLS 1.3, Client Certificate | IP Whitelist SPKLU Hub |
| DICOM PACS Image Server | 11112 | TCP / DICOM | TLS DICOM-TLS profile | Subnet Intranet Rumah Sakit |
| IoT Telematics & Fleet | 8883 | MQTTS | TLS 1.3, X.509 Device Cert | Publik Gateway Broker IoT |
| Raft Consensus DR | 7000 | TCP | Raft mTLS encryption | Subnet Inter-DC Dedicated Leased Line |

## 13. Universal Event Spine & Pola Transactional Outbox

Seluruh integrasi asinkron antar-modul diwajibkan menggunakan pola **Transactional Outbox** untuk menjamin *at-least-once delivery* dan konsistensi data transaksional:

1. **Pencatatan Atomik**: Domain event disimpan ke dalam tabel outbox dalam transaksi database yang sama dengan mutasi data bisnis.
2. **Dispatcher Mandiri**: Pekerja latar belakang (`php artisan outbox:dispatch`) membaca event pending dan memancarkannya ke bus pesan.
3. **Idempotensi Penerima**: Modul konsumen mencatat ID event yang telah diproses untuk mencegah pemrosesan duplikat (*idempotent consumer*).
4. **Dead-Letter Queue (DLQ)**: Kegagalan berulang setelah 8 kali percobaan secara otomatis dipindahkan ke tabel DLQ untuk inspeksi tim operasional.
