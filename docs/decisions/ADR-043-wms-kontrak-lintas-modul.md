# ADR-043: Pemisahan Kontrak Lintas Modul (Wms, Inventory, Logistics, EnterpriseFinance)

- **Status**: Diterima
- **Tanggal**: 2026-11-13
- **Konteks**: Fase 41 (Gudang & Pusat Distribusi — WMS Lanjutan)
- **Penulis**: Pelaksana [W8-P6]

---

## 1. Masalah yang Diselesaikan
Modul `Wms` berada di persimpangan tiga modul lain:
1. **`Inventory`**: mengelola saldo stok global produk (`inv_stock_movements`).
2. **`Logistics`**: mengelola pengiriman eksternal (`lgx_shipments`) dan jadwal kurir.
3. **`EnterpriseFinance`**: mengelola penutupan periode akuntansi (*period closing*).

Jika `Wms` mengimpor model Domain milik `Inventory` atau `Logistics` secara langsung, aturan arsitektur **A1** dan **A2** akan dilanggar serta menciptakan *tight coupling* antar-modul.

---

## 2. Keputusan Arsitektur
Setiap integrasi lintas modul wajib melalui antarmuka kontrak (*Port & Adapter*) berikut:
1. **`StockAvailabilityReader`** (`Modules\Wms\Contracts`) → diimplementasikan oleh `StockReservationService` agar modul `Store` / `Order` dapat membaca stok tersedia netto tanpa menyentuh `wms_bin_stocks`.
2. **`WarehouseValuationReader`** (`Modules\Wms\Contracts`) → diimplementasikan oleh `WarehouseValuationReaderAdapter` untuk membaca nilai subledger vs saldo buku besar per gudang.
3. **`InventoryBalanceProvider`** (`Modules\Inventory\Contracts`) → diimplementasikan oleh `InventoryBalanceProviderAdapter` (di dalam modul `Inventory` sendiri) agar `InventoryReconciliationService` di `Wms` tetap bersih dari aturan A1/A2.
4. **`InTransitShipmentProvider`** & **`CarrierCutoffProvider`** (`Modules\Logistics\Contracts`) → diimplementasikan oleh adapter di modul `Logistics` untuk memasok data `lgx_shipments` dan jam cut-off kurir ke `SweepInTransitAction` dan `DockAppointmentScheduler`.
5. **`SubledgerTotalProvider`** (`Modules\Intercompany\Contracts`) → diimplementasikan oleh `WmsSubledgerTotalAdapter` dan ditandai (`app->tag(..., SubledgerTotalProvider::class)`) di `WmsServiceProvider` untuk `EnterpriseFinance`.

---

## 3. Konsekuensi
- `php artisan arch:scan` mencatat **0 pelanggaran A1 dan 0 pelanggaran A2 baru**.
- Setiap modul tetap dapat diuji secara terisolasi maupun terintegrasi penuh melalui *dependency injection* container Laravel.
