# AutoServe — Superwebsite

Sistem Bengkel Otomotif Terpadu (Modular Monolith) dibangun dengan **Laravel 11, Blade + Tailwind CSS + Alpine.js, Laravel Breeze** dengan role **Admin**, **Mekanik**, dan **Customer**.

## Modul

| Modul | Deskripsi |
|-------|-----------|
| **AutoServe** | Manajemen bengkel: booking servis, invoice, mekanik assignment |
| **AutoDex** | Ensiklopedia mobil global, My Garage, Wishlist |
| **Banking** | Double-entry ledger, dompet digital, transfer P2P, mutasi |
| **Payment** | Payment Hub (charge, hold/capture/release, refund) |
| **Inventory** | Stock movement tracking terpusat |
| **Store** | Toko online (sparepart, aksesoris, mobil bekas C2C) |
| **Crypto** | Exchange kripto simulasi (BTC, ETH, SOL) + price alerts |
| **Finance** | HODL-to-Drive crypto-backed financing, cicilan, LTV monitoring |
| **Core** | Vehicle registry, Vehicle Passport (hash-chain), Notifications, Activity Feed, Dashboard terpadu |
| **Shared** | Komponen UI, BaseAction, MenuRegistry, value objects |

## Arsitektur

Proyek ini menggunakan pola **Modular Monolith** — setiap modul berada di `modules/{Modul}/` dengan struktur:

```
modules/{Modul}/
├── Application/       # Actions, Services, Listeners
├── Contracts/         # Interface untuk integrasi lintas modul
├── Console/           # Artisan commands
├── Domain/
│   ├── Models/        # Eloquent models
│   ├── Enums/         # PHP enums
│   └── Events/        # Domain events
├── Http/Controllers/  # HTTP layer
├── database/
│   ├── migrations/
│   └── seeders/
├── resources/views/   # Blade templates (namespaced)
├── routes/web.php     # Module routes
├── tests/Feature/     # Pest tests
└── {Modul}ServiceProvider.php
```

## Integritas Data

- **Double-Entry Ledger**: Setiap transaksi finansial (top-up, transfer, bayar, beli, trading, escrow, cicilan) menggunakan posting dua sisi atomik melalui `LedgerService`. Validasi: `php artisan bank:reconcile`
- **Vehicle Passport**: Hash-chain SHA-256 append-only yang merekam riwayat kendaraan (akuisisi, servis, pergantian part, penjualan). Validasi: `php artisan core:verify-passports`

## Akun Demo

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@autoserve.test | password |
| Mekanik | mekanik@autoserve.test | password |
| Customer | customer@autoserve.test | password |

PIN Dompet: `123456`

## Quick Start

```bash
# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Database (MySQL)
php artisan migrate:fresh --seed

# Development server
npm run dev          # Vite (terminal 1)
php artisan serve    # Laravel (terminal 2)
```

## Commands

| Command | Deskripsi |
|---------|-----------|
| `php artisan bank:reconcile` | Verifikasi keseimbangan double-entry ledger |
| `php artisan core:verify-passports` | Audit integritas hash-chain kendaraan |
| `php artisan crypto:tick` | Generate random price tick untuk aset kripto |
| `php artisan finance:charge-installments` | Proses cicilan jatuh tempo |
| `php artisan finance:monitor-ltv` | Evaluasi LTV pinjaman kripto |
| `php artisan store:cancel-stale-orders` | Batalkan pesanan pending > 24 jam |
| `php artisan payment:release-expired-holds` | Lepas hold yang expired |

## Testing

```bash
php artisan test                    # Run all tests (183 tests, 737 assertions)
php artisan test --filter=Banking   # Run per-module
```

## Tech Stack

- Laravel 11 + PHP 8.3+
- Blade + Tailwind CSS + Alpine.js
- Laravel Breeze (auth)
- Pest (testing)
- brick/math (financial precision)
- MySQL 8+ (production) / SQLite (testing)
