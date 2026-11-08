# Algoritma: LoadAndBalanceChecker (A3)

## 1. Lokasi Kode & Arsitektur
- **Kelas Algoritma:** `Modules\Shared\Domain\Algorithms\Aviation\LoadAndBalanceChecker`
- **Sertifikat:** `Modules\Shared\Domain\Algorithms\Aviation\LoadAndBalanceCertificate`
- **Verifier:** `Modules\Shared\Domain\Algorithms\Aviation\LoadAndBalanceCertificateVerifier`
- **Hasil:** `Modules\Shared\Domain\Algorithms\Aviation\LoadAndBalanceResult`
- **Pillar/Lini:** Lini 26 (Aviasi, Fase 177)
- **Kompleksitas Waktu:** $O(S)$ di mana $S$ adalah jumlah stasiun pemuatan (stasiun kursi, kompartemen kargo, tangki bahan bakar).
- **Kompleksitas Ruang:** $O(1)$ kalkulasi skalar berbasis integer.

## 2. Intuisi Bisnis & Prinsip Fisika Penerbangan
Penerbangan pesawat membutuhkan pengendalian distribusi massa yang ketat untuk menjamin stabilitas aerodinamika:
1. **Maximum Take-Off Weight (MTOW):** Berat lepas landas maksimum struktural pesawat. Kelebihan muatan membahayakan performa tanjak dan integritas sayap.
2. **Pusat Gravitasi (Center of Gravity / CG):** Titik resultan gravitasi seluruh beban pesawat. CG harus berada di dalam amplop aman (*flight envelope*):
   - Jika CG terlalu ke depan ($CG < CG_{\text{fwd}}$), elevator horizontal stabiliser tidak mampu mengangkat hidung pesawat saat rotasi lepas landas (*nose-heavy*).
   - Jika CG terlalu ke belakang ($CG > CG_{\text{aft}}$), pesawat kehilangan stabilitas statis memanjang dan dapat mengalami pitch-up tak terkendali (*tail-heavy* / stall).

## 3. Rumus Matematis & Spesifikasi Logika
$$\text{TotalWeight} = W_{\text{fuel}} + \sum_{i=1}^S W_i$$

$$\text{TotalMoment} = (W_{\text{fuel}} \times A_{\text{fuel}}) + \sum_{i=1}^S (W_i \times A_i)$$

$$\text{CG} = \left\lfloor \frac{\text{TotalMoment}}{\text{TotalWeight}} \right\rfloor$$

Kriteria Kelayakan:
- Berat: $\text{TotalWeight} \le \text{MTOW}$, jika gagal tambahkan `MTOW_EXCEEDED`.
- Batas Depan: $\text{CG} \ge CG_{\text{fwd}}$, jika gagal tambahkan `CG_FWD_LIMIT`.
- Batas Belakang: $\text{CG} \le CG_{\text{aft}}$, jika gagal tambahkan `CG_AFT_LIMIT`.

Hasil dinyatakan dalam amplop aman (`isWithinEnvelope = true`) bila tidak ada satupun pelanggaran batas terdeteksi.

## 4. Contoh Hitung Langkah Demi Langkah (Vektor Prompt)
**Spesifikasi Pesawat:**
- MTOW: 23.000 kg.
- Rentang CG Aman: [8.600 mm, 9.600 mm].
- Data Beban Dasar:
  - Bahan bakar: 3.000 kg @ lengan 9.200 mm $\implies$ Momen $= 27.600.000\text{ kg}\cdot\text{mm}$.
  - Berat Pesawat Kosong Operasional (OEW): 13.000 kg @ 9.000 mm $\implies$ Momen $= 117.000.000$.
  - Kru kabin: 180 kg @ 2.500 mm $\implies$ Momen $= 450.000$.
  - Penumpang depan: $20 \times 84\text{ kg} = 1.680\text{ kg}$ @ 5.500 mm $\implies$ Momen $= 9.240.000$.
  - Penumpang tengah: $24 \times 84\text{ kg} = 2.016\text{ kg}$ @ 9.000 mm $\implies$ Momen $= 18.144.000$.
  - Penumpang belakang: $10 \times 84\text{ kg} = 840\text{ kg}$ @ 12.500 mm $\implies$ Momen $= 10.500.000$.
  - Kargo depan: 300 kg @ 4.000 mm $\implies$ Momen $= 1.200.000$.
  - Kargo belakang: 400 kg @ 13.500 mm $\implies$ Momen $= 5.400.000$.

**Kasus Uji:**
1. **Kasus 1 (Dasar):**
   - Berat total $= 3.000 + 13.000 + 180 + 1.680 + 2.016 + 840 + 300 + 400 = 21.416\text{ kg}$.
   - Momen total $= 27.600.000 + 117.000.000 + 450.000 + 9.240.000 + 18.144.000 + 10.500.000 + 1.200.000 + 5.400.000 = 189.534.000\text{ kg}\cdot\text{mm}$.
   - $\text{CG} = \lfloor 189.534.000 / 21.416 \rfloor = \lfloor 8.850,11 \rfloor = 8.850\text{ mm}$.
   - Evaluasi: $21.416 \le 23.000$ dan $8.850 \in [8.600, 9.600] \implies$ Lolos (`isWithinEnvelope = true`).
2. **Kasus 2 (Hidung Berat / Forward Limit):**
   - Penumpang belakang $= 0\text{ kg}$, kargo belakang $= 0\text{ kg}$, kargo depan dinaikkan menjadi $1.500\text{ kg}$ @ 4.000 mm.
   - Berat total $= 21.376\text{ kg}$.
   - Momen total $= 178.434.000\text{ kg}\cdot\text{mm}$.
   - $\text{CG} = \lfloor 178.434.000 / 21.376 \rfloor = 8.347\text{ mm}$.
   - Evaluasi: $8.347 < 8.600 \implies$ Tolak dengan pelanggaran `['CG_FWD_LIMIT']`.
3. **Kasus 3 (Kelebihan Beban / MTOW Exceeded):**
   - Bahan bakar dinaikkan menjadi $6.000\text{ kg}$ @ 9.200 mm (stasiun lain tetap seperti Kasus 1).
   - Berat total $= 21.416 + 3.000 = 24.416\text{ kg}$.
   - Momen total $= 217.134.000\text{ kg}\cdot\text{mm}$.
   - $\text{CG} = \lfloor 217.134.000 / 24.416 \rfloor = 8.893\text{ mm}$.
   - Evaluasi: $24.416 > 23.000\text{ kg} \implies$ Tolak dengan pelanggaran `['MTOW_EXCEEDED']`.

## 5. Sertifikat & Verifikasi Integritas
Hasil perhitungan dibungkus dalam `LoadAndBalanceCertificate` yang memverifikasi bahwa status amplop konsisten dengan daftar pelanggaran dan kalkulasi CG adalah floor dari momen dibagi berat. Kegagalan memicu `InvalidCertificateException`.

