# PANDUAN TEKNIS: ALGORITMA MARITIM BERSERTIFIKAT (A10 s.d. A14)

## 1. Ikhtisar Mesin Matematis Maritim

Fase 180 mengimplementasikan 5 mesin algoritma independen tanpa dependensi eksternal (*framework-agnostic*), beralamat di `Modules\Shared\Domain\Algorithms\Port\*` dan diakses melalui namespace aplikasi `Modules\Port\Domain\Algorithms\*`. Panduan teknis ini merinci fondasi matematis, vektor uji numerik, batas toleransi aproksimasi, serta penanganan kondisi batas (*edge cases*).

---

## 2. A10: BunkerFuelConsumptionOptimizer

### 2.1 Teori Dasar Hidrodinamika Propulsi
Resistensi total badan kapal yang bergerak di air terdiri atas resistensi gesek permukaan basah (*frictional resistance*) dan resistensi pembentukan gelombang haluan (*wave-making resistance*). Menurut hukum kuadratik-kubik Froude & Admiralti:
$$P_E = R_T \times V \propto \Delta^{2/3} \times V^3$$
Tenaga efektif mesin induk ($P_E$) yang dibutuhkan berbanding lurus dengan massa perpindahan pangkat $2/3$ dan kecepatan jelajah pangkat 3.

### 2.2 Penalti Cuaca (Weather Margin)
Dalam kondisi riil di samudra, angin kencang dan gelombang laut memperlambat kapal atau menuntut daya mesin lebih tinggi untuk mempertahankan kecepatan yang sama:
- **Beaufort $\le 4$**: Laut tenang hingga riak sedang (*calm / gentle breeze*). Penalti diabaikan ($0\%$).
- **Beaufort $> 4$**: Penalti konsumsi BBM meningkat sebesar $4.5\%$ per skala Beaufort di atas 4, dibagi faktor efisiensi lambung:
$$\text{Faktor Penalti Cuaca} = 1.0 + \frac{(BN - 4) \times 0.045}{\eta_{\text{hull}}}$$
Di mana $\eta_{\text{hull}}$ umumnya bernilai $1.0$ untuk lambung bersih baru dan dapat turun hingga $0.85$ jika lambung kapal telah ditumbuhi teritip/lumut (*fouling effect*).

### 2.3 Vektor Uji Numerik & Verifikasi
- **Parameter Uji**:
  - Daya mesin dasar: 8.800 kW, Kecepatan desain: 14.2 knots, Sarat desain: 13.0 m, Konsumsi dasar: 28.5 MT/hari.
  - Kecepatan aktual: 12.5 knots, Sarat aktual: 11.5 m, Skala Beaufort: 6, Efisiensi lambung: 1.0.
- **Kalkulasi**:
  - Rasio sarat: $11.5 / 13.0 = 0.8846$
  - Rasio kecepatan: $12.5 / 14.2 = 0.8803$
  - Konsumsi teoritis laut tenang: $28.5 \times (0.8846)^{2/3} \times (0.8803)^3 = 28.5 \times 0.9213 \times 0.6821 = 17.91\,\text{MT/hari}$
  - Penalti Beaufort 6: $(6 - 4) \times 0.045 = 0.090$ ($+9.0\%$)
  - Konsumsi akhir berpenalti: $17.91 \times 1.090 = 19.52\,\text{MT/hari}$

---

## 3. A11: LaytimeDemurrageCalculator

### 3.1 Resolusi Jam Interupsi Cuaca (Statement of Facts)
Operasi bongkar muat kargo curah kering (*dry bulk*) seperti semen clinker, pupuk urea, atau gandum harus dihentikan seketika saat hujan turun untuk mencegah kerusakan muatan.
Pada lembar fakta SOF:
- Setiap insiden hujan dicatat dengan stempel waktu mulai (*interruption start*) dan selesai (*interruption end*).
- Jika klausula kontrak adalah `WWD` (*Weather Working Day*) dan kapal belum jatuh dalam status demurrage, durasi jeda hujan dipotong dari akumulasi laytime terpakai.
- Jika kapal telah melewati batas waktu laytime (*already on demurrage*), jeda hujan **TIDAK MENGHENTIKAN** penghitungan denda, sesuai asas *once on demurrage always on demurrage*.

### 3.2 Vektor Uji Despatch vs Demurrage
- **Kasus Demurrage**:
  - Laytime diizinkan: 72 jam.
  - Waktu berjalan riil: 96 jam.
  - Interupsi hujan selama masa laytime sah: 4 jam.
  - Laytime terpakai bersih: $96 - 4 = 92\,\text{jam}$.
  - Kelebihan waktu (*Demurrage Hours*): $92 - 72 = 20\,\text{jam}$.
  - Tarif harian demurrage: \$18.000 / hari (\$750 / jam).
  - Nilai tagihan invoice: $20 \times \$750 = \$15.000$.

---

## 4. A12: CiiCarbonIntensityRatingEngine

### 4.1 Koreksi Kapasitas Kapal (Capacity Corrections)
Kapasitas kapal niaga mengacu pada bobot mati (*DWT*) dalam metrik ton. Untuk jenis kapal tertentu seperti car carrier (*Ro-Ro passenger/cargo*), kapasitas diukur dalam *Gross Tonnage (GT)*.

### 4.2 Ambang Batas Peringkat MEPC.354(78)
Deviasi batas peringkat ditentukan oleh nilai $d_1, d_2, d_3, d_4$:
Untuk Bulk Carrier kapasitas 58.000 DWT:
- $d_1 = 0.18$, $d_2 = 0.08$, $d_3 = 0.08$, $d_4 = 0.18$.
- Batas Grade A: Rasio $\le \exp(-0.18) = 0.835$ (Lebih hemat $\ge 16.5\%$ dari standar IMO).
- Batas Grade B: $0.835 < \text{Rasio} \le \exp(-0.08) = 0.923$.
- Batas Grade C: $0.923 < \text{Rasio} \le \exp(0.08) = 1.083$ (Batas standar kepatuhan).
- Batas Grade D: $1.083 < \text{Rasio} \le \exp(0.18) = 1.197$ (Perlu evaluasi).
- Batas Grade E: $\text{Rasio} > 1.197$ (Inferior, dilarang berlayar komersial tanpa SEEMP Part III).

---

## 5. A13: TimeCharterEquivalentCalculator

### 5.1 Komponen Pengeluaran Pelayaran (Voyage Expenses)
1. **Biaya Bahan Bakar**: $\sum (\text{BBM Terpakai} \times \text{Harga Pasar Bunker per MT})$.
2. **Biaya Pelabuhan (Port Costs)**: PDA pelabuhan muat + PDA pelabuhan bongkar.
3. **Biaya Terusan / Kanal**: Tarif transit Terusan Suez atau Terusan Panama (bila melintas).
4. **Komisi Broker**: Lazimnya $1.25\%$ s.d. $3.75\%$ dari nilai kotor uang tambang (*gross freight*).

### 5.2 Rasio Evaluasi Komersial (TCE vs OPEX)
$$\text{Operating Margin (\%)} = \frac{\text{Net Daily TCE} - \text{Daily OPEX}}{\text{Daily OPEX}} \times 100\%$$
- Jika margin bertanda positif, pelayaran menghasilkan keuntungan bersih di atas biaya kepemilikan kapal.
- Jika margin bertanda negatif, kapal beroperasi di bawah titik impas harian (*loss-making voyage*).

---

## 6. A14: GeneralAverageContributionDistributor

### 6.1 Prinsip Perhitungan York-Antwerp Rule XVII
Nilai kontributif kapal dihitung dengan mengambil nilai suara kapal saat tiba di pelabuhan tujuan dalam kondisi selamat/rusak, dikurangi biaya perbaikan darurat non-GA, dan **DITAMBAH** nilai pengorbanan kapal yang dipulihkan (*Amount Made Good for Ship*).
Hal serupa berlaku untuk muatan kargo:
$$\text{Contributory Value}_{\text{kargo}} = \text{Nilai Faktur Komersial C&F} + \text{Nilai Kargo yang Dikorbankan/Dibuang (Made Good)}$$

Pengeluaran darurat jasa penyelamatan (*salvage awards*) tidak menambah nilai fisik kargo/kapal sehingga tidak dimasukkan ke dalam komponen *Made Good*.
