# Algoritma Bersertifikat WMS (H1, H2, H3) & Algoritma Pendukung

Dokumen ini menjelaskan spesifikasi matematis, aturan tie-breaking, contoh vektor, kompleksitas, dan mekanisme verifikasi sertifikat untuk algoritma di modul `Wms`.

---

## 1. H1 — `PutawaySlotter`

**Lokasi**: `Modules\Wms\Domain\Algorithms\PutawaySlotter`
**Sertifikat**: `PutawaySuggestionCertificate` & `PutawaySuggestionCertificateVerifier`

### 1.1 Input & Validasi
- `q` (integer): kuantitas yang harus ditempatkan. Jika `q <= 0`, lempar `DomainException('INVALID_QUANTITY')`.
- `abcClass` (`'A' | 'B' | 'C'`): kelas kecepatan produk. Nilai selain A/B/C melempar `InvalidArgumentException`.
- `bins`: daftar bin aktif berisi `(id, distance, freeCapacity)`. Bin dengan `freeCapacity <= 0` diabaikan.

### 1.2 Aturan Penempatan
1. **Kandidat Tunggal** (`freeCapacity >= q`):
   - **Kelas A** (*fast-moving*): pilih bin yang meminimalkan tuple `(distance ASC, freeCapacity - q ASC, id ASC)`. Tujuannya meminimalkan jarak tempuh dari dock ke *pick-face*.
   - **Kelas B** (*medium-moving*): pilih bin yang meminimalkan tuple `(freeCapacity - q ASC, distance ASC, id ASC)` (*best-fit* kapasitas terlebih dahulu agar ruang besar tetap utuh).
   - **Kelas C** (*slow-moving*): pilih bin yang meminimalkan tuple `(-distance ASC, freeCapacity - q ASC, id ASC)`, yakni menempatkan barang lambat di zona terdalam/terjauh agar area depan tetap longgar untuk kelas A.
2. **Split Greedy** (jika tidak ada satu pun bin dengan `freeCapacity >= q`):
   - Jika total `Σ freeCapacity < q`, lempar `DomainException('NO_CAPACITY')`.
   - Urutkan bin sesuai prioritas kelas ABC dan alokasikan secara greedy `min(remaining, freeCapacity)` hingga `remaining == 0`.

### 1.3 Contoh Vektor Terverifikasi
Diberikan bin:
- `B1`: `distance = 10`, `freeCapacity = 20`
- `B2`: `distance = 30`, `freeCapacity = 12`
- `B3`: `distance = 50`, `freeCapacity = 20`

Hasil deterministik:
- `plan(10, 'A', bins)` → `single`: `[{bin_id: 'B1', qty: 10}]` (jarak terkecil `10`)
- `plan(10, 'B', bins)` → `single`: `[{bin_id: 'B2', qty: 10}]` (*best-fit* sisa terkecil `12 - 10 = 2`)
- `plan(10, 'C', bins)` → `single`: `[{bin_id: 'B3', qty: 10}]` (jarak terjauh `50`)
- `plan(35, 'A', bins)` → `split`: `[{bin_id: 'B1', qty: 20}, {bin_id: 'B2', qty: 12}, {bin_id: 'B3', qty: 3}]`

### 1.4 Invarian Sertifikat (`PutawaySuggestionCertificateVerifier`)
- `requestedQty > 0`
- Tidak ada `bin_id` duplikat dalam alokasi
- Setiap `alloc.qty <= bin.freeCapacity`
- `Σ alloc.qty == requestedQty`

---

## 2. H2 — `PickPathPlanner`

**Lokasi**: `Modules\Wms\Domain\Algorithms\PickPathPlanner`
**Sertifikat**: `PickSequenceCertificate` & `PickSequenceCertificateVerifier`

### 2.1 Input & Validasi
- `aisleWidth` (`w > 0`): lebar jarak antar-lorong dalam satuan integer.
- `aisleLength` (`L > 0`): panjang satu lorong dalam satuan integer.
- `picks`: daftar titik pengambilan `(aisle, pos, bin_id?)`. Jika kosong, lempar `DomainException('EMPTY_PICK_LIST')`.

### 2.2 Perbandingan Strategi (Integer Murni)
Algoritma menghitung total jarak tempuh untuk dua strategi heuristik klasik gudang (Ratliff & Rosenthal / Hall):

1. **`S_SHAPE`**:
   - Jarak lintas-lorong: `2 * (maxAisle - 1) * w`.
   - Jarak vertikal: jika jumlah lorong yang dikunjungi `m` genap, setiap lorong dilintasi penuh (`m * L`); jika `m` ganjil, `m - 1` lorong pertama dilintasi penuh dan lorong terakhir ditempuh dari depan (`pos = 0`) hingga posisi terjauh lalu kembali (`2 * maxPos`).
2. **`LARGEST_GAP`**:
   - Jarak lintas-lorong: `2 * (maxAisle - 1) * w`.
   - Jika `m == 1`, jarak vertikal = `2 * maxPos`.
   - Jika `m > 1`, lorong pertama dan terakhir ditempuh penuh (`L` per lorong). Pada setiap lorong interior, dihitung celah terbesar (*largest gap*) di antara `(0, p_1), (p_i, p_{i+1}), (p_r, L)`, sehingga jarak vertikal di lorong interior = `2 * (L - maxGap)`.

### 2.3 Tie-Breaking & Sertifikat
- Jika `sShapeDistance <= largestGapDistance`, strategi terpilih adalah **`S_SHAPE`**.
- Urutan pick pada `S_SHAPE` bergantian naik (`pos ASC`) untuk indeks lorong genap dan turun (`pos DESC`) untuk indeks lorong ganjil.
- `PickSequenceCertificateVerifier` memastikan panjang urutan hasil sama dengan jumlah pick input, `totalDistance == min(sShapeDistance, largestGapDistance)`, dan aturan tie-breaking `S_SHAPE` dipatuhi.

---

## 3. H3 — `CycleCountScheduler`

**Lokasi**: `Modules\Wms\Domain\Algorithms\CycleCountScheduler`
**Sertifikat**: `CycleCountScheduleCertificate` & `CycleCountScheduleCertificateVerifier`

### 3.1 Interval & Perhitungan Overdue
- Interval siklus wajib per kelas ABC:
  - **Kelas A**: `30` hari
  - **Kelas B**: `90` hari
  - **Kelas C**: `360` hari
- Rumus keterlambatan: `overdue = today - (lastCountedDay + interval)`.
- Item dianggap jatuh tempo bila `overdue >= 0`. Item dengan `overdue < 0` tidak dimasukkan ke antrean hari ini.

### 3.2 Prioritas & Kapasitas
Item yang jatuh tempo diurutkan berdasarkan:
1. `overdue DESC` (paling terlambat didahulukan)
2. `abcClass ASC` (`A` sebelum `B` sebelum `C`)
3. `id ASC` (tie-breaker deterministik)

Sebanyak `capacity` item teratas masuk ke daftar `selected`, sedangkan sisanya masuk ke daftar `carried_over` untuk dijadwalkan pada hari kerja berikutnya.

### 3.3 Contoh Vektor Terverifikasi
Dengan `today = 400`, `capacity = 2`:
- `SKU-C` (C, last=10): due `370`, `overdue = 30`
- `SKU-A1` (A, last=360): due `390`, `overdue = 10`
- `SKU-A2` (A, last=340): due `370`, `overdue = 30`
- `SKU-B` (B, last=350): due `440`, `overdue = -40` (tidak jatuh tempo)

Hasil: `selected = ['SKU-A2', 'SKU-C']`, `carried_over = ['SKU-A1']`.

---

## 4. Algoritma Pendukung Operasional

### 4.1 `AbcVelocityClassifier`
Mengurutkan SKU berdasarkan `annual_value_idr DESC, product_id ASC` lalu menghitung persentase nilai kumulatif dalam basis points (`cumulative_bps`). SKU dengan `cumulative_bps <= 8000` masuk kelas `A`, `<= 9500` masuk kelas `B`, dan sisanya kelas `C`.

### 4.2 `FefoLotAllocator`
Menyaring lot dengan `status == 'available'`, `available_qty > 0`, serta `expiry_epoch >= minRemainingShelfLifeEpoch` (atau `0` untuk non-expiring). Mengurutkan berdasarkan `expiry_epoch ASC, bin_id ASC, lot_number ASC` dan mengalokasikan kuantitas secara deterministik.

### 4.3 `ReplenishmentMinMaxCalculator`
Menghitung:
- `safety_stock = avg_daily_demand × safety_days`
- `reorder_point = (avg_daily_demand × lead_time_days) + safety_stock`
- `max_level = (avg_daily_demand × max_days_cover) + safety_stock`
- Jika `current_qty <= reorder_point`, `suggested_replenish_qty = max_level - current_qty`.

### 4.4 `WaveBatchOptimizer`
Mengelompokkan order outbound (diurutkan berdasarkan `cutoff_minutes ASC, order_id ASC`) ke dalam wave picking dengan batasan `maxLinesPerWave` dan `maxWeightGramsPerWave` tanpa memecah satu order ke dua wave berbeda.
