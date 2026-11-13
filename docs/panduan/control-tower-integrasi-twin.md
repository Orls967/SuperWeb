# Integrasi Digital Twin & Kontrak Lintas Modul — Control Tower (Fase 53)

## 1. Integrasi dengan Simulasi Kernel P3 (`ControlTowerTwinTickHandler`)

`Modules\ControlTower\Infrastructure\Simulation\ControlTowerTwinTickHandler` mengimplementasikan `Modules\Core\Contracts\SimTickHandlerInterface` agar Control Tower dapat berpartisipasi dalam eksekusi deterministik kernel simulasi P3:
- **`name()`**: mengembalikan `'control_tower_twin'`.
- **`shouldRun(CarbonInterface $virtualNow, int $dayNumber)`**: aktif pada setiap hari simulasi (`$dayNumber >= 1`).
- **`handle(CarbonInterface $virtualNow, int $dayNumber, ?callable $commandRunner = null)`**:
  - Menjalankan `WhatIfTwinSimulator::simulate()` secara murni (pure computation) tanpa melakukan `INSERT`, `UPDATE`, atau `DELETE` pada tabel riil (`sct_*`).
  - Mengembalikan ringkasan metrik deterministik (`tick_date`, `day`, `at_risk_orders`, `simulated_otif_bps`, `margin_delta_idr`) untuk perhitungan hash checkpoint simulasi.

## 2. Kontrak Baca Lintas Modul (`StockAvailabilityReader` & `ControlTowerVisibilityReader`)

Untuk mematuhi batas modul `A1` dan `A2`:
- `ControlTowerWmsSyncService` membaca stok fisik gudang **hanya** melalui `Modules\Wms\Contracts\StockAvailabilityReader::getAvailableQuantity($warehouseId, $productId)`.
- Modul lain (seperti `Sales`, `Manufacturing`, atau `Logistics`) yang membutuhkan visibilitas multi-eselon atau net available stock **wajib** menginjeksi `Modules\ControlTower\Contracts\ControlTowerVisibilityReader` yang di-bind ke `ControlTowerVisibilityReaderAdapter` di `ControlTowerServiceProvider`.

## 3. Verifikasi Kepatuhan Arsitektur
- **A1**: Nihil import `Modules\Wms\Domain\*` di dalam `ControlTower`.
- **A2**: Nihil query langsung ke tabel `wms_*` dari `ControlTower`.
- **A3**: Seluruh metrik persentase dan nilai finansial memakai integer basis points (`bps`) atau minor unit IDR (`int`).
- **A8**: Nihil `now()` / `Carbon::now()` di lapisan Domain dan Application (`ClockInterface` diinjeksi).
- **A11**: Seluruh file PHP modul memakai `declare(strict_types=1);`.
- **A12**: Seluruh kolom `*_id` di migrasi `2026_11_13_000001_create_sct_sop_cycles_and_twin_scenarios_tables.php` memiliki constraint foreign key eksplisit dan tipe kolom yang kompatibel dengan MySQL 8.4 (`unsignedBigInteger`).

