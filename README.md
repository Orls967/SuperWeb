# Superwebsite — Enterprise Multi-Business Modular Monolith Platform

Platform terpadu berskala *enterprise* berbasis **Laravel 11, Blade + Tailwind CSS + Alpine.js**, mengintegrasikan 5 pilar lini bisnis konglomerasi modern di atas pondasi **Double-Entry Multi-Asset Ledger** dan **Cryptographic Hash-Chain**:

1. **Otomotif & Bengkel**: Bengkel Servis Mobil (*AutoServe*), Ensiklopedia & Garasi (*AutoDex*), Toko Onderdil & Bursa Mobil Bekas C2C (*Store*).
2. **Keuangan & Kripto**: Core Banking Buku Besar Dua Sisi (*Banking*), Payment Hub (*Payment*), Bursa Kripto Simulasi (*Crypto*), dan Pembiayaan Beragun Kripto *HODL-to-Drive* (*Finance*).
3. **Kuliner Tradisional Padang (RM Sari Ranah)**: Sistem Masak & Hidang Otentik, Resep Berlapis BOM, Kalkulasi HPP & Varian Produksi, Manajemen Etalase & Resirkulasi, POS Kasir Multi-Shift, Rantai Pasok & Dapur Sentral, Katering & Pengantaran, Royalti Waralaba, serta Analitik Menu Matrix BCG (*Resto*).
4. **Properti Komersial & Pusat Belanja (Duta Mall)**: Manajemen Tenant & Leasing (*Fixed*, *Revenue Share*, *Greater-Of*), Penagihan Sewa & Utilitas, Sistem Parkir Gate Progresif Terintegrasi Paspor Kendaraan, Program Loyalitas Duta Points (`PTS`), Voucher Belanja Mall (*Breakage Accounting*), Pemesanan Ruang Atrium Event, dan Manajemen Fasilitas (*Preventive Maintenance* & *Work Orders*) (*Mall*).
5. **Konsolidasi Holding & Observabilitas**: Integrasi omzet tenant otomatis lintas lini, validasi parkir di kasir resto, poin/voucher belanja lintas ekosistem, pembatalan akses parkir otomatis saat kendaraan berpindah tangan, Dashboard P&L Grup Konsolidasi real-time ($\le 30$ query), Navigasi Global `Ctrl+K`, dan Diagnosa Kesehatan Sistem 7 Pilar (`super:health-check`) (*Core* & *Shared*).

---

## 🏛️ Arsitektur Sistem

Platform ini dibangun dengan pola **Modular Monolith** yang ketat di direktori `modules/`:

```
modules/
├── AutoServe/      # Operasional bengkel & smart repair escrow
├── AutoDex/        # Ensiklopedia mobil, garasi virtual, wishlist
├── Banking/        # Double-entry multi-asset ledger, dompet digital, mutasi
├── Payment/        # Central Payment Hub (charge, hold, capture, release, refund)
├── Inventory/      # Pelacak mutasi stok barang dan bahan baku terpusat
├── Store/          # Marketplace suku cadang & bursa mobil bekas C2C
├── Crypto/         # Engine kuotasi harga kripto, dompet aset digital & trading
├── Finance/        # Pembiayaan mobil beragun kripto (HODL-to-Drive), LTV monitor
├── Resto/          # RM Sari Ranah (Padang hidang, HPP, POS kasir, supply chain)
├── Mall/           # Duta Mall (leasing, utilitas, parkir, loyalty PTS, fasilitas)
├── Core/           # Registri kendaraan, paspor hash-chain, notifikasi, observabilitas
└── Shared/         # UI components, value objects (Money), MenuRegistry
```

### Prinsip Integritas Moneter & Kriptografis
- **Double-Entry Ledger (Zero Discrepancy)**: Setiap mutasi keuangan (rupiah, kripto, atau poin) dicatat secara berpasangan debet-kredit yang seimbang. Terverifikasi melalui `php artisan bank:reconcile`.
- **Vehicle Passport (Append-Only Hash Chain)**: Setiap peristiwa kepemilikan dan servis kendaraan disegel dalam rantai hash SHA-256 yang kebal manipulasi. Terverifikasi melalui `php artisan core:verify-passports`.
- **Isolasi Batas Modul (Clean Boundaries)**: Modul bisnis tidak boleh mengimpor domain modul lain secara langsung; komunikasi antar-modul hanya diizinkan melalui **Contracts** dan **Events**. Ditegakkan melalui *Arch Tests* Pest.

---

## 🚀 Panduan Instalasi & Menjalankan

### Persyaratan Sistem
- **PHP**: $\ge$ 8.2 (ekstensi `sqlite3`, `bcmath`, `curl`, `mbstring`, `pdo`)
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
| `php artisan super:health-check` | Memindai kesehatan 8 pilar platform (DB, Cache, Storage, Ledger, Passport, Mall Billing, Resto Shift, Logistik Kustodi & Billing) |
| `php artisan bank:reconcile` | Memverifikasi seluruh saldo akun buku besar double-entry (0 diskrepansi saldo) |
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

### 5. Otomotif, Toko & Finansial
| Command | Deskripsi |
|---|---|
| `php artisan crypto:tick` | Simulasikan fluktuasi harga pasar kripto real-time |
| `php artisan finance:monitor-risk` | Pantau LTV pinjaman agunan kripto, kirim peringatan margin call, dan eksekusi likuidasi |
| `php artisan store:cancel-stale-orders` | Batalkan reservasi pesanan e-commerce yang tidak dibayar dalam 2 jam |
| `php artisan payment:release-expired-holds` | Lepaskan dana escrow yang kedaluwarsa kembali ke dompet pembeli |

---

## 🧪 Pengujian & Uji Kualitas (Quality Gates)

Platform dilengkapi rangkaian automated test komprehensif (**545 Tests, 3203 Assertions**, 0 skipped):

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

# 6. Uji aksesibilitas rute per role (Smoke Test)
php artisan test tests/Feature/RouteSmokeTest.php

# 7. Format kode sesuai standar Laravel Pint
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
