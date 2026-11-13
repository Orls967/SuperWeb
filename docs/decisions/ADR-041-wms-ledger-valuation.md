# ADR-041: Penilaian Persediaan Gudang Berbasis Integer & Rekonsiliasi Ledger Otomatis

- **Status**: Diterima
- **Tanggal**: 2026-11-13
- **Konteks**: Fase 41 (Gudang & Pusat Distribusi — WMS Lanjutan)
- **Penulis**: Pelaksana [W8-P6]

---

## 1. Latar Belakang
Pada implementasi awal modul `Wms`, kuantitas bin disimpan dalam kolom `decimal(18,6)` dan belum memiliki tabel biaya satuan terversi (`wms_unit_costs`) maupun integrasi posting buku besar (`LedgerService`). Kondisi ini menyebabkan dua risiko utama:
1. Risiko *floating-point drift* saat mengonversi satuan kemasan (pallet, carton, box, pcs) atau saat menghitung nilai total persediaan dalam Rupiah (IDR).
2. Ketiadaan rekonsiliasi otomatis antara nilai fisik persediaan di subledger gudang dengan saldo akun aset persediaan di buku besar `EnterpriseFinance`.

---

## 2. Keputusan Arsitektur

### 2.1 Normalisasi Kuantitas ke Integer Base Unit (D81)
Seluruh logika domain dan aplikasi di modul `Wms` wajib memproses kuantitas dalam **integer base unit** (`qty_base`), bukan `float`. Kolom `qty_base` (`BIGINT`) ditambahkan ke `wms_bin_stocks` melalui migrasi aditif tanpa mengubah migrasi historis. Seluruh konversi satuan dilakukan melalui `QuantityNormalizer`.

### 2.2 Penilaian Harga Pokok Berversi (`wms_unit_costs`)
Harga pokok per unit disimpan dalam `cost_idr` (`UNSIGNED BIGINT`, minor units IDR) dengan dimensi `(product_id, warehouse_id, effective_date, method)`. Setiap perubahan biaya wajib melalui `RevalueInventoryCostAction` sehingga:
- Riwayat biaya lama tetap tersimpan (`is_active = false`).
- Selisih nilai stok aktif langsung diposting ke akun `wms:cost_revaluation_variance:IDR` (D41-10 / D41-11).

### 2.3 Buku Besar 12 Kejadian Uang (`WmsAccounts` & `WmsLedgerPoster`)
Seluruh kejadian mutasi persediaan yang berdampak finansial (12 kejadian D41-1 s.d. D41-12) diposting melalui `WmsLedgerPoster` ke `LedgerService` dengan:
- Kunci idempotensi deterministik `IDEM:WMS:{AKSI}:{ID}`.
- Penamaan akun terstandarisasi pada `WmsAccounts` (`wms:inventory:{KODE_GUDANG}:IDR`, `wms:inventory_in_transit:IDR`, `wms:receiving_clearing:IDR`, `wms:issue_clearing:IDR`, `wms:inventory_shrinkage:IDR`, `wms:inventory_gain:IDR`, `wms:inventory_writeoff:IDR`, `wms:transit_loss:IDR`, `wms:cost_revaluation_variance:IDR`, `wms:3pl_receivable:IDR`, `wms:3pl_revenue:IDR`, `wms:opening_balance_equity:IDR`).
- Konfigurasi `allow_negative => true` pada akun aset/beban karena sistem buku besar menggunakan konvensi tanda tunggal (`debit = -`, `credit = +`).

### 2.4 Rekonsiliasi & Period Closing
- `WarehouseValuationReader` menjadi kontrak tunggal untuk membandingkan `Σ(qty_base × cost_idr)` terhadap `-cached_balance` di `bank_ledger_accounts`.
- `WmsSubledgerTotalAdapter` mengimplementasikan `SubledgerTotalProvider` dan ditandai ke `EnterpriseFinance` agar penutupan periode akuntansi mencakup nilai persediaan seluruh gudang.
- Perintah `php artisan wms:audit` gagal (`exit code 1`) apabila terdapat gudang dengan `discrepancy_idr != 0`.

---

## 3. Alternatif yang Ditolak
- **Menggunakan `float` / `ROUND()` pada level PHP**: Ditolak karena berpotensi menimbulkan selisih 1–2 IDR pada agregasi ribuan baris SKU saat period closing.
- **Satu akun ledger global `wms:inventory:IDR` untuk semua gudang**: Ditolak karena menyulitkan pelacakan selisih per pusat distribusi (`DC-JKT`, `DC-SBY`, `DC-BJM`) dan memperlambat investigasi audit.

---

## 4. Konsekuensi
- **Positif**: Rekonsiliasi subledger vs buku besar selalu tepat `0` selisih; seluruh mutasi idempoten dan terlacak.
- **Risiko Terkendali**: Gudang baru yang diisi stok awal wajib menjalankan `wms:post-opening-balance` sebelum `wms:audit` dijalankan agar saldo buku besar terbentuk.
