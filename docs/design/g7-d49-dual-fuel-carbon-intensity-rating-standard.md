# G7-D49: Dual-Fuel Carbon Intensity Rating Standard (IMO MEPC.354(78) & MARPOL Annex VI)

## 1. Konteks Regulasi Emisi Maritim Internasional

Mulai 1 Januari 2023, Organisasi Maritim Internasional (*International Maritime Organization - IMO*) mewajibkan seluruh kapal niaga dengan tonase kotor 5.000 GT ke atas untuk menghitung dan melaporkan Indikator Intensitas Karbon Operasional (*Carbon Intensity Indicator - CII*). Regulasi ini diadopsi melalui Resolusi MEPC.354(78) dan Amandemen MARPOL Annex VI Regulasi 28.

Keputusan arsitektur **G7-D49** menetapkan implementasi perhitungan deterministik CII harian dan tahunan, penentuan batas toleransi peringkat A sampai E, integrasi bahan bakar ganda (*dual-fuel / multi-fuel*), serta penguncian izin dispatch armada berbasis kepatuhan dekarbonisasi maritim global.

---

## 2. Parameter Bahan Bakar & Faktor Emisi Karbon ($C_F$)

Sesuai IMO Guidelines 2022 on the Carbon Intensity Indicator, setiap jenis bahan bakar kapal memiliki faktor konversi emisi massal ($C_F$ dalam metrik ton $\text{CO}_2$ per metrik ton bahan bakar):

| Tipe Bahan Bakar Maritim | Standar Spesifikasi | Kadar Sulfur Maksimum | Nilai $C_F$ IMO ($\text{t-CO}_2 / \text{t-fuel}$) | Karakteristik Operasional |
|---|---|---|---|---|
| **VLSFO** (*Very Low Sulphur Fuel Oil*) | ISO 8217:2017 RMG 380 | $\le 0.50\% \text{ m/m}$ | **3.151** | Bahan bakar utama pelayaran samudera modern paska IMO 2020. |
| **LSMGO** (*Low Sulphur Marine Gas Oil*)| ISO 8217:2017 DMA/DMZ | $\le 0.10\% \text{ m/m}$ | **3.206** | Digunakan di zona emisi khusus (*ECA - Emission Control Areas*) dan saat manuver sandar. |
| **HFO** (*Heavy Fuel Oil*) | ISO 8217:2017 RMK 700 | $> 0.50\% \text{ m/m}$ | **3.114** | Hanya diizinkan bagi kapal yang dilengkapi *Exhaust Gas Cleaning System* (Scrubber terbuka/tertutup). |
| **LNG** (*Liquefied Natural Gas*) | IGF Code (Methane) | $\approx 0\%$ | **2.750** | Emisi karbon lebih rendah $20-25\%$, resiko pelepasan gas metana (*methane slip*). |
| **Bio-Fuel B30 / B35 (FAME)** | EN 14214 / SNI 7182 | $\le 10 \text{ ppm}$ | **2.850** (*net-well-to-wake*) | Campuran kelapa sawit domestik Indonesia untuk armada kabotase. |

---

## 3. Rumusan Matematis Penilaian Attained vs Required CII

### 3.1 Perhitungan Attained CII Operasional
$$\text{Attained CII} = \frac{\sum_{j} (FC_j \times C_{F,j})}{\text{Kapasitas (DWT)} \times \text{Jarak Tempuh (Nautical Miles)}} \times 10^6 \quad [\text{gram } \text{CO}_2 / \text{dwt}\cdot\text{nm}]$$
Di mana:
- $FC_j$: Total konsumsi bahan bakar tipe $j$ dalam metrik ton selama periode evaluasi pelayaran.
- $C_{F,j}$: Faktor emisi karbon IMO untuk tipe bahan bakar $j$.
- $\text{Kapasitas}$: Bobot mati kapal (*Deadweight Tonnage - DWT*) untuk bulk carrier, tanker, container ship, dan general cargo.
- $\text{Jarak Tempuh}$: Jarak perpindahan kapal di atas permukaan air (*distance travelled over ground*) yang diverifikasi lewat noon report GPS.

### 3.2 Perhitungan Reference Line & Required CII Tahunan
$$\text{CII}_{\text{ref}} = a \times \text{Capacity}^{-c}$$
Untuk kapal Bulk Carrier: $a = 4745$, $c = 0.401$.
$$\text{Required CII}_{2026} = \text{CII}_{\text{ref}} \times \left(1 - \frac{Z}{100}\right)$$
Di mana faktor reduksi tahunan $Z$ untuk tahun 2026 adalah $11\%$ ($Z = 11$).

### 3.3 Penentuan Batas Peringkat (Rating Boundaries d1..d4)
Rasio efisiensi dihitung dengan formula perbandingan:
$$\text{Rasio CII} = \frac{\text{Attained CII}}{\text{Required CII}}$$
Batas peringkat emisi dievaluasi terhadap eksponensial faktor deviasi vektor $d_1, d_2, d_3, d_4$:

```
Rasio CII <= exp(-d1)  -->  [ GRADE A : Major Superior ]
Rasio CII <= exp(-d2)  -->  [ GRADE B : Minor Superior ]
Rasio CII <= exp(d3)   -->  [ GRADE C : Moderate / Standard ]
Rasio CII <= exp(d4)   -->  [ GRADE D : Minor Inferior ]
Rasio CII >  exp(d4)   -->  [ GRADE E : Inferior ]
```

---

## 4. Konsekuensi Hukum & Operasional Peringkat Emisi

```
+---------------------------------------------------------------+
|                       HASIL AUDIT CII                         |
+-------------------------------+-------------------------------+
                                |
        +-----------------------+-----------------------+
        |                                               |
        v                                               v
+-------------------------------+       +-------------------------------+
|   Peringkat A, B, atau C      |       |     Peringkat D atau E        |
+---------------+---------------+       +---------------+---------------+
                |                                       |
                v                                       v
+-------------------------------+       +-------------------------------+
| Operasi Normal Berkelanjutan  |       | Kepatuhan Regulasi Terancam   |
| - Diterbitkan Sertifikat SOCP |       | - D 3 Tahun Beruntun atau E   |
| - Potongan Port Dues Hijau 5% |       | - Wajib Rencana Perbaikan     |
+-------------------------------+       +---------------+---------------+
                                                        |
                                                        v
                                        +-------------------------------+
                                        |  SEEMP Part III Action Plan   |
                                        |  1. Just-in-Time Port Arrival |
                                        |  2. Eco-Speed Slow Steaming   |
                                        |  3. Retrofit Hull Air Enclose |
                                        +-------------------------------+
```

1. **Grade A & B (Superior)**:
   - Berhak menerima insentif diskon tarif jasa labuh/tambat (*Green Port Dues Rebate*) sebesar $5\%$ di pelabuhan modern terdaftar.
   - Peringkat kapal otomatis tercatat di bursa sewa kapal Baltic Exchange dengan premi tarif sewa harian (*green freight premium*).
2. **Grade C (Standard)**:
   - Kepatuhan minimum tercapai, kapal beroperasi secara normal tanpa penalti.
3. **Grade D & E (Inferior)**:
   - Sistem menandai kapal dengan peringatan operasional kuning/merah.
   - Jika kapal meraih Grade D selama 3 tahun berturut-turut atau Grade E dalam 1 tahun pelaporan, sistem secara otomatis memblokir pembuatan fixture sewa baru hingga dokumen rencana aksi koreksi (*Corrective Action Plan*) diverifikasi oleh biro klasifikasi resmi dan disetujui administrasi bendera negara (*Flag State*).

---

## 5. Integrasi Sistem & Pencegahan Pelanggaran Emisi

1. **Telemetri Noon Report Real-Time**: Setiap entri `FleetNoonReport` secara otomatis memperbarui nilai estimasi akumulatif CII tahun berjalan (*year-to-date attained CII*).
2. **Optimasi Rute Eco-Speed**: Layanan `OceanFleetRouteOptimizationService` menghitung kurva kecepatan jelajah paling optimal (*economic transit speed*) untuk menjaga peringkat emisi kapal tetap berada di zona Grade C atau lebih baik.
3. **Audit Kepatuhan Pelabuhan**: Endpoint `/port/fleet` menampilkan kartu skor karbon visual setiap kapal untuk transparansi kepada penyewa dan auditor maritim.
