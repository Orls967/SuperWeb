# FAQ & Pemecahan Masalah Operasional WMS (Fase 41)

## 1. Pertanyaan Umum Operator Gudang

### T: Mengapa saya tidak bisa melakukan putaway ke bin yang terlihat kosong di rak?
**J**: Periksa tiga kemungkinan berikut melalui `/wms` atau `PutawayRuleEngine`:
1. Bin berada di zona `quarantine` atau `is_active = false`.
2. Produk membutuhkan rantai dingin (`requires_cold_chain = true`) atau berstatus `hazmat`, sedangkan zona bin tidak mendukungnya.
3. Berat per unit produk `>= 10.000 gram` (`10 kg`), sehingga aturan keselamatan melarang penempatan di rak level `> 2`.

### T: Mengapa layar `/wms/tasks` menolak barcode bin yang saya pindai?
**J**: `WmsHandheldOperationService::confirmScanAndComplete()` mencocokkan kode bin hasil pindai secara ketat terhadap `to_bin_id` (untuk `putaway`) atau `from_bin_id` (untuk `pick`). Pastikan Anda berada di lorong dan rak yang tepat; jika barang secara fisik tidak muat, laporkan kendala melalui `ResolveTaskExceptionAction::flag()` agar tugas berpindah ke status `exception`.

### T: Apa yang harus dilakukan jika jumlah barang datang dari vendor melebihi PO?
**J**: `ReceivingToleranceEvaluator` mengizinkan selisih hingga `+500 bps` (`+5%`) secara otomatis (`within_tolerance`). Jika melebihi `+5%`, sistem menandai `over_receipt` dan memblokir penyimpanan sampai supervisor (`admin`) menyetujui lewat parameter `approve_over_receipt`.

### T: Mengapa stok produk menunjukkan 100 unit di rak, tetapi order baru gagal mereservasi?
**J**: `StockAvailabilityReader::getAvailableQty()` menghitung `on_hand - quarantined - active_reservations`. Sebagian atau seluruh stok tersebut mungkin sedang berstatus `quarantine` (lihat `/wms/quarantine`) atau terkunci oleh reservasi aktif yang belum disapu oleh `wms:reservation-sweep`.

---

## 2. Pertanyaan Umum Akuntansi & Auditor

### T: Mengapa saldo akun `wms:inventory:DC-JKT:IDR` bernilai negatif di tabel `bank_ledger_accounts`?
**J**: Ekosistem menggunakan konvensi tanda tunggal (`KONSEP §A2.1`): **Debit bernilai negatif (`-`)** dan **Kredit bernilai positif (`+`)**. Karena akun aset persediaan bertambah pada sisi Debit, saldo aset positif `Rp 250.000` tersimpan sebagai `-250000` di `cached_balance`. `WmsLedgerPoster::getWarehouseLedgerBalanceIdr()` otomatis membalik tanda (`-(int) $cached`) saat menampilkan nilai aset pada `/wms/valuation` dan `wms:audit`.

### T: Mengapa `wms:audit` gagal dengan pesan `ledger:DC-X SELISIH` setelah saya menambah stok uji manual di database?
**J**: Penambahan baris ke `wms_bin_stocks` secara langsung melewati `WmsLedgerPoster` sehingga buku besar tidak mencatat nilai persediaan tersebut. Untuk stok awal gudang baru, selalu jalankan `php artisan wms:post-opening-balance --warehouse_id={ID}` setelah mengisi `wms_bin_stocks` dan `wms_unit_costs`.

### T: Apakah aman menjalankan `php artisan wms:post-opening-balance` lebih dari satu kali?
**J**: Ya, 100% aman dan idempoten. Kunci `IDEM:WMS:OPENING:{KODE_GUDANG}` dicek terhadap `bank_ledger_transactions` sebelum posting dieksekusi.

### T: Bagaimana jika harga pokok standar (`wms_unit_costs`) berubah di tengah bulan?
**J**: Gunakan `RevalueInventoryCostAction` atau `php artisan wms:revalue-cost`. Sistem akan menonaktifkan harga lama, menyimpan harga baru dengan `effective_date`, dan memposting selisih `Δcost × qty_base` ke `wms:cost_revaluation_variance:IDR` (D41-10 atau D41-11) agar subledger dan buku besar tetap seimbang (`discrepancy_idr === 0`).

---

## 3. Pertanyaan Teknis Pengembang

### T: Mengapa `Warehouse::findOrFail($id)` tidak boleh bertipe parameter `int` saja?
**J**: Model `Warehouse`, `Zone`, `Rack`, `Bin`, `Task`, `Wave`, `Transfer`, dan `CycleCount` menggunakan trait `HasUuids` sehingga primary key-nya bertipe string UUID. Seluruh kontrak dan action baru menerima `string|int $warehouseId` agar kompatibel dengan UUID maupun ID numerik.

### T: Di mana saya harus mendaftarkan akun ledger baru atau tipe transaksi baru untuk WMS?
**J**:
- Akun ledger didaftarkan di `Modules\Wms\Ledger\WmsAccounts` (yang terdaftar di `Database\Seeders\LedgerAccountsSeeder::ACCOUNT_REGISTRIES`).
- Tipe transaksi didaftarkan di enum `Modules\Wms\Ledger\WmsTransactionTypes` (yang terdaftar di `Modules\Banking\Application\Registry\TransactionTypeRegistry::$registries`).
- Jangan pernah memanggil `LedgerAccount::create()` di dalam Action atau Test.

