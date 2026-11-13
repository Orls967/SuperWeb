# Simulasi Vektor & Skenario S2 Modul WMS (Fase 41)

Dokumen ini memuat skenario simulasi deterministik (tingkat S2) beserta jejak perhitungan integer langkah-demi-langkah untuk seluruh algoritma dan layanan inti WMS Fase 41.

---

## 1. Simulasi H1 — `PutawaySlotter`

### Skenario 1A: Penempatan Tunggal Kelas A, B, C
Diketahui tiga bin aktif pada zona tujuan:
- `B1`: `distance = 10`, `freeCapacity = 20`
- `B2`: `distance = 30`, `freeCapacity = 12`
- `B3`: `distance = 50`, `freeCapacity = 20`

Untuk permintaan `q = 10`:
1. **Kelas A** (*fast-moving*):
   - Kandidat tunggal (`freeCapacity >= 10`): `B1`, `B2`, `B3`.
   - Tuple pembanding `(distance, freeCapacity - q, id)`:
     - `B1`: `(10, 10, 'B1')`
     - `B2`: `(30, 2, 'B2')`
     - `B3`: `(50, 10, 'B3')`
   - Pemenang: **`B1`** dengan alokasi `10`.
2. **Kelas B** (*medium-moving*):
   - Tuple pembanding `(freeCapacity - q, distance, id)`:
     - `B1`: `(10, 10, 'B1')`
     - `B2`: `(2, 30, 'B2')`
     - `B3`: `(10, 50, 'B3')`
   - Pemenang: **`B2`** dengan alokasi `10` (sisa ruang paling rapat `2`).
3. **Kelas C** (*slow-moving*):
   - Tuple pembanding `(-distance, freeCapacity - q, id)`:
     - `B1`: `(-10, 10, 'B1')`
     - `B2`: `(-30, 2, 'B2')`
     - `B3`: `(-50, 10, 'B3')`
   - Pemenang: **`B3`** dengan alokasi `10` (lokasi terjauh dari dock).

### Skenario 1B: Split Greedy Multi-Bin
Untuk permintaan `q = 35` kelas `A` pada bin yang sama:
- Tidak ada bin tunggal dengan `freeCapacity >= 35` (maksimum `20`).
- Total kapasitas tersedia `20 + 12 + 20 = 52 >= 35` (memenuhi syarat).
- Urutan prioritas split kelas A: `(distance ASC, freeCapacity DESC, id ASC)` → `B1`, `B2`, `B3`.
- Alokasi bertahap:
  1. Ambil `min(35, 20) = 20` dari `B1` (sisa `15`).
  2. Ambil `min(15, 12) = 12` dari `B2` (sisa `3`).
  3. Ambil `min(3, 20) = 3` dari `B3` (sisa `0`).
- Mode sertifikat: `split`, total teralokasi `35 == requestedQty`.

### Skenario 1C: Kegagalan Kapasitas (`NO_CAPACITY`)
- Untuk `q = 60` pada bin di atas, `52 < 60` → melempar `DomainException('NO_CAPACITY')`.
- Untuk `q = 0` atau `q = -5` → melempar `DomainException('INVALID_QUANTITY')`.

---

## 2. Simulasi H2 — `PickPathPlanner`

### Skenario 2A: Satu Lorong (Tie-Breaker `S_SHAPE`)
- Layout: `aisleWidth = 10`, `aisleLength = 100`.
- Picks: `[{aisle: 1, pos: 25}]`.
- Perhitungan `S_SHAPE`:
  - Cross-aisle: `2 * (1 - 1) * 10 = 0`.
  - Vertical: `1` lorong (ganjil) → `2 * 25 = 50`.
  - Total `S_SHAPE` = `50`.
- Perhitungan `LARGEST_GAP`:
  - `aisleCount == 1` → `2 * 25 = 50`.
- Karena `50 <= 50`, tie-breaker wajib memilih **`S_SHAPE`** dengan `total_distance = 50`.

### Skenario 2B: Multi-Lorong dengan Interior Gap Besar
- Layout: `aisleWidth = 10`, `aisleLength = 100`.
- Picks:
  - Lorong 1: `pos = 10`, `pos = 90`
  - Lorong 2: `pos = 5`, `pos = 95` (gap tengah = `90`, sangat besar)
  - Lorong 3: `pos = 20`
- Perhitungan `S_SHAPE`:
  - Cross-aisle: `2 * (3 - 1) * 10 = 40`.
  - Vertical: lorong 1 (`100`) + lorong 2 (`100`) + lorong 3 (`2 * 20 = 40`) = `240`.
  - Total `S_SHAPE` = `280`.
- Perhitungan `LARGEST_GAP`:
  - Cross-aisle: `40`.
  - Lorong 1 (`100`) + Lorong 3 (`100`) = `200`.
  - Lorong 2 interior: gap `5 - 0 = 5`, `95 - 5 = 90`, `100 - 95 = 5` → `maxGap = 90` → `2 * (100 - 90) = 20`.
  - Total `LARGEST_GAP` = `40 + 200 + 20 = 260`.
- Strategi terpilih: **`LARGEST_GAP`** (`260 < 280`).

---

## 3. Simulasi H3 — `CycleCountScheduler`

Diketahui `today = 400`, `capacity = 2`:
- `SKU-C` (`C`, `last = 10`): jatuh tempo `10 + 360 = 370`, `overdue = 400 - 370 = 30`.
- `SKU-A1` (`A`, `last = 360`): jatuh tempo `360 + 30 = 390`, `overdue = 400 - 390 = 10`.
- `SKU-A2` (`A`, `last = 340`): jatuh tempo `340 + 30 = 370`, `overdue = 400 - 370 = 30`.
- `SKU-B` (`B`, `last = 350`): jatuh tempo `350 + 90 = 440`, `overdue = 400 - 440 = -40` (belum jatuh tempo).

Pengurutan item jatuh tempo `(overdue DESC, abcClass ASC, id ASC)`:
1. `SKU-A2` (`overdue = 30, class = A`)
2. `SKU-C` (`overdue = 30, class = C`)
3. `SKU-A1` (`overdue = 10, class = A`)

Hasil dengan `capacity = 2`:
- `selected`: `['SKU-A2', 'SKU-C']`
- `carried_over`: `['SKU-A1']`

---

## 4. Simulasi Jejak Buku Besar 12 Kejadian (D41-9)

Misalkan gudang `DC-JKT` menjalankan urutan transaksi berikut dalam satu siklus operasional:

1. **Saldo Awal (`D41-1`)** sebesar `100.000 IDR`:
   - Debit `wms:inventory:DC-JKT:IDR` (`-100.000`) / Kredit `wms:opening_balance_equity:IDR` (`+100.000`).
   - Saldo aset `DC-JKT` = `100.000 IDR`.
2. **Penerimaan Inbound (`D41-2`)** sebesar `50.000 IDR`:
   - Debit `wms:inventory:DC-JKT:IDR` (`-50.000`) / Kredit `wms:receiving_clearing:IDR` (`+50.000`).
   - Saldo aset `DC-JKT` = `150.000 IDR`.
3. **Pengeluaran Outbound (`D41-3`)** sebesar `20.000 IDR`:
   - Debit `wms:issue_clearing:IDR` (`-20.000`) / Kredit `wms:inventory:DC-JKT:IDR` (`+20.000`).
   - Saldo aset `DC-JKT` = `130.000 IDR`.
4. **Transfer Keluar (`D41-4`)** ke `DC-SBY` sebesar `30.000 IDR`:
   - Debit `wms:inventory_in_transit:IDR` (`-30.000`) / Kredit `wms:inventory:DC-JKT:IDR` (`+30.000`).
   - Saldo aset `DC-JKT` = `100.000 IDR`; saldo `in_transit` = `30.000 IDR`.
5. **Transfer Masuk (`D41-5`)** di `DC-SBY` sebesar `25.000 IDR` + **Transit Loss (`D41-9`)** sebesar `5.000 IDR`:
   - Masuk: Debit `wms:inventory:DC-SBY:IDR` (`-25.000`) / Kredit `wms:inventory_in_transit:IDR` (`+25.000`).
   - Hilang: Debit `wms:transit_loss:IDR` (`-5.000`) / Kredit `wms:inventory_in_transit:IDR` (`+5.000`).
   - Saldo `in_transit` kembali bersih `0 IDR`; saldo `DC-SBY` = `25.000 IDR`.
6. **Cycle Count Gain (`D41-6`)** `10.000 IDR` & **Shrinkage (`D41-7`)** `5.000 IDR`:
   - Saldo `DC-JKT` = `100.000 + 10.000 - 5.000 = 105.000 IDR`.
7. **Write-Off (`D41-8`)** `10.000 IDR`, **Mark-Up (`D41-10`)** `15.000 IDR`, **Mark-Down (`D41-11`)** `5.000 IDR`:
   - Saldo akhir `DC-JKT` = `105.000 - 10.000 + 15.000 - 5.000 = 105.000 IDR`.
8. **Tagihan 3PL (`D41-12`)**:
   - `10 palletDays × 5.000 + 20 handlingUnits × 1.000 = 70.000 IDR`.
   - Debit `wms:3pl_receivable:IDR` (`-70.000`) / Kredit `wms:3pl_revenue:IDR` (`+70.000`).

---

## 5. Simulasi Normalisasi Kuantitas & Toleransi (D81 & X41-1)

- `QuantityNormalizer::toBaseUnits(2, 'box')` → `2 × 12 = 24`.
- `QuantityNormalizer::toBaseUnits(3, 'pallet')` → `3 × 480 = 1440`.
- `QuantityNormalizer::fromDecimalString('12.000000')` → `12`.
- `QuantityNormalizer::varianceBps(100, 104)` → `+400 bps` → `within_tolerance` (batas `500 bps`).
- `QuantityNormalizer::varianceBps(100, 110)` → `+1000 bps` → `over_receipt` (memerlukan persetujuan supervisor).
- `QuantityNormalizer::varianceBps(100, 90)` → `-1000 bps` → `short_receipt` (memicu klaim vendor).
