# ADR-053: Pemisahan Control Tower & S&OP dari Posting Buku Besar (D98)

- **Status:** Diterima (2026-11-13)
- **Konteks:** Fase 53 membangun Control Tower rantai pasok, peramalan permintaan, siklus S&OP 4-tahap, janji pesanan ATP/CTP, dan simulasi What-If Digital Twin.
- **Keputusan:** Modul `ControlTower` (`sct_`) ditetapkan memiliki **RAB Posting Ledger = 0** dan tidak mendaftarkan `TransactionTypeRegistry`.
- **Alasan:** Seluruh entitas di `sct_*` merepresentasikan rencana kuantitatif, proyeksi finansial non-mengikat, atau simulasi sandbox what-if. Kejadian uang riil (penerimaan barang, pengakuan HPP, faktur penjualan, penyesuaian persediaan) tetap dieksekusi dan dijurnal di modul pemilik transaksi (`Wms`, `Purchasing`, `Sales`, `Manufacturing`, `Banking`).
- **Konsekuensi:** `ControlTower` membaca posisi persediaan riil secara read-only melalui kontrak `Modules\Wms\Contracts\StockAvailabilityReader` dan diaudit secara independen oleh `php artisan tower:audit` terhadap invarian ATP/CTP dan state machine S&OP.

