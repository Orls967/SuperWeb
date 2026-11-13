# Algoritma J2 — `WaveBinPacker` (First-Fit Decreasing Per Carrier Cutoff)

- **Kelas**: `Modules\Wms\Domain\Algorithms\WaveBinPacker`
- **Sertifikat**: `Modules\Wms\Domain\Certificates\WaveBinPackerCertificate`
- **Integrasi Kontrak**: `Modules\Logistics\Contracts\CarrierCutoffProvider`

## 1. Masalah & Formulasi Matematis
Diberikan kapasitas maksimum per wave `C > 0` dan daftar order `O = {(id_i, size_i, carrier_i, cutoff_i)}`. Setiap wave hanya boleh memuat order dengan pasangan `(carrier, cutoff)` yang sama agar konsolidasi pengiriman sesuai jadwal pickup kurir.

Tujuan: mempartisi seluruh `O` ke dalam himpunan wave `W_1, ..., W_m` sehingga:
1. Untuk setiap wave `W_j`, jumlah ukuran order `sum(size_i) <= C`.
2. Seluruh order dalam `W_j` memiliki `carrier` dan `cutoff` identik.
3. **Konservasi Order (Zero-Loss / Zero-Duplicate)**: `union(W_1..W_m) == O` dan `sum(|W_j|) == |O|`.

## 2. Langkah Algoritma (Deterministic FFD)
1. Kelompokkan order berdasarkan kunci `(cutoff, carrier)`, urutkan grup secara menaik berdasarkan `cutoff` lalu `carrier` (tie-breaking deterministik).
2. Di dalam setiap grup, urutkan order berdasarkan `size` menurun (`usort` dengan tie-breaker `id` menaik).
3. Untuk setiap order `o`:
   - Jika `o.size > C`, lempar `InvalidArgumentException` (order melebihi kapasitas bin).
   - Cari wave pertama pada grup yang sisa kapasitasnya `>= o.size` (First-Fit).
   - Jika tidak ada yang muat, buka wave baru berkapasitas `C`.
4. Bangun `WaveBinPackerCertificate` dan verifikasi konservasi ID order sebelum dikembalikan.

## 3. Kompleksitas & Vektor Uji
- **Kompleksitas**: `O(|O| log |O| + |O| * m)` dengan `m` jumlah wave.
- **Vektor Uji**:
  - Kapasitas `70`, order `[40, 35, 30]` untuk `JNE @ 1700` -> tepat 2 wave (`[40, 30]` utilisasi 70/70, dan `[35]` utilisasi 35/70).
  - Saat misi robot mengalami `fallback_manual`, `RetryOrFallbackRobotMissionAction` memanggil ulang `PlanRoboticsWaveAction` dan memverifikasi jumlah order identik tanpa hilang/ganda.
