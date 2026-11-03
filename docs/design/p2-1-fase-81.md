# Technical Design — Fase 81 & Item 64.1: Dynamic Surge Pricing ber-Guardrail (P2.1)

## 1. Domain Model & Skema Database

### 1.1 Tabel `pric_tick_rejections`
Mencatat penolakan tick yang menabrak guardrail keras [floor, min(ceiling, HET)].
- `id` (bigint auto-increment)
- `sku` (string 64, index)
- `slot_time` (datetime)
- `attempted_price_idr` (bigint)
- `base_price_idr` (bigint)
- `last_price_idr` (bigint)
- `raw_price_idr` (bigint)
- `bounded_price_idr` (bigint)
- `floor_price_idr` (bigint)
- `ceiling_price_idr` (bigint)
- `het_price_idr` (bigint)
- `rejection_reason` (string 64: `BELOW_FLOOR`, `ABOVE_CEILING`, `ABOVE_HET`)
- `idempotency_key` (string 64, unique)
- `created_at`, `updated_at` (timestamps)

### 1.2 Tabel `pric_sku_parameters`
Parameter berversi per SKU / kategori:
- `id` (bigint auto-increment)
- `sku` (string 64, unique)
- `category` (string 64)
- `base_price_idr` (bigint)
- `floor_price_idr` (bigint)
- `ceiling_price_idr` (bigint)
- `het_price_idr` (bigint)
- `price_step_idr` (integer, default 50)
- `elasticity_bps` (integer, default 10000 = 1.0)
- `is_perishable` (boolean, default false)
- `version` (integer, default 1)
- `timestamps`

### 1.3 Kolom Tambahan `pric_price_ticks`
- `status`: string 32 default 'normal' (`normal`, `dibatasi`)
- `base_price_idr`: bigint nullable
- `raw_price_idr`: bigint nullable
- `slot_time`: datetime nullable (index)
- `factor_total_bps`: integer nullable

## 2. Aturan Kalkulasi Tick S1 (D81-2)

1. **Faktor BPS Total**:
   $$\text{faktor\_bps} = f_{stok} + f_{permintaan} + f_{umur\_simpan} + f_{musim} + f_{okupansi}$$
   - $f_{stok}$:
     - stock cover $< 3$ hari: $+1.500$ bps
     - $3 \le \text{cover} < 7$ hari: $+500$ bps
     - $7 \le \text{cover} < 14$ hari: $0$ bps
     - $\ge 14$ hari: $-700$ bps
   - $f_{permintaan}$:
     - rasio permintaan harian $\ge 15.000$ bps: $+1.000$ bps
     - $12.000 \le \text{rasio} < 15.000$ bps: $+400$ bps
     - $< 8.000$ bps: $-500$ bps
     - lainnya: $0$ bps
   - $f_{umur\_simpan}$ (hanya jika perishable):
     - sisa umur $< 2.000$ bps: $-3.000$ bps
     - $2.000 \le \text{sisa} < 5.000$ bps: $-1.000$ bps
     - lainnya: $0$ bps
   - $f_{musim}$, $f_{okupansi}$: integer bps dari kalender/okupansi berversi.

2. **Harga Mentah**:
   $$\text{mentah} = \text{round}\left(\frac{\text{dasar} \times (10.000 + \sum \text{faktor})}{10.000}\right)$$
   Dibulatkan HalfUp ke kelipatan langkah harga (default Rp 50):
   $$\text{mentah\_step} = \text{round}\left(\frac{\text{mentah}}{\text{langkah}}\right) \times \text{langkah}$$

3. **Batas Langkah (Step Limits)**:
   Perubahan maksimal $\pm 1.000$ bps dari harga tick terakhir:
   - $\text{max\_step} = \text{round}\left(\frac{\text{terakhir} \times 11.000}{10.000}\right)$
   - $\text{min\_step} = \text{round}\left(\frac{\text{terakhir} \times 9.000}{10.000}\right)$
   Jika $\text{mentah\_step} > \text{max\_step} \implies \text{harga} = \text{max\_step}$, status `dibatasi`.
   Jika $\text{mentah\_step} < \text{min\_step} \implies \text{harga} = \text{min\_step}$, status `dibatasi`.
   Lainnya: $\text{harga} = \text{mentah\_step}$, status `normal`.

4. **Guardrail Keras**:
   Rentang yang diizinkan: $[\text{floor}, \min(\text{ceiling}, \text{HET})]$.
   Jika $\text{harga} < \text{floor}$ atau $\text{harga} > \min(\text{ceiling}, \text{HET})$:
   - Tick DITOLAK.
   - Catat di tabel `pric_tick_rejections` dengan alasan (`BELOW_FLOOR`, `ABOVE_CEILING`, `ABOVE_HET`) dan snapshot nilai.
   - Harga efektif tetap pada harga tick terakhir.

## 3. Proyeksi Elastisitas Item 64.1 (D81-3)
Untuk simulasi/laporan dampak kenaikan harga terhadap volume penjualan:
$$q' = \text{round}\left(\frac{q \times (10.000 - \frac{e \times \Delta p_{bps}}{10.000})}{10.000}\right)$$
di mana:
$$\Delta p_{bps} = \text{round}\left(\frac{p - p_{base}}{p_{base}} \times 10.000\right)$$
$e$ adalah elastisitas bps per SKU. Proyeksi hanya dilaporkan di controller `/pricing/surge` dan tidak memengaruhi kalkulasi harga riil.

## 4. PriceLocker & Checkout (D81-4)
- Dokumen order mengunci harga via `PriceLocker::lockLine` ke `pric_price_locks` dan membuat `pric_frozen_quotes` dengan TTL 15 menit.
- Perubahan tick baru tidak mengubah harga yang telah dikunci pada order lama.
- Checkout memverifikasi status quote. Jika quote sudah expired (> 15 menit), checkout melempar pengecualian `QuoteExpiredException` yang diterjemahkan menjadi respons HTTP 409 Conflict disertai penawaran harga terbaru.

## 5. Command & Audit (D81-5)
- `pricing:tick`: Terjadwal, menghasilkan tick untuk setiap SKU dalam slot 5 menit saat ini secara idempoten `{prefiks}:tick:{sku}:{slot}`.
- `pricing:audit`:
  1. Memverifikasi seluruh baris order pada `pric_price_locks` konsisten dengan frozen quotes (0 selisih).
  2. Memverifikasi tidak ada tick di `pric_price_ticks` yang berada di luar guardrail [floor, min(ceiling, HET)].
  3. Memverifikasi seluruh penolakan guardrail tercatat di `pric_tick_rejections`.
  4. Kompatibel dengan `PricingAuditCorruptionFixture` yang ada.

