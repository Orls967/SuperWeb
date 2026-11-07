# Spesifikasi Matematis & Sertifikasi Algoritma: Hardy-Cross Underground Ventilation Network Solver (M7)

## 1. Identifikasi & Metrik Algoritma M7

- **Nama Algoritma**: `HardyCrossVentilationNetworkSolver`
- **Namespace Modul**: `Modules\Shared\Domain\Algorithms\Underground`
- **Sertifikasi / Verifier**: `HardyCrossVentilationNetworkVerifier`
- **Domain Aplikasi**: Dinamika fluida udara tambang bawah tanah, simulasi kestabilan sirkulasi shaft & stope, verifikasi kepatuhan regulasi Kepmen ESDM No. 1827 K/2018 Lampiran IV.
- **Kompleksitas Asimtotik Waktu**: $\mathcal{O}(K \cdot M \cdot B)$, di mana $K$ adalah jumlah iterasi konvergensi, $M$ jumlah loop tertutup independen, dan $B$ jumlah cabang dalam loop.
- **Kompleksitas Ruang Memori**: $\mathcal{O}(B + M)$, menggunakan representasi sparse vector array.

---

## 2. Landasan Fisika & Teori Persamaan

Aliran udara di dalam terowongan tambang bawah tanah diasumsikan sebagai fluida inkompresibel bertekanan rendah ($\Delta P \ll P_{\text{atmosfer}}$). Perilaku jaringan diatur oleh dua hukum fundamental:

### 2.1 Hukum Aliran Udara Atkinson (1854)
Kehilangan tekanan akibat gesekan dinding saluran dan turbulensi aliran ($\Delta h$, dalam Pascal $[\text{Pa}]$):

$$\Delta h = R \cdot |Q| \cdot Q$$

Di mana:
- $Q$: Laju volumetrik aliran udara ($\text{m}^3/\text{s}$).
- $R$: Resistansi aerodinamika spesifik saluran airway ($\text{N}\cdot\text{s}^2/\text{m}^8$).

Resistansi $R$ ditentukan oleh dimensi fisik dan koefisien gesek:

$$R = \frac{k \cdot \text{Per} \cdot L}{A^3} \cdot \frac{\rho}{1.20}$$

- $k$: Faktor friksi Atkinson ($\text{kg}/\text{m}^3$), mencerminkan kekasaran permukaan batuan tembak atau dinding beton.
- $\text{Per}$: Keliling basah airway ($\text{m}$).
- $L$: Panjang lintasan airway ($\text{m}$).
- $A$: Luas penampang lintasan bebas udara ($\text{m}^2$).
- $\rho$: Kerapatan udara tambang aktual ($\text{kg}/\text{m}^3$).

### 2.2 Hukum Kirchhoff II pada Loop Sirkulasi Tertutup
Untuk setiap loop sirkulasi tertutup $m \in \{1, 2, \dots, M\}$:

$$F_m(Q) = \sum_{i \in m} \epsilon_{i,m} \left( R_i |Q_i| Q_i - H_{f,i} - H_{n,i} \right) = 0$$

Di mana:
- $\epsilon_{i,m} = +1$ jika arah aliran cabang $i$ searah orientasi loop $m$, dan $-1$ jika berlawanan arah.
- $H_{f,i}$: Tekanan penggerak kipas (*fan pressure*) yang terpasang pada cabang $i$ ($\text{Pa}$).
- $H_{n,i}$: Tekanan kepala ventilasi alami (*natural ventilation head*) akibat perbedaan densitas kolom udara vertikal shaft hangat vs dingin ($\text{Pa}$).

---

## 3. Formulasi Numerik Deret Taylor Orde-1 (Metode Hardy-Cross)

Metode Hardy-Cross memperkirakan koreksi debit loop $\Delta Q_m$ dengan mengekspansikan fungsi galat $F_m(Q + \Delta Q_m)$ ke deret Taylor di sekitar titik operasi saat ini dan mengabaikan suku berderajat dua ke atas:

$$F_m(Q + \Delta Q_m) \approx F_m(Q) + \Delta Q_m \cdot \frac{\partial F_m}{\partial Q} = 0$$

Turunan parsial terhadap debit loop adalah:

$$\frac{\partial F_m}{\partial Q} = \sum_{i \in m} 2 R_i |Q_i|$$

Sehingga rumus inkremen koreksi aliran debit loop menjadi:

$$\Delta Q_m = -\frac{\sum_{i \in m} \epsilon_{i,m} \left( R_i |Q_i| Q_i - H_{f,i} - H_{n,i} \right)}{2 \sum_{i \in m} R_i |Q_i|}$$

### 3.1 Pembaruan Debit Cabang
Setelah $\Delta Q_m$ dihitung untuk setiap loop $m$, debit seluruh cabang $i$ yang merupakan anggota loop diperbarui:

$$Q_i^{(k+1)} = Q_i^{(k)} + \sum_{m \ni i} \epsilon_{i,m} \cdot \Delta Q_m$$

Proses pembaruan ini diulang hingga memenuhi kriteria konvergensi:

$$\max_{m} |\Delta Q_m| < \delta_{\text{toleransi}} \quad (\delta = 0.001\text{ m}^3/\text{s})$$

---

## 4. Sertifikasi & Vektor Uji Baku

Algoritma diverifikasi menggunakan kelas `HardyCrossVentilationNetworkVerifier` terhadap 5 kondisi deterministik:
1. **Loop Simetris Tunggal**: Dua cabang paralel dengan nilai $R$ identik harus membagi debit secara presisi 50:50.
2. **Kompensasi Kipas Fan**: Penambahan tekanan kipas $H_f$ harus meningkatkan debit aliran searah secara eksponensial sesuai kurva karakteristik.
3. **Deteksi Pembalikan Arah (Reversal Risk)**: Bila resistansi stope meningkat tajam dan tekanan alami berlawanan, solver mendeteksi perubahan tanda arah aliran ($Q < 0$) dan membunyikan alarm keselamatan.
4. **Konservasi Massa Simpul (KCL)**: Galat sisa debit di setiap simpul pertemuan percabangan wajib $\le 10^{-4}\text{ m}^3/\text{s}$.
5. **Daya Kipas Mekanikal**: Daya poros kipas dihitung presisi via $W_{\text{fan}} = \Delta P \times Q$ dalam satuan Watt/kW.
