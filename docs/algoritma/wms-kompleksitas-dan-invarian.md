# Analisis Kompleksitas, Batas Integer, & Invarian Formal WMS

Dokumen ini merangkum kompleksitas waktu/ruang serta jaminan batas integer (64-bit) untuk seluruh algoritma di modul `Wms`.

---

## 1. Tabel Kompleksitas Algoritma

| Algoritma | Kompleksitas Waktu | Kompleksitas Ruang | Keterangan |
|---|---|---|---|
| `PutawaySlotter::plan` | $O(N \log N)$ | $O(N)$ | $N$ = jumlah bin aktif dengan `freeCapacity > 0`; sorting deterministik tunggal |
| `PickPathPlanner::plan` | $O(P \log P + A)$ | $O(P)$ | $P$ = jumlah titik pick, $A$ = jumlah lorong unik |
| `CycleCountScheduler::schedule` | $O(M \log M)$ | $O(M)$ | $M$ = jumlah item kandidat cycle count |
| `AbcVelocityClassifier::classify` | $O(S \log S)$ | $O(S)$ | $S$ = jumlah SKU aktif |
| `FefoLotAllocator::allocate` | $O(L \log L)$ | $O(L)$ | $L$ = jumlah lot tersedia untuk satu produk |
| `WaveBatchOptimizer::buildWaves` | $O(K \log K)$ | $O(K)$ | $K$ = jumlah order outbound dalam antrean |
| `CrossWarehouseRebalancer::proposeTransfers` | $O(W \cdot P)$ | $O(W \cdot P)$ | $W$ = jumlah gudang, $P$ = jumlah SKU |
| `DockQueueDispatcher::assignDoors` | $O(Q \log Q)$ | $O(Q)$ | $Q$ = jumlah truk dalam antrean dock |

---

## 2. Keamanan Batas Integer 64-bit (`PHP_INT_MAX`)
Seluruh perhitungan menggunakan integer bertanda 64-bit (`PHP_INT_MAX = 9.223.372.036.854.775.807`):
- Kuantitas maksimum per transaksi gudang (`10^9` base unit) × harga maksimum per unit (`10^9` IDR) = `10^18 < 9.22 × 10^18`, sehingga tidak terjadi *integer overflow*.
- Perhitungan basis points `intdiv($diff * 10000, $expected)` aman hingga `$diff <= 9 × 10^14`.
- Untuk nilai `expiry_epoch === 0` (produk tanpa tanggal kedaluwarsa), `FefoLotAllocator` memetakannya ke `PHP_INT_MAX` hanya di dalam fungsi komparator *sorting*, tidak pernah dijumlahkan atau dikalikan.

---

## 3. Daftar Invarian Formal Sistem

1. **Konservasi Kuantitas Putaway**: Untuk setiap hasil `PutawaySlotter::plan($q, ...)`, `Σ alloc.qty === $q` dan tidak ada `bin_id` duplikat.
2. **Optimalitas Lokal Picking**: Untuk setiap hasil `PickPathPlanner::plan(...)`, `total_distance === min(s_shape_distance, largest_gap_distance)` dan seri selalu memilih `S_SHAPE`.
3. **Kepatuhan Kapasitas Cycle Count**: Untuk setiap hasil `CycleCountScheduler::schedule($today, $items, $cap)`, `count(selected) <= $cap` dan setiap item di `selected` memiliki `overdue >= 0`.
4. **Keseimbangan Buku Besar**: Untuk setiap transaksi `WmsLedgerPoster`, `Σ entries.signed_amount === 0`.
5. **Non-Negativitas Stok**: Tidak ada mutasi resmi yang menghasilkan `qty_base < 0` atau `available_base < 0`.
6. **Konsistensi Subledger-Ledger**: Pada kondisi sehat (`wms:audit` exit `0`), `Σ(wms_bin_stocks.qty_base × wms_unit_costs.cost_idr) === -ledger_account.cached_balance` untuk setiap gudang.

