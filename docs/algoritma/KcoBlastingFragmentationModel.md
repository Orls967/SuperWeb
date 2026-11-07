# Spesifikasi Matematis & Sertifikasi Algoritma: KCO Kuz-Ram Blasting Fragmentation Model (M8)

## 1. Identifikasi & Metrik Algoritma M8

- **Nama Algoritma**: `KcoBlastingFragmentationModel`
- **Namespace Modul**: `Modules\Shared\Domain\Algorithms\Blasting`
- **Sertifikasi / Verifier**: `KcoBlastingFragmentationVerifier`
- **Domain Aplikasi**: Rekayasa peledakan batuan tambang (*drill & blast*), pemodelan kurva fragmentasi ayakan muckpile, optimasi konsumsi energi crusher primer pabrik pengolahan.
- **Kompleksitas Asimtotik Waktu**: $\mathcal{O}(S)$, di mana $S$ adalah jumlah ukuran fraksi ayakan yang dievaluasi.
- **Kompleksitas Ruang Memori**: $\mathcal{O}(S)$, deterministik dan alokasi memori minimal.

---

## 2. Landasan Fisika & Formulasi Persamaan

Model KCO (Kuznetsov-Cunningham-Ouchterlony) merupakan penyempurnaan dari model empiris Kuz-Ram klasik untuk memprediksi kurva distribusi ukuran fragmen batuan hasil peledakan.

### 2.1 Persamaan Kuznetsov yang Dimodifikasi (Ukuran Rata-rata $X_{50}$)
Ukuran partikel rata-rata di mana 50% massa batuan lolos ayakan ($X_{50}$, dalam centimeter $[\text{cm}]$):

$$X_{50} = A \cdot \left( \frac{V_0}{Q_e} \right)^{0.8} \cdot Q_e^{1/6} \cdot \left( \frac{115}{\text{RWS}} \right)^{19/30}$$

Di mana:
- $A$: Faktor massa batuan Lilly (*Rock Mass Factor*, berkisar antara 1 s/d 14).
  $$A = 0.06 \cdot (\text{RMD} + \text{JPS} + \text{JPA} + \text{RDI} + \text{HF})$$
- $V_0$: Volume batuan tertembak per lubang ledak ($V_0 = B \times S \times H$, dalam $\text{m}^3$).
- $Q_e$: Massa bahan peledak per lubang ledak ($\text{kg}$).
- $\text{RWS}$: Kekuatan bobot relatif bahan peledak (*Relative Weight Strength* terhadap ANFO murni $= 100$).

### 2.2 Indeks Keseragaman Cunningham ($n$)
Parameter bentuk sebaran partikel ($n$, *Uniformity Index*) diturunkan dari parameter geometri pengeboran:

$$n = \left( 2.2 - 14 \cdot \frac{B}{d} \right) \cdot \sqrt{\frac{1 + S/B}{2}} \cdot \left( 1 - \frac{W_e}{B} \right) \cdot \left( \frac{L_b}{H} \right)$$

- $B$: *Burden* (jarak lubang ke bidang bebas batuan, $\text{m}$).
- $S$: *Spacing* (jarak antar lubang ledak dalam satu baris, $\text{m}$).
- $d$: Diameter lubang ledak ($\text{mm}$).
- $W_e$: Deviasi akurasi pemboran (*drilling standard deviation*, $\text{m}$).
- $L_b$: Panjang kolom isian bahan peledak ($\text{m}$).
- $H$: Tinggi jenjang / bench tambang ($\text{m}$).

### 2.3 Ukuran Karakteristik Rosin-Rammler ($X_c$)
Hubungan matematis antara $X_{50}$ dan ukuran karakteristik $X_c$ (ukuran lolos 63.2% massa batuan):

$$X_c = \frac{X_{50}}{(\ln 2)^{1/n}}$$

### 2.4 Fungsi Distribusi Kumulatif Rosin-Rammler
Fraksi massa batuan kumulatif $P(x)$ yang berukuran lebih kecil dari bukaan ayakan $x$ ($\text{mm}$):

$$P(x) = 1 - \exp\left( -\left( \frac{x}{X_c} \right)^n \right)$$

---

## 3. Evaluasi Fraksi Kritis (Boulders vs Fines)

Dalam operasional tambang efisien:
1. **Fraksi Bongkah (Boulder Oversize $> 800\text{ mm}$)**:
   $$P_{\text{boulder}} = 1 - P(800\text{ mm})$$
   Bila $P_{\text{boulder}} > 15\%$, sistem menandai bahaya kemacetan corong crusher (*hopper bridging*) dan biaya pemecahan sekunder (*secondary blasting / rock breaker*).
2. **Fraksi Halus (Fines Undersize $< 25\text{ mm}$)**:
   $$P_{\text{fines}} = P(25\text{ mm})$$
   Bila $P_{\text{fines}} > 20\%$, terjadi pemborosan energi bahan peledak dan potensi kehilangan mineral berharga akibat hancur menjadi debu.

---

## 4. Sertifikasi & Vektor Uji Baku

Algoritma diverifikasi menggunakan kelas `KcoBlastingFragmentationVerifier` terhadap kriteria:
1. **Monotonitas Kurva Ayak**: Nilai $P(x)$ wajib monoton naik terhadap ukuran saringan $x$.
2. **Batas Asimtotik**: $P(0) = 0$ dan $\lim_{x \to \infty} P(x) = 1.0$.
3. **Respon Powder Factor**: Peningkatan muatan handak $Q_e$ wajib menggeser ukuran $d_{50}$ ke ukuran yang lebih kecil.
4. **Presisi Matematika**: Galat numerik antara formula Rosin-Rammler dan integrasi empiris $< 10^{-6}$.
