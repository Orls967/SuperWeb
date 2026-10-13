# Superwebsite — Enterprise Multi-Business Modular Monolith Platform

Platform ERP terpadu berskala *enterprise* (Ekosistem 45+ Modul) berbasis **Laravel 13 (PHP ^8.4), Blade + Tailwind CSS + Alpine.js**, mengintegrasikan 17 lini bisnis konglomerasi modern di atas pondasi **Double-Entry Multi-Asset Ledger (Zero-Discrepancy)**, **Cryptographic Hash-Chain**, dan **Universal Event Spine**:

1. **Otomotif & Bengkel (Automotive)**: Bengkel Servis Mobil (*AutoServe*), Ensiklopedia (*AutoDex*), Marketplace Suku Cadang & Bursa Mobil C2C (*Store*), Pembiayaan Beragun Kripto (*Finance*).
2. **Keuangan & Kripto (FinTech)**: Core Banking Double-Entry (*Banking*), Payment Hub (*Payment*), Bursa Kripto (*Crypto*), Multi-Currency Treasury (*Treasury*), Escrow B2B & Lelang Surplus (*B2B*).
3. **Kuliner & Waralaba (F&B)**: Sistem Masak & Hidang RM Sari Ranah, BOM/HPP, Dapur Sentral CK-01, POS Kasir, Katering & Royalti Waralaba (*Resto*).
4. **Properti Komersial & EPC (Real Estate & Construction)**: Manajemen Tenant, Leasing Duta Mall, Parkir Gate & Fasilitas (*Mall*), Manajemen Proyek Konstruksi, WBS & Capitalization (*EPC*).
5. **Logistik Multimoda & SCM**: Jaringan Sari Ranah Express, Operasi Darat/Laut, Lacak Balak Hash-Chain (*Logistics*), Supply Chain Control Tower (*ControlTower*), WMS Gudang (*Wms*).
6. **Manufaktur & Distribusi**: Produksi BOM multi-level, Material Requirements Planning (MRP), Kualitas & Pemeliharaan Mesin OEE (*Manufacturing*), Jaringan Distribusi B2B (*Distribution*), Engine Harga & Promosi (*Pricing*).
7. **B2B Ekspor-Impor & Pengadaan (Trade & Procurement)**: RFQ/Tender Pengadaan (*Procurement*), Manajemen Pemasok (*Supplier*), Perdagangan Lintas Batas (*Trade*), Trade Finance & L/C (*TradeFinance*).
8. **Tata Kelola, Agrikultur & Integrasi Enterprise (Corporate Services)**: Kontrak Pertanian & Rantai Dingin IoT (*Agri*), Manajemen Kontrak B2B (*Contract*), Manajemen Aset Tetap PSAK (*Asset*), Konsolidasi Intercompany (*Intercompany*), Kepatuhan & Anggaran (*EnterpriseFinance*), Manajemen Agen & Komisi (*Agency*), Mitigasi Emisi Karbon (*ESG*), Product Lifecycle Management (*PLM*), Tata Kelola Mitra (*Partner*), Kerja Sama Internasional (*International*), Manajemen SDM & Payroll (*HCM*), dan B2B API (*Integration*).
9. **Kesehatan & Farmasi (Healthcare - Hosp)**: Paspor Rekam Medis (EMR) hash-chain, ketersediaan bed isolasi & ICU, dispensing farmasi berbasis contraindication engine, tagihan terpadu BPJS/asuransi/pasien, dan medical tourism packages.
10. **Hiburan & Venue (Entertainment & Venue - Ven)**: Ticketing digital non-fungible hash-chain, access control RFID/QR gate, manajemen kapasitas zona crowd safety, VIP table escrow, festival bundle packages, dan door-share artist settlement.
11. **Perhotelan & Resort (Hospitality & Hotel - Htl)**: Property Management System (PMS) multi-properti, dynamic rate protection, smart lock keyless QR, HVAC energy digital twin setback, folio addon checkout, dan timeshare fractional ownership yield.
12. **Pertambangan & Alat Berat (Mining - Min)**: Rencana tambang, automated fleet dispatch, telemetri payload sensor, timbangan jembatan weighbridge hash-chain, akrual royalti PNBP komoditas, dan audit izin kerja HSE berisiko tinggi.
13. **Energi & Utilitas Listrik (Energy - Egy)**: Pembangkit EBT (PLTS/PLTB/PLTU), transmisi SCADA, Automated Grid Dispatch, microgrid islanding darurat, SPKLU smart metering kWh, dan sertifikat energi hijau REC hash-chain.
14. **Telekomunikasi & Jaringan (Telecom & ISP - Tlx)**: Network Operations Center (NOC), fiber & 5G tower, data center rack space leasing, PUE cooling twin, RADIUS bandwidth throttling, dan wholesale IP transit carrier settlement.
15. **Media & Industri Kreatif (Media & Content - Med)**: Stage-gate pipeline produksi konten, DRM watermark tamper-evident, distribusi multi-channel SVOD/linear broadcast, atribusi royalti artis/kreator, dan sponsorship escrow.
16. **Pendidikan & Sertifikasi (Education & EdTech - Edu)**: Student lifecycle, cohort live batch class, grading engine, credential sertifikasi digital hash-chain, evaluasi akreditasi kurikulum, dan tuition split scholarship fee.
17. **Ritel Omnichannel & Q-Commerce (Retail & Store - Ret)**: Multi-channel Order Management System (OMS), smart replenishment inventory allocation, dark store picker wave dispatch, q-commerce delivery routing, dan loyalty points sync.

---

## 🏛️ Arsitektur Sistem

Platform ini dibangun dengan pola **Modular Monolith** yang ketat di direktori `modules/`:

```
modules/                     # 38 Modular Monolith Domains
├── Agency/                  # Manajemen agen, komisi bertingkat, atribusi
├── Agri/                    # Pertanian kontrak (plasma), grading, log cold-chain IoT
├── Asset/                   # Aset tetap, depresiasi PSAK 16/73, revaluasi, TCO
├── AutoDex/                 # Ensiklopedia mobil, garasi virtual, wishlist
├── AutoServe/               # Operasional bengkel & smart repair escrow
├── B2b/                     # Katalog grosir, lelang surplus, escrow pembayaran B2B
├── Banking/                 # Double-entry multi-asset ledger, 140 akun seimbang
├── Contract/                # Manajemen kontrak, hash-chain clause, addendum
├── ControlTower/            # S&OP, alokasi ATP/CTP, alert disrupsi rantai pasok
├── Core/                    # RBAC (32 role), Paspor hash-chain, DocumentStore, Outbox
├── Crypto/                  # Engine kuotasi harga kripto, dompet aset digital
├── Distribution/            # Ekosistem B2B distributor, limit kredit, rebate
├── EnterpriseFinance/       # Anggaran grup, enkumbrans, kepatuhan perpajakan/SoD
├── Epc/                     # Konstruksi (Engineering, Procurement, Construction), MC
├── Esg/                     # Emisi karbon GHG, offset trading, skor pemasok
├── Finance/                 # Pembiayaan HODL-to-Drive, margin call, LTV monitor
├── Hcm/                     # SDM, penggajian PPh 21, BPJS, alokasi jam kerja manufaktur
├── Integration/             # B2B API v2, EDIFACT parser, webhook HMAC delivery
├── Intercompany/            # Transaksi antar-entitas, transfer pricing, eliminasi konsolidasi
├── International/           # Joint venture lintas yurisdiksi, OEM, royalti tax treaty
├── Inventory/               # Inventori terpusat, reservasi dua langkah, pergerakan stok
├── Logistics/               # Sari Ranah Express (multimoda laut/darat), custody lacak balak
├── Mall/                    # Duta Mall (leasing, utilitas, parkir, loyalty PTS)
├── Manufacturing/           # Produksi (BOM, Routing, MRP, SPC Quality, OEE Maintenance)
├── Partner/                 # Mitra strategis, bagi hasil (revenue share), due diligence
├── Party/                   # Master Data Party, KYC, badan hukum, credit scoring
├── Payment/                 # Hub pembayaran terpusat (charge, hold, capture, refund)
├── Plm/                     # Product Lifecycle Management, R&D, Lab Notebook
├── Pricing/                 # Engine harga dinamis, diskon waterfall, promosi
├── Procurement/             # PR, RFQ, Tender blind-bidding, PO, GR/IR 3-way match
├── Resto/                   # RM Sari Ranah (Padang hidang, HPP, dapur sentral, POS)
├── Shared/                  # UI components, value objects (Money), BaseAction
├── Store/                   # Marketplace suku cadang & bursa C2C e-commerce
├── Supplier/                # Sertifikasi, evaluasi kinerja (scorecard), SCAR
├── Trade/                   # Ekspor-Impor, Incoterms, kalkulasi Landed Cost (HS Code)
├── TradeFinance/            # Letter of Credit (L/C), Bank Guarantee, SCF
├── Treasury/                # Hedging kurs, cash pooling, rekonsiliasi mutasi bank
└── Wms/                     # Manajemen Gudang tingkat lanjut, putaway, wave picking
```

### Prinsip Integritas Moneter & Kriptografis
- **Double-Entry Ledger (Zero Discrepancy)**: Setiap mutasi keuangan (rupiah, kripto, atau poin) dicatat secara berpasangan debet-kredit yang seimbang. Terverifikasi melalui `php artisan bank:reconcile`.
- **Vehicle Passport (Append-Only Hash Chain)**: Setiap peristiwa kepemilikan dan servis kendaraan disegel dalam rantai hash SHA-256 yang kebal manipulasi. Terverifikasi melalui `php artisan core:verify-passports`.
- **Isolasi Batas Modul (Clean Boundaries)**: Modul bisnis tidak boleh mengimpor domain modul lain secara langsung; komunikasi antar-modul hanya diizinkan melalui **Contracts** dan **Events**. Ditegakkan melalui *Arch Tests* Pest.

---

## 🚀 Panduan Instalasi & Menjalankan

### Persyaratan Sistem
- **PHP**: `^8.4` (minimal 8.4.1, sesuai `composer.lock`) dengan ekstensi `ctype`, `dom`, `fileinfo`, `filter`, `iconv`, `mbstring`, `openssl`, `pdo_sqlite`, `session`, `simplexml`, `tokenizer`, `xml`, `xmlwriter` (+ `bcmath` disarankan untuk `brick/math`; `gd` dengan dukungan JPEG wajib untuk menjalankan test karena `UploadedFile::fake()->image()`). Jangan memakai `--ignore-platform-reqs`.
- **Composer**: $\ge$ 2.5
- **Node.js**: $\ge$ 18.x & **NPM**

### Langkah Instalasi
```bash
# 1. Clone repositori & masuk direktori
cd autoserve

# 2. Install dependensi PHP & JavaScript
composer install
npm install

# 3. Konfigurasi Environment
cp .env.example .env
php artisan key:generate

# 4. Migrasi & Seed Database Lengkap
php artisan migrate:fresh --seed

# 5. Build Aset Frontend
npm run build
```

### Menjalankan Server Pengembangan
```bash
# Jalankan Vite & Server Laravel
npm run dev &
php artisan serve
```
Akses platform di peramban: `http://127.0.0.1:8000`.

---

## 👥 Akun Demo & Akses Peran (Roles)

Seluruh akun telah disiapkan dengan kata sandi default `password` dan PIN Dompet `123456`:

| Role / Jabatan | Alamat Email | Kata Sandi | PIN Dompet | Ruang Lingkup Akses |
|---|---|---|---|---|
| **Super Admin / Holding** | `admin@autoserve.test` | `password` | `123456` | Akses penuh ke seluruh modul, Dashboard P&L Grup, Kesehatan Sistem, dan Manajemen Platform |
| **Mekanik Bengkel** | `mekanik@autoserve.test` | `password` | `123456` | Estimasi servis, pengerjaan booking bengkel, alokasi sparepart |
| **Customer / Konsumen** | `customer@autoserve.test` | `password` | `123456` | My Garage, booking servis, belanja onderdil, jual-beli mobil C2C, trading kripto, pinjaman HODL-to-Drive |
| **Demo Customers (20 Akun)** | `customer01@autoserve.test` s/d `customer20@autoserve.test` | `password` | `123456` | 20 customer aktif dengan saldo dompet IDR, kendaraan berpaspor digital, riwayat servis bengkel, portofolio kripto & pinjaman HODL-to-Drive |
| **Manajer Outlet Resto** | `resto.manager@autoserve.test` | `password` | `123456` | Dapur sentral CK-01, POS hidang, stok opname, order transfer, pembatalan VOID pesanan |
| **Tenant Duta Mall** | `tenant@autoserve.test` | `password` | `123456` | Portal Mandiri Tenant, tagihan sewa/utilitas, lapor omzet bulanan, izin kerja lembur |
| **Logistics Admin (Sari Ranah Express)** | `logistics.admin@autoserve.test` | `password` | `123456` | Manajemen penuh jaringan logistik multimoda, armada, kru, rate card, dan audit operasi |
| **Dispatcher Logistik (3 Akun)** | `dispatcher01@autoserve.test` s/d `dispatcher03@autoserve.test` | `password` | `123456` | Papan dispatch trip, penugasan armada truk & driver sesuai kelas SIM dan batasan UU 22/2009 |
| **Operator Hub Logistik (4 Akun)** | `hub.bdj@autoserve.test`, `hub.bjb@autoserve.test`, `hub.pky@autoserve.test`, `hub.bpn@autoserve.test` | `password` | `123456` | Operasi inbound/outbound hub, scan resi, sorting paket, dan pemantauan kontainer depot/CFS |
| **Driver Ekspedisi (12 Akun)** | `driver01@autoserve.test` s/d `driver12@autoserve.test` | `password` | `123456` | Aplikasi tugas driver, scan pickup, rute pengantaran, dan pelaporan POD (Proof of Delivery) |
| **Shipper Bisnis B2B (5 Akun)** | `shipper01@autoserve.test` s/d `shipper05@autoserve.test` | `password` | `123456` | Portal Shipper, pembuatan resi, upload massal CSV, cetak label QR, dan pembayaran prabayar/invoice |

---

## 🛠️ Daftar Artisan Commands

Platform menyediakan rangkaian Artisan Command untuk otomatisasi operasional dan audit kepatuhan:

### 1. Diagnosa & Observabilitas
| Command | Deskripsi |
|---|---|
| `php artisan chain:audit-all` | Orkestrasi 18 audit rantai nilai global ekosistem secara menyeluruh (0 diskrepansi) |
| `php artisan super:health-check` | Memindai kesehatan 10 pilar platform (DB, Cache, Storage, Ledger, Passport, Mall Billing, Resto Shift, Logistik Kustodi & Billing, Aset Subledger & Hash Chain, Health Matrix) |
| `php artisan bank:reconcile` | Memverifikasi seluruh saldo akun buku besar double-entry (140 akun seimbang, 0 diskrepansi saldo) |
| `php artisan core:verify-passports` | Memvalidasi keabsahan kriptografis rantai hash-chain Paspor Kendaraan |
| `php artisan mall:audit-billing` | Mengaudit keselarasan seluruh penagihan invoice mall terhadap pendapatan buku besar |
| `php artisan lgx:audit-billing` | Mengaudit keselarasan 15 titik penagihan logistik vs saldo buku besar (0 selisih) |
| `php artisan lgx:verify-custody` | Memvalidasi keabsahan kriptografis rantai lacak balak (Chain of Custody) logistik |
| `php artisan lgx:capacity-check` | Memverifikasi alokasi kapasitas jadwal operasi multimoda |

### 2. Kuliner RM Sari Ranah
| Command | Deskripsi |
|---|---|
| `php artisan resto:close-day {--check}` | Tutup harian outlet: alihkan sisa etalase ke waste, tutup shift kasir, dan validasi ledger |
| `php artisan resto:post-royalty` | Hitung dan posting royalti waralaba bulanan ke pendapatan holding |
| `php artisan resto:check-stock` | Pantau stok bahan baku minim dan buat draf Purchase Order otomatis |

### 3. Properti Duta Mall
| Command | Deskripsi |
|---|---|
| `php artisan mall:generate-invoices` | Terbitkan invoice sewa bulanan dan tarik omzet tenant otomatis via `TenantSalesProvider` |
| `php artisan mall:auto-debit` | Eksekusi auto-debit pelunasan invoice dari saldo dompet tenant |
| `php artisan mall:apply-penalties` | Terapkan denda keterlambatan (2% flat) pada invoice yang melewati jatuh tempo |
| `php artisan mall:renew-parking-members` | Perpanjangan otomatis langganan parkir bulanan tenant & member |
| `php artisan mall:settle-vouchers` | Cairkan klaim voucher belanja tenant pada siklus settlement mingguan |
| `php artisan mall:expire-vouchers` | Bukukan voucher kedaluwarsa ke pendapatan *breakage* holding |
| `php artisan mall:generate-pm` | Terbitkan work order pemeliharaan preventif fasilitas gedung |

### 4. Logistik Multimoda Sari Ranah Express (SRX)
| Command | Deskripsi |
|---|---|
| `php artisan lgx:invoice-shippers` | Terbitkan invoice bulanan untuk akun shipper pascabayar B2B |
| `php artisan lgx:settle-cod` | Cairkan setoran COD dari pos kasir hub ke dompet shipper (D+N) |
| `php artisan lgx:pay-carriers` | Bayar tagihan jasa carrier subkontrak yang melewati batas termin |
| `php artisan lgx:accrue-dd` | Akrual harian denda Demurrage & Detention kontainer di lokasi |
| `php artisan lgx:detect-late` | Deteksi pengiriman yang berpotensi atau telah melewati target SLA |
| `php artisan lgx:retry-webhooks` | Kirim ulang webhook outbox yang gagal dengan exponential backoff |

### 6. Audit & Kepatuhan 17 Lini Bisnis (`*:audit` & `verify-*`)
| Command | Deskripsi |
|---|---|
| `php artisan bank:reconcile` | Rekonsiliasi buku besar double-entry 0 selisih |
| `php artisan super:health-check` | Pengecekan komprehensif kesehatan seluruh pilar sistem |
| `php artisan core:verify-passports` | Verifikasi kriptografis hash-chain paspor kendaraan |
| `php artisan mall:audit-billing` | Audit integrasi tagihan tenant Duta Mall vs ledger |
| `php artisan lgx:audit-billing` & `lgx:verify-custody` | Audit freight billing dan integritas lacak balak Logistik |
| `php artisan egy:audit` | Audit kestabilan grid dispatch, frekuensi, & smart meter |
| `php artisan tlx:audit` | Audit availability SLA telko, SLA credit, & transit margin |
| `php artisan med:audit` | Audit royalti media, DRM watermark chain, & sponsorship escrow |
| `php artisan edu:audit` | Audit integritas sertifikat digital hash-chain & tuition split |
| `php artisan ret:audit` | Audit multi-channel OMS, dark store picking wave, & stock leak |
| `php artisan security:audit` | Audit Zero Trust service registry & least-privilege token |
| `php artisan dr:audit` | Audit ketahanan multi-region DR drill & data residency |
| `php artisan api:audit` | Audit Platform Economy: API usage billing & embedded finance |
| `php artisan ecosystem:audit-12-lines` | Audit lintas lini ekosistem terpadu 0 diskrepansi |

---

## 🚀 Kernel Simulasi & Seeder Ultra Gelombang 2

Platform menyediakan kernel simulasi kompresi waktu 365 hari dan seeder deterministik berkapasitas ultra:

```bash
# Seeder Skala Penuh 17 Lini Bisnis
php artisan db:seed --class="Database\Seeders\SeventeenLinesUltraSeeder"

# Jalankan Kompresi Waktu Simulasi 12 Bulan Konglomerasi
php artisan test modules/Integration/tests/Feature/MegaScenarioTest.php
```

## 🧪 Pengujian & Uji Kualitas (Quality Gates)

Platform dilengkapi rangkaian automated test komprehensif (**931+ Tests, 4778+ Assertions**, 0 skipped):

```bash
# 1. Jalankan seluruh test suite
php artisan test

# 2. Verifikasi batas modul & arsitektur (Arch Tests)
php artisan test tests/Architecture/ModuleBoundariesTest.php

# 3. Uji integrasi lintas lini bisnis end-to-end
php artisan test tests/Feature/CrossLineIntegrationTest.php modules/Logistics/tests/Feature/CrossLineIntegrationTest.php

# 4. Uji anggaran query SQL performa tinggi
php artisan test tests/Performance/QueryBudgetTest.php

# 5. Uji ketahanan keamanan (IDOR, Mass Assignment, PIN Lockout, XSS, Signed URL, Sanctum, Webhooks)
php artisan test tests/Feature/SecurityTest.php

# 6. Uji regresi race/idempotensi Action (retry ganda, refund ganda, alokasi ganda)
php artisan test tests/Feature/ActionConcurrencyRegressionTest.php

#7. Uji aksesibilitas rute per role (Smoke Test)
php artisan test tests/Feature/RouteSmokeTest.php

#8. Format kode sesuai standar Laravel Pint
vendor/bin/pint --test
```

---

## 📊 Seeder Demo Skala Besar (Stress-Test Data)

Untuk memvalidasi kesiapan operasional pada beban data tinggi, jalankan:

```bash
# Seeder Skala Besar Kuliner & Mall
php artisan db:seed --class=DemoLargeSeeder

# Seeder Skala Besar Logistik Multimoda
php artisan db:seed --class="Modules\Logistics\database\seeders\LogisticsLargeSeeder"
```
Seeder ini menginisialisasi:
- **Resto & Mall**: 3 Outlet Resto, 60 Tenant, 12 Bulan Billing, dan 150.000 Sesi Parkir dalam < 4 detik.
- **Logistik Multimoda**: 300+ truk berpaspor, 20+ kapal laut ber-IMO, 5.000+ kontainer ISO 6346, ratusan ribu shipment dan jutaan event kustodi dengan penegakan ledger akurat.
- Keseimbangan ledger dan audit billing tetap terverifikasi **0 selisih**.

---

## 🌐 API v1 Logistik (Laravel Sanctum)

API v1 Sari Ranah Express (`/api/v1/logistics`) melayani integrasi mitra B2B:
- **Autentikasi**: bearer token Sanctum asli (`Authorization: Bearer <token>`), diterbitkan lewat **Profil → API Tokens** (pilih abilities, kedaluwarsa opsional, cabut kapan saja).
- **Abilities**: `quote:create`, `shipment:create`, `shipment:read`, atau `*`.
- **Endpoint publik**: `GET /api/v1/logistics/tracking/{tracking_number}` (rate limit 30/menit, tanpa token).
- **Endpoint terotentikasi**: `POST /quotes`, `POST /shipments` (header `Idempotency-Key`), `GET /shipments`, `GET /shipments/{tracking_number}` (rate limit 60/menit).
- **Enforcement**: tanpa token 401, ability tidak cocok 403, token dicabut/kedaluwarsa 401, session cookie web tidak diterima sebagai autentikasi API.

Dokumentasi lengkap: [`docs/API.md`](docs/API.md).
