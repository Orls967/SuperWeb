# G7-D56: Maritime Draft Survey & Hydrostatic Weighing Standard (UN ECE Code)

## 1. Konteks Pengukuran Muatan Kargo Curah

Dalam perdagangan komoditas curah kering berskala besar (*dry bulk cargo* seperti batu bara, bijih besi, semen clinker, pupuk, dan biji-bijian), jembatan timbang darat sering kali tidak tersedia atau tidak praktis. Metode internasional yang diakui secara hukum untuk menentukan berat kargo yang dimuat atau dibongkar adalah survei sarat kapal (*Draft Survey*), yang distandarisasi oleh *United Nations Economic Commission for Europe* (UN ECE Code of Uniform Standards and Procedures for the Performance of Draught Surveys).

Keputusan arsitektur **G7-D56** menetapkan implementasi algoritma kalkulasi draft survey deterministik dengan koreksi deformasi lambung, pemangkasan sarat (*trim corrections*), dan densitas air dermaga.

---

## 2. Metodologi Pembacaan Sarat Enam Titik (Six-Point Draft Reading)

Pembacaan skala sarat air pada lambung kapal dilakukan oleh surveyor independen bersama mualim satu:
1. $D_{\text{FP}}$ dan $D_{\text{FS}}$: Sarat Haluan Kiri (*Forward Port*) dan Kanan (*Forward Starboard*).
2. $D_{\text{MP}}$ dan $D_{\text{MS}}$: Sarat Tengah Kiri (*Midship Port*) dan Kanan (*Midship Starboard*).
3. $D_{\text{AP}}$ dan $D_{\text{AS}}$: Sarat Buritan Kiri (*Aft Port*) dan Kanan (*Aft Starboard*).

### 2.1 Rata-Rata Sarat Haluan, Tengah, dan Buritan
$$D_F = \frac{D_{\text{FP}} + D_{\text{FS}}}{2}, \quad D_M = \frac{D_{\text{MP}} + D_{\text{MS}}}{2}, \quad D_A = \frac{D_{\text{AP}} + D_{\text{AS}}}{2}$$
$$\text{Apparent Trim} = D_A - D_F$$

---

## 3. Koreksi Deformasi Lambung: Mean of Means (Quarter Mean)

Kapal niaga yang panjang mengalami lenturan struktural akibat distribusi muatan:
- **Sagging**: Bagian tengah kapal melengkung ke bawah ($D_M > \frac{D_F + D_A}{2}$).
- **Hogging**: Bagian tengah kapal melengkung ke atas ($D_M < \frac{D_F + D_A}{2}$).

Untuk mengoreksi efek deformasi kelengkungan lambung ini, dihitung sarat rata-rata kuartal (*Quarter Mean Draft* atau *Mean of Means*):
$$D_{\text{MM}} = \frac{D_F + D_A}{2}$$
$$D_{\text{MMM}} = \frac{D_{\text{MM}} + D_M}{2}$$
$$D_{\text{Quarter Mean}} = \frac{D_{\text{MMM}} + D_M}{2} = \frac{D_F + 6 D_M + D_A}{8}$$

---

## 4. Koreksi Hidrostatis Lanjutan

### 4.1 Koreksi Trim Pertama & Kedua
Jika kapal mengalami kemiringan memanjang (*trim*), titik berat bidang air (*Longitudinal Center of Flotation - LCF*) tidak berada tepat di tengah kapal ($L_{\text{pp}} / 2$):
$$\text{First Trim Correction (FTC)} = \frac{\text{Trim} \times LCF \times TPC \times 100}{L_{\text{pp}}}$$
$$\text{Second Trim Correction (STC)} = \frac{\text{Trim}^2 \times 50 \times \frac{d(MTC)}{dz}}{L_{\text{pp}}}$$

### 4.2 Koreksi Densitas Air Pelabuhan (Dock Water Density Correction)
Tabel hidrostatis kapal dihitung berdasarkan air laut standar dengan densitas $\rho_{\text{std}} = 1.025\,\text{t}/\text{m}^3$. Jika kapal berada di muara sungai atau kolam pelabuhan dengan densitas riil $\rho_{\text{dock}}$ yang diukur dengan hydrometer:
$$\text{Displacement Terkoreksi} = \text{Displacement Tabel} \times \left( \frac{\rho_{\text{dock}}}{1.025} \right)$$

---

## 5. Penentuan Berat Bersih Kargo Muat / Bongkar

Berat kargo ditentukan dari selisih penimbangan hidrostatis antara survei awal (*Initial Draft Survey*) dan survei akhir (*Final Draft Survey*):
$$\text{Net Cargo Weight} = (\text{Displacement}_{\text{Final}} - \text{Deductibles}_{\text{Final}}) - (\text{Displacement}_{\text{Initial}} - \text{Deductibles}_{\text{Initial}})$$
Di mana *Deductibles* mencakup total berat bahan bakar bunker (VLSFO/LSMGO), minyak pelumas, air tawar (*fresh water*), air balas (*ballast water*), dan konstanta kapal (*ship's constant*).

Layanan `PerformDraftSurveyInspectionAction` memvalidasi angka penimbangan kargo terhadap toleransi Konosemen (*Bill of Lading tolerance* $\le 0.5\%$).
