# Spesifikasi Matematis & Sertifikasi Algoritma: Hydrostatic Draft Survey Reconciler (M9)

## 1. Identifikasi & Metrik Algoritma M9

- **Nama Algoritma**: `HydrostaticDraftSurveyReconciler`
- **Namespace Modul**: `Modules\Shared\Domain\Algorithms\Marine`
- **Sertifikasi / Verifier**: `HydrostaticDraftSurveyVerifier`
- **Domain Aplikasi**: Survei sarat air kapal tongkang (*barge draft survey*), interpolasi hidrostatik perpindahan massa (*displacement mt*), rekonsiliasi muatan mineral curah basah (*Wet Metric Tons - WMT*).
- **Kompleksitas Asimtotik Waktu**: $\mathcal{O}(T)$, di mana $T$ adalah jumlah baris entri tabel hidrostatik kapal tongkang (pencarian biner / interpolasi linier).
- **Kompleksitas Ruang Memori**: $\mathcal{O}(1)$, alokasi tetap.

---

## 2. Landasan Hidrodinamika & Formulasi Matematis

Perhitungan berat muatan di atas kapal tongkang didasarkan pada Prinsip Archimedes: *Benda terapung memindahkan massa cairan yang sama besar dengan total massa benda tersebut*.

### 2.1 Pembacaan Draft 6-Titik & Rata-rata Awal
Sarat air (*draft*) diukur pada enam posisi lambung kapal:
- Haluan: $DFP$ (*Draft Forward Port*), $DFS$ (*Draft Forward Starboard*).
- Tengah: $DMP$ (*Draft Midship Port*), $DMS$ (*Draft Midship Starboard*).
- Buritan: $DAP$ (*Draft Aft Port*), $DAS$ (*Draft Aft Starboard*).

Draf rata-rata per penampang:
$$D_F = \frac{DFP + DFS}{2}, \quad D_M = \frac{DMP + DMS}{2}, \quad D_A = \frac{DAP + DAS}{2}$$

### 2.2 Quarter Mean Draft (Koreksi Defleksi Hogging & Sagging)
Lambung tongkang baja sering mengalami defleksi elastis akibat distribusi muatan tidak rata:
- Jika $D_M > (D_F + D_A)/2$: Terjadi **Sagging** (lambung melengkung ke bawah di tengah).
- Jika $D_M < (D_F + D_A)/2$: Terjadi **Hogging** (lambung melengkung ke atas di tengah).

Untuk menghilangkan bias volume defleksi lambung, digunakan rumus rata-rata kuartil (*Mean of Means / Quarter Mean Draft*):

$$D_{\text{QM}} = \frac{D_F + 6 \cdot D_M + D_A}{8}$$

### 2.3 Interpolasi Linear Tabel Hidrostatik
Dari tabel hidrostatik kapal tongkang yang disediakan arsitek perkapalan, dibaca nilai perpindahan dasar ($\Delta_0$) pada draf $D_{\text{QM}}$:

$$\Delta_0 = \Delta_1 + \left( \frac{D_{\text{QM}} - d_1}{d_2 - d_1} \right) \cdot (\Delta_2 - \Delta_1)$$

Di mana $d_1, d_2$ adalah interval draf tabel hidrostatik terdekat, dan $\Delta_1, \Delta_2$ adalah nilai *displacement* padanannya.

### 2.4 Koreksi Trim Pertama & Kedua
Trim kapal didefinisikan sebagai selisih draf buritan terhadap haluan:

$$\text{Trim} = D_A - D_F$$

Koreksi trim pertama (*First Trim Correction* - FTC) memperhitungkan pergeseran pusat daya apung membujur (*Longitudinal Center of Flotation* - LCF):

$$\text{FTC} = \frac{\text{Trim} \cdot \text{LCF} \cdot \text{TPC} \cdot 100}{\text{LBP}}$$

- $\text{TPC}$: *Tonnes Per Centimeter Immersion* (ton/cm).
- $\text{LBP}$: *Length Between Perpendiculars* (panjang antara garis tegak buritan dan haluan tongkang, meter).

### 2.5 Koreksi Densitas Air
Tabel hidrostatik dikalibrasi untuk air laut standar internasional ($\rho_{\text{standar}} = 1.025\text{ kg/L}$). Bila tongkang berada di air tawar atau muara payau dengan densitas terukur $\rho_{\text{aktual}}$:

$$\Delta_{\text{density}} = \Delta_{\text{trim\_corrected}} \cdot \left( \frac{\rho_{\text{aktual}}}{1.025} \right)$$

### 2.6 Tonase Muatan Bersih (Net Cargo WMT)
Muatan bersih mineral dihitung dari pengurangan kondisi muat penuh (*final loaded*) terhadap kondisi kosong (*initial light*) setelah deduksi air balas (*ballast*) dan bahan bakar:

$$\text{Net Cargo WMT} = (\Delta_{\text{final}} - \text{Deduksi}_{\text{final}}) - (\Delta_{\text{initial}} - \text{Deduksi}_{\text{initial}})$$

---

## 3. Sertifikasi & Vektor Uji Baku

Algoritma diverifikasi menggunakan kelas `HydrostaticDraftSurveyVerifier` terhadap kriteria:
1. **Even Keel Precision**: Pada kondisi tanpa trim ($\text{Trim} = 0$), koreksi trim harus presisi bernilai $0.00\text{ mt}$.
2. **Koreksi Air Tawar**: Pengukuran pada air tawar murni ($\rho = 1.000\text{ kg/L}$) harus menghasilkan deduksi bobot apung persis rasio $1.000 / 1.025$.
3. **Deteksi Hogging / Sagging**: Perhitungan mean-of-means harus presisi mereduksi bias penampang tengah sesuai bobot $6/8$.
4. **Verifikasi Toleransi Timbangan Jetty**: Perhitungan selisih persentase terhadap timbangan jembatan timbang pelabuhan diverifikasi akurat hingga 2 desimal.
