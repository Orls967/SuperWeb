# Panduan Simulasi Deterministik S2 & Tier Skala T1 WMS

Dokumen ini menjelaskan cara menjalankan simulasi deterministik tingkat **S2** dan memverifikasi kelengkapan data skala **T1** pada modul `Wms`.

---

## 1. Spesifikasi Tingkat Simulasi S2
Modul `Wms` beroperasi pada tingkat simulasi **S2**:
- Seluruh data gudang, rak, bin, stok, dan biaya bersifat deterministik dan berversi.
- Tidak ada ketergantungan pada *feed* sensor waktu-nyata (*real-time*), *webhook* eksternal, atau nilai acak (`rand()`, `Str::uuid()` pada kunci bisnis).
- Waktu simulasi selalu dapat dikendalikan melalui `ClockInterface` (di pengujian menggunakan `CarbonImmutable::setTestNow()`).

---

## 2. Spesifikasi Tier Skala T1 (Seeded Benchmark)
Untuk pengujian skala dan validasi performa di lingkungan uji/staging, dataset T1 WMS mencakup minimal:
- **3 Gudang (DC)** (`DC-JKT`, `DC-SBY`, `DC-BJM`)
- **9 Zona** (masing-masing `putaway`, `pick`, `storage` per gudang)
- **20 Rak** tersebar di seluruh zona
- **100 Bin** dengan kapasitas integer terukur
- **500 baris `wms_bin_stocks`** dengan `qty_base`, `lot_number`, `expiry_date`, dan `stock_status`
- **200 tugas `wms_tasks`** dengan transisi `WarehouseTaskStateMachine`
- **50 transfer antar-gudang**
- **10 sesi `cycle_count`**
- **20 permintaan `replenishment`**

---

## 3. Langkah Eksekusi Simulasi Normal & Kejutan (L.4)
1. **Simulasi Normal**:
   - Jalankan `PostOpeningBalanceAction` untuk setiap gudang → pastikan `discrepancy_idr === 0`.
   - Jalankan `ReceiveInboundShipmentAction` (X41-1 `within_tolerance`) → pastikan stok bin bertambah dan D41-2 terposting.
   - Jalankan `ExecuteDirectedPutawayAction` (H1) dan `AllocateOutboundWavePickingAction` (H2 + X41-2).
   - Jalankan `RevalueInventoryCostAction` (D41-10/D41-11) → pastikan `WarehouseValuationReader` tetap seimbang (`discrepancy_idr === 0`).
2. **Simulasi Kejutan**:
   - Injeksi penerimaan `over_receipt` (`> +500 bps`) tanpa persetujuan supervisor → verifikasi sistem menolak dengan `DomainException`.
   - Injeksi permintaan putaway melebihi kapasitas total → verifikasi `PutawaySlotter` melempar `NO_CAPACITY`.
   - Injeksi karantina lot (`LotQuarantineService`) → verifikasi `StockAvailabilityReader::getAvailableQty()` otomatis berkurang sebesar kuantitas karantina.
   - Injeksi stok negatif via `WmsAuditCorruptionFixture` (X41-5) → verifikasi `php artisan wms:audit` gagal dengan exit code `1`.
