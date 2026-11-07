# Konsep Teknis: Underground Digital Twin, Geoteknik Lereng, Peledakan Terkendali & Pengapalan Tongkang (Fase 119)

## 1. Pendahuluan & Latar Belakang Operasional Tambang Terpadu

Fase 119 merupakan pilar ekspansi kritis dalam arsitektur penambangan modern (*Integrated Mine-to-Port Digital Ecosystem*) pada Modul Pertambangan (*Mining*). Integrasi ini menjembatani operasi ekstraksi bawah tanah (*underground stoping*), ventilasi tambang dalam, rekayasa peledakan batuan (*drill & blast*), pemantauan kestabilan lereng (*geotechnical slope radar & InSAR*), fotogrametri drone volumetrik, hingga logistik pengapalan curah (*bulk marine barge logistics*) di pelabuhan khusus tambang (*special terminal jetty*).

Dalam lanskap regulasi mineral dan batubara Indonesia (UU No. 3/2020 jo. Kepmen ESDM No. 1827 K/30/MEM/2018), keselamatan operasi tambang bawah tanah dan kestabilan lereng merupakan area dengan toleransi risiko nol (*zero-tolerance regulatory compliance*). Sistem ini mengimplementasikan pemantauan berbasis sensor cerdas, perhitungan fisika mekanika batuan, orkestrasi *saga pattern* dengan kompensasi otomatis, dan integrasi akuntansi biaya buku besar (*ledger posting*).

```
+---------------------------------------------------------------------------------------------------+
|                        INTEGRATED MINE-TO-PORT LIFECYCLE (FASE 119)                               |
+---------------------------------------------------------------------------------------------------+
|  [1. UNDERGROUND TWIN]       [2. DRILL & BLAST]         [3. GEOTECH SLOPE]      [4. MARINE BARGE] |
|   - 3D Stope Geometry         - Blast Perimeter Clear    - InSAR / SSR Radar     - 6-Point Draft  |
|   - RMR Geomechanics          - Telematics Geofence      - Bishop FoS Slices     - Hydrostatic M9 |
|   - Hardy-Cross Solver        - Siren 3-Stage Lock       - Pore Water Pressure   - Seal & SPB Port|
|   - Multi-Gas Sensor          - KCO Kuz-Ram Model        - Evacuation Trigger    - Freight Settle |
+---------------------------------------------------------------------------------------------------+
```

---

## 2. Pemodelan Digital Twin Bawah Tanah & Mekanika Batuan Stope

Tambang bawah tanah beroperasi dengan membuat rongga bukaan (*excavations*) terencana yang disebut *stope*. Pemilihan metode tambang (apakah *Sublevel Open Stoping*, *Cut-and-Fill Stoping*, atau *Square Set*) sangat bergantung pada karakteristik massa batuan yang diukur menggunakan sistem Bieniawski (1989) *Rock Mass Rating* (RMR):

$$\text{RMR} = R_1 + R_2 + R_3 + R_4 + R_5 + R_6$$

Di mana:
1. $R_1$: Kekuatan batuan utuh (*Uniaxial Compressive Strength* - UCS, bobot 0–15).
2. $R_2$: Kualitas inti bor batuan (*Rock Quality Designation* - RQD, bobot 3–20).
3. $R_3$: Spasi bidang diskontinuitas / kekar (bobot 5–20).
4. $R_4$: Kondisi bidang diskontinuitas (kekasaran, pelapukan, bukaan, isian; bobot 0–30).
5. $R_5$: Kondisi air tanah / rembesan (*groundwater inflow*; bobot 0–15).
6. $R_6$: Penyesuaian orientasi jurus dan kemiringan (*strike & dip adjustment*; bobot -12 s/d 0).

### Klasifikasi Rekayasa Penyangga (Ground Support System)
Berdasarkan nilai RMR total:
- **Kelas I (RMR 81–100, Very Good Rock)**: Stope mandiri tanpa penyangga berat, cukup *spot bolting* lokal.
- **Kelas II (RMR 61–80, Good Rock)**: Baut batuan (*rock bolt*) sistematis spasi 2.0 meter dengan *wire mesh*.
- **Kelas III (RMR 41–60, Fair Rock)**: *Rock bolt* spasi 1.5 meter dikombinasikan dengan *fiber-reinforced shotcrete* tebal 50 mm.
- **Kelas IV (RMR 21–40, Poor Rock)**: *Rock bolt* spasi 1.0 meter, *shotcrete* 100 mm, dan penyangga baja lengkung (*steel sets*).
- **Kelas V (RMR 0–20, Very Poor Rock)**: Penyangga berat *cast-in-place concrete liner* dan *invert arch*.

Sistem secara deterministik menolak rencana stope yang tidak menyediakan sistem penyangga minimum sesuai kelas RMR yang terdeteksi.

---

## 3. Dinamika Aliran Udara & Solver Ventilasi Hardy-Cross (M7)

Jaringan ventilasi tambang bawah tanah adalah sistem fluida tak termampatkan (*incompressible aerodynamic network*) yang tunduk pada Hukum Kirchhoff:

1. **Hukum Arus Kirchhoff (KCL - Konservasi Massa di Titik Simpul / Junction)**:
   $$\sum_{j} Q_j = 0$$
   Jumlah debit udara ($Q$, $\text{m}^3/\text{s}$) yang masuk ke sebuah titik percabangan harus sama dengan debit udara yang keluar.

2. **Hukum Tegangan Kirchhoff (KVL - Konservasi Tekanan di Loop Tertutup)**:
   $$\sum_{i \in \text{Loop}} \Delta h_i = 0$$
   Total kehilangan tekanan gesek ($\Delta h_i$) di sepanjang lintasan loop tertutup harus seimbang dengan total tekanan penggerak kipas (*fan pressure* $H_f$) dan tekanan ventilasi alami (*natural ventilation head* $H_n$).

### Penurunan Rumus Tahanan Atkinson
Kehilangan tekanan aerodinamika dihitung menggunakan persamaan Atkinson (1854):

$$\Delta h = R \cdot Q \cdot |Q|$$

Di mana resistansi aerodinamika cabang ($R$, $\text{N}\cdot\text{s}^2/\text{m}^8$) diturunkan dari parameter fisik shaft/airway:

$$R = \frac{k \cdot \text{Per} \cdot L}{A^3} \cdot \frac{\rho}{1.20}$$

- $k$: Koefisien friksi dinding airway ($\text{kg}/\text{m}^3$). Dinding beton halus $k \approx 0.0035$; terowongan batuan kasar ledak $k \approx 0.0120$.
- $\text{Per}$: Keliling basah penampang airway ($\text{m}$).
- $L$: Panjang terowongan atau kedalaman shaft ($\text{m}$).
- $A$: Luas penampang bebas aliran udara ($\text{m}^2$).
- $\rho$: Densitas udara tambang aktual ($\text{kg}/\text{m}^3$), dikoreksi terhadap elevasi bawah laut dan temperatur basah/kering.

### Metode Iteratif Hardy-Cross dengan Koreksi Orde Satu
Jika estimasi aliran awal pada cabang-cabang loop menghasilkan galat penutupan loop:

$$\Delta H_m = \sum_{i \in m} \left( \epsilon_i R_i Q_i |Q_i| - H_{f,i} - H_{n,i} \right) \neq 0$$

Koreksi debit loop $\Delta Q_m$ dihitung menggunakan ekspansi deret Taylor orde satu:

$$\Delta Q_m = -\frac{\sum_{i \in m} \left( \epsilon_i R_i Q_i |Q_i| - H_{f,i} - H_{n,i} \right)}{2 \sum_{i \in m} R_i |Q_i|}$$

Di mana $\epsilon_i = +1$ jika arah aliran searah putaran loop, dan $\epsilon_i = -1$ jika berlawanan. Solver mengulangi koreksi ini secara simultan di seluruh loop hingga $\max |\Delta Q_m| < 0.001\text{ m}^3/\text{s}$.

---

## 4. Analisis Peledakan Muckpile Model KCO (M8) & Radius Bahaya Flyrock

Evaluasi hasil peledakan batuan keras (*hard rock blasting*) menggunakan gabungan model Kuz-Ram klasik dan perbaikan Cunningham-Ouchterlony (KCO Model).

### Persamaan Karakteristik KCO
Ukuran partikel karakteristik $X_{50}$ (ukuran ayakan di mana 50% massa lolos) dihitung melalui:

$$X_{50} = A \cdot \left( \frac{V_0}{Q_e} \right)^{0.8} \cdot Q_e^{1/6} \cdot \left( \frac{115}{\text{RWS}} \right)^{19/30}$$

- $A$: Faktor massa batuan (*rock factor*, 1–14).
- $V_0 / Q_e$: Kebalikan dari *powder factor* spesifik ($\text{m}^3/\text{kg}$).
- $Q_e$: Massa bahan peledak per lubang ledak ($\text{kg}$).
- $\text{RWS}$: *Relative Weight Strength* bahan peledak relatif terhadap ANFO standar ($\text{ANFO} = 100$).

Fungsi distribusi kumulatif massa batuan lolos ayakan $x$ menggunakan persamaan Rosin-Rammler dua parameter:

$$P(x) = 1 - \exp\left( -\left( \frac{x}{X_c} \right)^n \right)$$

Di mana indeks keseragaman $n$ (*uniformity index*) diturunkan dari geometri pola pemboran:

$$n = \left( 2.2 - 14 \frac{B}{d} \right) \cdot \sqrt{\frac{1 + S/B}{2}} \cdot \left( 1 - \frac{W_e}{B} \right) \cdot \left( \frac{L_b}{H} \right)$$

- $B$: Beban batuan di depan lubang (*Burden*, $\text{m}$).
- $S$: Jarak antar lubang ledak (*Spacing*, $\text{m}$).
- $d$: Diameter lubang ledak ($\text{mm}$).
- $L_b$: Panjang kolom muatan bahan peledak ($\text{m}$).
- $H$: Tinggi jenjang (*Bench height*, $\text{m}$).

### Interlock Geofence dan Radius Aman Peledakan
Sebelum detonasi elektronik dapat dieksekusi:
1. Radius bahaya flyrock dihitung dengan formula Lundborg: $L_{\max} = 260 \cdot d^{2/3}$ meter dikalikan faktor keselamatan 1.50 (minimum 500 meter baku industri).
2. Gateway telematika GPS memindai koordinat pekerja tambang aktif via `PersonnelTelematicsGeofenceGateway`.
3. Jika ditemukan pelanggaran (personel berada di dalam radius eksklusi), sistem memicu interlock keras (*safety interlock abort*) dan penembakan ditolak.

---

## 5. Kestabilan Lereng Geoteknik & Metode Irisan Bishop Disederhanakan

Kestabilan lereng tambang terbuka (*open pit*) dan tambang kuari (*quarry*) dianalisis menggunakan metode kesetimbangan batas (*limit equilibrium*) irisan busur lingkaran Bishop disederhanakan (*Simplified Bishop Method of Slices*).

### Formulasi Faktor Keamanan (Factor of Safety - FoS)
FoS didefinisikan sebagai rasio kekuatan geser tersedia (*available shear strength*) terhadap gaya geser pemicu kelongsoran (*driving shear stress*):

$$\text{FoS} = \frac{\sum_{i=1}^n \frac{c' b_i + (W_i - u_i b_i) \tan \phi'}{m_{\alpha,i}}}{\sum_{i=1}^n W_i \sin \alpha_i}$$

Di mana faktor pembagi sudut $m_{\alpha,i}$ adalah:

$$m_{\alpha,i} = \cos \alpha_i \left( 1 + \frac{\tan \alpha_i \tan \phi'}{\text{FoS}} \right)$$

Karena suku $\text{FoS}$ muncul di kedua sisi persamaan, perhitungan dilakukan secara iteratif hingga konvergensi nilai $|\text{FoS}^{(k+1)} - \text{FoS}^{(k)}| < 0.001$.

### Ambang Batas Regulasi ESDM
- **FoS $\ge 1.30$**: Lereng berstatus STABIL untuk lereng keseluruhan tambang.
- **FoS $1.10 - 1.29$**: Waspada deformasi, wajib pemantauan radar InSAR kontinu.
- **FoS $< 1.10$ atau laju deformasi $> 5.0\text{ mm/jam}$**: Kritis, Kepala Teknik Tambang (KTT) wajib memerintahkan evakuasi darurat sektor tambang (`CRITICAL_EVACUATION`).

---

## 6. Draft Survey Kapal Tongkang & Rekonsiliasi Hidrostatik (M9)

Pengapalan mineral curah (seperti bijih nikel, bauksit, atau batubara) menggunakan kapal tongkang (*flat deck barge*) yang ditarik kapal tunda (*tugboat*). Penentuan tonase muatan bersih mengandalkan pembacaan draf survey 6 titik:

1. **Haluan (Forward)**: $DFP$ (Port / kiri) dan $DFS$ (Starboard / kanan).
2. **Tengah (Midship)**: $DMP$ (Port) dan $DMS$ (Starboard).
3. **Buritan (Aft)**: $DAP$ (Port) dan $DAS$ (Starboard).

### Perhitungan Quarter Mean Draft (Mean of Means)
Untuk menghilangkan pengaruh pembengkokan lambung (*deflection*) berupa *hogging* (tengah melengkung ke atas) atau *sagging* (tengah melengkung ke bawah), draf rata-rata dihitung dengan bobot kuartil:

$$D_{\text{quarter}} = \frac{D_{\text{mean\_fwd}} + 6 \cdot D_{\text{mean\_mid}} + D_{\text{mean\_aft}}}{8}$$

### Koreksi Trim & Densitas Air
Perpindahan (*displacement*) kapal dibaca dari tabel hidrostatik kapal melalui interpolasi linear, kemudian dikoreksi terhadap trim kapal:

$$C_{\text{trim}} = \frac{\text{Trim} \cdot \text{LCF} \cdot \text{TPC} \cdot 100}{\text{LBP}}$$

Dan dikoreksi terhadap massa jenis perairan nyata yang diukur hidrometer:

$$\Delta_{\text{corrected}} = \Delta_{\text{table}} \cdot \frac{\rho_{\text{actual}}}{1.025}$$

Tonase muatan bersih dihitung dari selisih perpindahan akhir (*loaded displacement*) dikurangi perpindahan awal (*light displacement*) dan deduksi air balas (*ballast water*) serta bahan bakar. Toleransi selisih antara hasil draf survey dengan akumulasi timbangan jembatan timbang pelabuhan dibatasi maksimal $\pm 1.5\%$.


---

## 7. Distribusi Tegangan Batuan Bawah Tanah & Persamaan Kirsch (1898)

Pembuatan terowongan atau shaft sirkular di dalam massa batuan mengubah kondisi tegangan *in-situ* alami ($\sigma_v, \sigma_h$). Distribusi tegangan elastis di sekitar lubang bukaan silindris dengan radius $a$ dianalisis menggunakan solusi Kirsch:

### 7.1 Tegangan Radial ($\sigma_r$), Tangensial ($\sigma_\theta$), dan Geser ($\tau_{r\theta}$)

$$\sigma_r = \frac{\sigma_v}{2} \left[ (1 + k) \left(1 - \frac{a^2}{r^2}\right) - (1 - k) \left(1 - 4\frac{a^2}{r^2} + 3\frac{a^4}{r^4}\right) \cos 2\theta \right]$$

$$\sigma_\theta = \frac{\sigma_v}{2} \left[ (1 + k) \left(1 + \frac{a^2}{r^2}\right) + (1 - k) \left(1 + 3\frac{a^4}{r^4}\right) \cos 2\theta \right]$$

$$\tau_{r\theta} = \frac{\sigma_v}{2} \left[ (1 - k) \left(1 + 2\frac{a^2}{r^2} - 3\frac{a^4}{r^4}\right) \sin 2\theta \right]$$

Di mana:
- $\sigma_v$: Tegangan vertikal *in-situ* akibat beban *overburden* batuan ($\sigma_v = \rho \cdot g \cdot z$).
- $k$: Rasio tegangan horizontal terhadap vertikal ($k = \sigma_h / \sigma_v$).
- $r$: Jarak radial dari pusat sumbu terowongan ke titik pengamatan ($r \ge a$).
- $\theta$: Sudut kutub yang diukur dari bidang horizontal.

Pada dinding lubang terowongan ($r = a$), tegangan radial menjadi nol ($\sigma_r = 0$), dan tegangan tangensial mencapai konsentrasi maksimum:

$$\sigma_\theta = \sigma_v \left[ (1 + k) + 2(1 - k) \cos 2\theta \right]$$

- Pada mahkota terowongan (*roof*, $\theta = 90^\circ$): $\sigma_\theta = \sigma_v (3k - 1)$.
- Pada dinding samping (*sidewall*, $\theta = 0^\circ$): $\sigma_\theta = \sigma_v (3 - k)$.

Bila tegangan tangensial melampaui kekuatan batuan utuh, terjadi fenomena keruntuhan geser batuan (*rockburst* atau *spalling*).

---

## 8. Kriteria Keruntuhan Massa Batuan Non-Linier Hoek-Brown (2002)

Untuk memprediksi keruntuhan massa batuan bersendi (*jointed rock mass*) pada stope dan lereng terjal, sistem menggunakan kriteria empiris Hoek-Brown:

$$\sigma_1' = \sigma_3' + \sigma_{ci} \left( m_b \frac{\sigma_3'}{\sigma_{ci}} + s \right)^a$$

Di mana:
- $\sigma_1', \sigma_3'$: Tegangan efektif utama maksimum dan minimum saat runtuh.
- $\sigma_{ci}$: Kuat tekan uniaksial batuan utuh (*intact rock UCS*).
- $m_b, s, a$: Konstanta massa batuan yang diturunkan dari Geological Strength Index (GSI):

$$m_b = m_i \exp\left( \frac{\text{GSI} - 100}{28 - 14D} \right)$$

$$s = \exp\left( \frac{\text{GSI} - 100}{9 - 3D} \right)$$

$$a = \frac{1}{2} + \frac{1}{6} \left( \exp\left( -\frac{\text{GSI}}{15} \right) - \exp\left( -\frac{20}{3} \right) \right)$$

Parameter $D$ mencerminkan faktor gangguan akibat peledakan (*disturbance factor*). Pada penambangan bawah tanah dengan peledakan terkendali halus (*smooth blasting*), $D = 0.0$. Pada peledakan produksi kuari terbuka yang kurang terkontrol, $D = 0.7 - 1.0$.

---

## 9. Pemetaan Geospasial Radar InSAR & Analisis Regangan Tersier

Radar Interferometric Synthetic Aperture Radar (InSAR) permukaan darat (GB-InSAR) mengukur pergeseran lereng dengan membandingkan fase gelombang mikro yang dipantulkan:

$$\Delta \phi = \frac{4\pi}{\lambda} \cdot \Delta r + \phi_{\text{atmosfer}} + \phi_{\text{kebisingan}}$$

- $\lambda$: Panjang gelombang radar (misal pita Ku $\lambda \approx 1.7\text{ cm}$ atau pita C $\lambda \approx 5.6\text{ cm}$).
- $\Delta r$: Perpindahan sepanjang garis pandang (*Line-of-Sight* - LoS).

### Tiga Fase Deformasi Lereng (Saito Model)
1. **Fase Primer (Primary Creep)**: Laju deformasi melambat seiring waktu akibat penataan ulang partikel batuan pasca-penggalian jenjang.
2. **Fase Sekunder (Secondary Creep)**: Laju deformasi stabil dan konstan ($\dot{\epsilon} = \text{konstan}$), biasanya berlangsung berminggu-minggu.
3. **Fase Tersier (Tertiary Creep)**: Terjadi akselerasi laju regangan eksponensial menuju keruntuhan (*time-to-failure* $t_f$):

$$\frac{1}{\dot{\epsilon}(t)} \to 0 \quad \text{saat} \quad t \to t_f$$

Dengan memplot kebalikan laju kecepatan pergerakan ($1/v$) terhadap waktu, sistem memprediksi saat persis terjadinya longsoran (*inverse velocity method*) sehingga peringatan evakuasi dapat dikeluarkan berjam-jam sebelum bencana terjadi.
