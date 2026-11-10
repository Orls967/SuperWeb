# Algoritma A1: WcagContrastCalculator

**Kelas:** `Modules\Shared\Domain\Algorithms\Ui\WcagContrastCalculator`  
**Paket:** Shared Domain Algorithms (Aksesibilitas UI)  
**Sertifikat:** Dilengkapi verifier matematis SHA-256 (`ContrastCertificateVerifier`)  
**Standar Acuan:** W3C Web Content Accessibility Guidelines (WCAG) 2.1 SC 1.4.3 & SC 1.4.11  

---

## 1. Intuisi & Latar Belakang

Penetapan kontras warna antarmuka pengguna merupakan fondasi aksesibilitas bagi pengguna dengan gangguan penglihatan warna (*color vision deficiency*), katarak, presbiopia, atau operator yang menggunakan layar gawai di bawah terik matahari lapangan. Standar WCAG 2.1 menetapkan rasio kontras minimum:
- Teks normal Level AA: minimal 4.5:1.
- Teks besar (minimal 18 pt atau 14 pt tebal): minimal 3.0:1.
- Komponen antarmuka pengguna dan batas visual interaktif: minimal 3.0:1.
- Teks normal Level AAA: minimal 7.0:1.

Dalam sistem komputasi terdistribusi, kalkulasi berbasis angka pecahan (*floating-point*) dapat menimbulkan deviasi mikro akibat perbedaan implementasi FPU arsitektur x86_64 vs ARM64 (misal: rasio dihitung $4.499999999$ vs $4.500000001$). Untuk menjamin keadilan audit dan determinisme absolut, algoritma A1 dirancang menggunakan **aritmetika integer murni** dengan presisi *parts-per-million* (ppm) dan *basis points* (bps).

---

## 2. Formulasi Matematis

### 2.1. Normalisasi & Linearisasi Kanal sRGB
Diberikan representasi warna heksadesimal `#RRGGBB`, ekstrak tiga kanal warna integer $R, G, B \in [0, 255]$.
Untuk setiap kanal $c \in \{R, G, B\}$, hitung nilai kanal terlinearisasi $C_{lin}$ dalam skala integer per sejuta ($0 \dots 1.000.000$ ppm):
$$
C_{norm} = \frac{c}{255}
$$
$$
C_{lin} = 
\begin{cases}
\left\lfloor \frac{C_{norm}}{12.92} \times 1.000.000 + 0.5 \right\rfloor & \text{jika } C_{norm} \le 0.04045 \\
\left\lfloor \left(\frac{C_{norm} + 0.055}{1.055}\right)^{2.4} \times 1.000.000 + 0.5 \right\rfloor & \text{jika } C_{norm} > 0.04045
\end{cases}
$$
Operasi pemangkatan $x^{2.4}$ adalah satu-satunya titik penggunaan fungsi floating-point `pow()`, yang hasilnya langsung dibulatkan *HalfUp* ke bilangan bulat ppm. Seluruh alur kalkulasi berikutnya murni menggunakan integer.

### 2.2. Perhitungan Luminansi Relatif ($L$)
Luminansi relatif $L$ dihitung menggunakan koefisien standar W3C untuk ruang warna sRGB Rec. 709:
$$
L_{ppm} = \left\lfloor \frac{2126 \times R_{lin} + 7152 \times G_{lin} + 722 \times B_{lin} + 5000}{10000} \right\rfloor
$$
Rentang nilai $L_{ppm}$ adalah bilangan bulat $[0, 1.000.000]$.

### 2.3. Perhitungan Rasio Kontras ($R_{bps}$)
Diberikan dua warna dengan luminansi $L_1$ dan $L_2$, tentukan $L_{terang} = \max(L_1, L_2)$ dan $L_{gelap} = \min(L_1, L_2)$.
Konstanta bias WCAG $0.05$ setara dengan $50.000$ ppm. Rasio kontras dinyatakan dalam satuan *basis points* ($1\times = 10.000$ bps):
$$
R_{bps} = \left\lfloor \frac{(L_{terang} + 50.000) \times 10.000}{L_{gelap} + 50.000} \right\rfloor
$$
Rentang nilai rasio berkisar dari $10.000$ bps (rasio 1:1, identik) hingga $210.000$ bps (rasio 21:1, hitam di atas putih sempurna).

---

## 3. Contoh Hitungan Langkah demi Langkah (Vektor Prompt)

### Contoh 1: Abu-abu `#767676` di atas Putih `#FFFFFF`
1. **Latar Belakang `#FFFFFF`:**
   - $R = 255, G = 255, B = 255 \implies C_{norm} = 1.0$.
   - $C_{lin} = 1.000.000$ ppm.
   - $L_{putih} = \lfloor (2126 \times 1.000.000 + 7152 \times 1.000.000 + 722 \times 1.000.000 + 5000)/10000 \rfloor = 1.000.000$ ppm.
2. **Warna Teks `#767676`:**
   - $c = 118 \implies C_{norm} = 118 / 255 \approx 0.462745$.
   - Karena $C_{norm} > 0.04045$, $C_{lin} = \lfloor ((0.462745 + 0.055)/1.055)^{2.4} \times 1.000.000 + 0.5 \rfloor = 181.164$ ppm.
   - $L_{teks} = \lfloor (2126 \times 181.164 + 7152 \times 181.164 + 722 \times 181.164 + 5000)/10000 \rfloor = 181.164$ ppm.
3. **Rasio Kontras:**
   - $L_{terang} = 1.000.000$, $L_{gelap} = 181.164$.
   - Pembilang: $(1.000.000 + 50.000) \times 10.000 = 10.500.000.000$.
   - Penyebut: $181.164 + 50.000 = 231.164$.
   - Rasio: $\lfloor 10.500.000.000 / 231.164 \rfloor = 45.422$ bps ($4.54:1$).
   - **Keputusan:** $45.422 \ge 45.000 \implies$ **LULUS LEVEL AA**.

### Contoh 2: Abu-abu `#777777` di atas Putih `#FFFFFF`
- Kanal $c = 119 \implies C_{lin} = 184.475$ ppm.
- $L = 184.475$ ppm.
- Penyebut: $184.475 + 50.000 = 234.475$.
- Rasio: $\lfloor 10.500.000.000 / 234.475 \rfloor = 44.780$ bps ($4.478:1$).
- **Keputusan:** $44.780 < 45.000 \implies$ **GAGAL LEVEL AA**.

---

## 4. Analisis Kompleksitas & Karakteristik Kinerja

- **Kompleksitas Waktu:** $\mathcal{O}(1)$ konstan, terdiri dari ekstraksi kanal bitwise dan operasi perkalian/pembagian integer.
- **Kompleksitas Ruang:** $\mathcal{O}(1)$ tanpa alokasi memori dinamis.
- **Kasus Tepi:**
  - Warna identik (`#FFFFFF` di atas `#FFFFFF`): Pembilang sama dengan penyebut $\implies$ tepat $10.000$ bps (1:1).
  - Kontras maksimum (`#000000` di atas `#FFFFFF`): $(1.000.000 + 50.000) \times 10.000 / 50.000 = 10.500.000.000 / 50.000 = 210.000$ bps (21:1).

---

## 5. Sertifikat & Verifikasi

Setiap eksekusi kalkulasi kontras mengembalikan objek sertifikat:
```json
{
  "color_a": "#767676",
  "color_b": "#FFFFFF",
  "luminance_a_ppm": 181164,
  "luminance_b_ppm": 1000000,
  "ratio_bps": 45422,
  "passes_aa_normal": true,
  "passes_aa_large": true,
  "passes_aaa_normal": false,
  "signature": "sha256_hash_dari_payload_kanonikal"
}
```
Kelas `ContrastCertificateVerifier` melakukan verifikasi integritas tanda tangan kriptografis sertifikat di lingkungan produksi.

