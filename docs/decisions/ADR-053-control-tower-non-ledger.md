# ADR-053: Pemisahan Control Tower & S&OP dari Posting Buku Besar (D98) & Arsitektur Kontrak Lintas Modul

- **Status:** Diterima & Diperketat (2026-11-14, pembaruan W8-P6-fix)
- **Fase Terkait:** Fase 53 (`Modules\ControlTower`, prefiks tabel `sct_`)
- **Modul Mitra:** `Wms` (`wms_`), `Manufacturing` (`mfg_`), `Distribution` (`dist_`), `Procurement` (`prc_`), `Logistics` (`lgx_`), `Store` (`store_`), `Supplier` (`sup_`), `Core`

---

## 1. Konteks Arsitektur

Modul `ControlTower` bertanggung jawab menyatukan data operasi lintas rantai pasok (stok gudang, penerimaan jadwal produksi MPS & kapasitas kerja pabrik, pesanan distribusi terbuka, serta catatan kedatangan pemasok) ke dalam satu lapisan visibilitas, perencanaan konsensus S&OP, peramalan permintaan, janji pengiriman ATP/CTP, mitigasi risiko, kartu skor pemasok, analisis kesehatan persediaan, KPI eksekutif, dan simulasi digital twin what-if.

Tanpa batas modul yang eksplisit (`A1`, `A2`, `D98`), implementasi Control Tower berisiko melakukan dua anti-pola serius:
1. Membaca tabel operasional milik modul lain secara langsung (`DB::table('mfg_*')`, `DB::table('dist_*')`, `DB::table('prc_*')`, `DB::table('wms_*')`), yang melanggar modular monolith boundaries (`A2` Foreign Table Prefix & `A1` Cross-Module Domain Import).
2. Mencampuradukkan perencanaan analitis (perkiraan margin S&OP, eksposur nilai dead-stock, skenario simulasi) dengan posting buku besar keuangan nyata (`acc_journal_entries`), sehingga menciptakan duplikasi pembukuan atau angka simulasi mencemari laporan keuangan.

---

## 2. Keputusan Arsitektur

### Keputusan 1 — RAB Posting Ledger = 0 (Aturan D98)
- Modul `ControlTower` (`sct_`) **dilarang keras** mem-posting jurnal akuntansi atau mendaftarkan kelas `TransactionTypeRegistry`.
- Angka rupiah pada `sct_sop_cycles`, `sct_twin_scenarios`, dan `sct_inventory_health_snapshots` adalah metrik perencanaan / estimasi dampak risiko semata (`int64` / `bigInteger` minor unit IDR tanpa `float`).
- Kejadian keuangan nyata (penerimaan barang, selisih stok, biaya produksi, faktur penjualan/pembelian, dan pembayaran) dieksekusi seluruhnya di modul transaksi masing-masing.

### Keputusan 2 — Inversi Ketergantungan Kontrak Lintas Modul
`ControlTower` hanya boleh berinteraksi dengan modul eksternal melalui interface kontrak resmi yang di-bind di masing-masing `ServiceProvider`:
1. `Modules\ControlTower\Contracts\ItemAvailabilityReader` diimplementasikan oleh `WmsItemAvailabilityReader` memakai `Modules\Wms\Contracts\StockAvailabilityReader`, `Modules\Store\Contracts\Product`, dan `Modules\Logistics\Contracts\InTransitShipmentProvider`.
2. `Modules\Manufacturing\Contracts\CapacityAndMaterialProvider` diimplementasikan langsung oleh `ManufacturingCapacityAndMaterialProvider` di modul `Manufacturing` yang memiliki akses sah atas tabel `mfg_*`.
3. `Modules\Distribution\Contracts\OpenDemandProvider` diimplementasikan langsung oleh `DistributionOpenDemandProvider` di modul `Distribution` atas tabel `dist_*`.
4. `Modules\Procurement\Contracts\SupplierLeadTimeStatistics` diimplementasikan oleh `ProcurementSupplierLeadTimeStatistics` di modul `Procurement` atas tabel `prc_*` serta kontrak `Modules\Supplier\Contracts\Supplier`.
5. Modul pemesan eksternal meminta evaluasi janji pengiriman **hanya** melalui `Modules\ControlTower\Contracts\PromiseQuoteInterface` tanpa mengakses `sct_order_promises` secara langsung.

### Keputusan 3 — Publikasi Event Domain Terstruktur via `OutboxBusInterface` & `EventSchemaRegistry`
Seluruh kejadian transaksional penting dipublikasikan secara aditif dengan `schema_version = 1` dan didaftarkan di `EventCatalog` + `EventSchemaRegistry`:
- `tower.alert.raised.v1` -> dipancarkan oleh `AlertScannerService` saat anomali risiko/keterlambatan baru melampaui ambang batas.
- `tower.sop.published.v1` -> dipancarkan oleh `SopPublicationOrchestrator` setelah siklus S&OP melewati gerbang persetujuan eksekutif four-eyes.
- `tower.promise.made.v1` -> dipancarkan oleh `PromiseQuoteService` saat janji pengiriman ATP/CTP baru tersimpan.

### Keputusan 4 — Pemisahan Human-in-the-Loop untuk Tindakan Fisik
Rekomendasi realokasi stok antar-simpul (`ExecuteAutonomousReallocationPlaybookAction::proposeReallocation`) membentuk usulan teraudit yang menunggu otorisasi pengguna berwenang sebelum mengubah komitmen atau alokasi stok operasional.

---

## 3. Konsekuensi & Verifikasi Otomatis

- **Keuntungan:** Setiap modul pemilik data (`Manufacturing`, `Distribution`, `Procurement`, `Wms`) bebas merefaktor struktur tabel internalnya selama kontrak publik dipertahankan; tidak ada penambahan pelanggaran `A1`/`A2` pada `php artisan arch:scan`.
- **Verifikasi CI/CD:**
  - `php artisan arch:scan --json` memverifikasi 0 pelanggaran baru di atas baseline `A1/A2/A12` serta `A3=0, A8=0, A11=0`.
  - `php artisan tower:audit` memverifikasi invarian saldo ATP vs fisik + jadwal terima, integritas pohon CTP, validitas status siklus S&OP 7-tahap, serta deteksi kegagalan deterministik oleh `ControlTowerAuditCorruptionFixture`.
  - Uji isolasi Digital Twin memeriksa kesetaraan SHA-256 seluruh tabel `sct_echelon_stocks`, `sct_order_promises`, dan `sct_disruption_alerts` sebelum dan sesudah simulasi (`before_hash === after_hash`).

---

## 4. Referensi Silang
- Konvensi Non-Ledger & D98: `docs/dor/fase-53.md`
- Matriks Kontrak & Algoritma: `docs/matriks/fase-53.md`
- Spesifikasi Algoritma Deterministik: `docs/algoritma/i1-i3-control-tower.md`
- Prosedur Audit & Pemulihan: `docs/runbooks/control-tower-audit-dan-pemulihan.md`
