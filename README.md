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
| **Manajer Outlet Resto** | `resto.manager@autoserve.test` | `password` | `123456` | Dapur sentral CK-01, POS hidang, stok opname, order transfer, pembatalan VOID pesanan |
| **Tenant Duta Mall** | `tenant@autoserve.test` | `password` | `123456` | Portal Mandiri Tenant, tagihan sewa/utilitas, lapor omzet bulanan, izin kerja lembur |

---

## 🛠️ Daftar Artisan Commands

Platform menyediakan rangkaian Artisan Command untuk otomatisasi operasional dan audit kepatuhan:

### 1. Diagnosa & Observabilitas
| Command | Deskripsi |
|---|---|
| `php artisan super:health-check` | Memindai kesehatan 7 pilar platform (DB, Cache, Storage, Ledger, Passport, Mall Billing, Resto Shift) |
| `php artisan bank:reconcile` | Memverifikasi seluruh saldo akun buku besar double-entry (0 diskrepansi saldo) |
| `php artisan core:verify-passports` | Memvalidasi keabsahan kriptografis rantai hash-chain Paspor Kendaraan |
| `php artisan mall:audit-billing` | Mengaudit keselarasan seluruh penagihan invoice mall terhadap pendapatan buku besar |

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

### 4. Otomotif, Toko & Finansial
| Command | Deskripsi |
|---|---|
| `php artisan crypto:tick` | Simulasikan fluktuasi harga pasar kripto real-time |
| `php artisan finance:monitor-risk` | Pantau LTV pinjaman agunan kripto, kirim peringatan margin call, dan eksekusi likuidasi |
| `php artisan store:cancel-stale-orders` | Batalkan reservasi pesanan e-commerce yang tidak dibayar dalam 2 jam |
| `php artisan payment:release-expired-holds` | Lepaskan dana escrow yang kedaluwarsa kembali ke dompet pembeli |

---

## 🧪 Pengujian & Uji Kualitas (Quality Gates)

Platform dilengkapi rangkaian automated test komprehensif (**297 Tests, 1317 Assertions**):

```bash
# 1. Jalankan seluruh test suite
php artisan test

# 2. Verifikasi batas modul & arsitektur (Arch Tests)
php artisan test tests/Architecture/ModuleBoundariesTest.php

# 3. Uji integrasi lintas lini bisnis end-to-end
php artisan test tests/Feature/CrossLineIntegrationTest.php

# 4. Uji anggaran query SQL performa tinggi
php artisan test tests/Performance/QueryBudgetTest.php

# 5. Uji ketahanan keamanan (IDOR, Mass Assignment, PIN Lockout, XSS, Signed URL)
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
php artisan db:seed --class=DemoLargeSeeder
```
Seeder ini menginisialisasi:
- **3 Outlet Resto**: Dapur Sentral Veteran (CK-01), Mall Outlet Duta Mall (DM-01), Cabang Dine-In Kayutangi (KD-01).
- **60 Tenant & Unit Mall**: Tersebar di LG, GF, L1, L2 dengan kontrak sewa aktif (*fixed*, *revenue share*, *greater of*).
- **12 Bulan Data Penagihan Historis**: Invoice penagihan sewa dan utilitas terverifikasi bersih tanpa diskrepansi ledger.
- **150.000 Sesi Parkir Riil**: Disisipkan dalam chunk transaksi berkinerja tinggi dalam **< 4 detik**.
- Keseimbangan ledger dan audit billing tetap terverifikasi **0 selisih**.
