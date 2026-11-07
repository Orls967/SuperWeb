# Algoritma V3: ZoneCapacityGovernor

## 1. Intuisi Bisnis & Masalah
Keselamatan kerumunan (*crowd safety*) pada zona-zona berdensitas tinggi (seperti *pool deck*, *front-of-stage*, *dance floor*, dan selasar bar) sangat rentan terhadap gelombang desakan massa (*crowd surge*). Membatasi jumlah pengunjung total di bawah kapasitas izin legal belum cukup menjamin keselamatan; laju aliran kedatangan (*inflow rate*) per menit juga harus diatur agar tidak menimbulkan titik kemacetan (*bottleneck*) di gerbang akses. Algoritma `ZoneCapacityGovernor` menerapkan batas kapasitas efektif dengan *safety margin* $5\%$ di bawah kapasitas izin legal, serta membatasi admisi menggunakan mekanisme *token bucket*.

## 2. Rumus Matematis & Spesifikasi
Diberikan himpunan parameter masukan:
- $C_{\text{legal}} \in \mathbb{N}$: Kapasitas daya tampung maksimum sesuai surat izin keramaian kepolisian (orang).
- $M_{\text{margin}} \in [0, 10.000]$: Batas margin keselamatan dalam basis poin (fixture: $500\text{ bps} = 5\%$).
- $R_{\text{rate}} \in \mathbb{N}$: Kapasitas laju kedatangan maksimum per menit (token/menit).
- $O_{\text{current}} \in \mathbb{N}$: Okupansi riil saat ini yang dihitung dari agregasi pemindaian fisik gerbang:
  $$O_{\text{current}} = \sum \text{MASUK} - \sum \text{KELUAR}$$
- $Q_{\text{waiting}} \in \mathbb{N}$: Jumlah antrean tamu yang ingin masuk pada jendela waktu menit tersebut.
- $T_{\text{tokens}} \in \mathbb{N}$: Jumlah token admisi yang tersedia dalam bucket saat ini.

### Tahapan Perhitungan Numerik:
1. **Kapasitas Efektif ($C_{\text{effective}}$)**:
   $$C_{\text{effective}} = \left\lfloor \frac{C_{\text{legal}} \times (10.000 - M_{\text{margin}})}{10.000} \right\rfloor$$
2. **Kapasitas Tersedia ($S_{\text{available}}$)**:
   $$S_{\text{available}} = \max(0, C_{\text{effective}} - O_{\text{current}})$$
3. **Pengisian Ulang Token Bucket ($T_{\text{available}}$)**:
   $$T_{\text{available}} = \min(R_{\text{rate}}, T_{\text{tokens}} + R_{\text{rate}})$$
4. **Alokasi Jumlah Tamu yang Diizinkan Masuk ($A_{\text{admitted}}$)**:
   $$A_{\text{admitted}} = \min(Q_{\text{waiting}}, S_{\text{available}}, T_{\text{available}})$$
5. **Jumlah Tamu yang Ditahan di Antrean ($R_{\text{rejected}}$)**:
   $$R_{\text{rejected}} = Q_{\text{waiting}} - A_{\text{admitted}}$$
6. **Pembaruan Okupansi Baru**:
   $$O_{\text{new}} = O_{\text{current}} + A_{\text{admitted}}$$

## 3. Contoh Vektor Pengujian Langkah Demi Langkah
Parameter sistem: $C_{\text{legal}} = 500\text{ orang}$, $M_{\text{margin}} = 500\text{ bps}$ ($5\%$), $R_{\text{rate}} = 30\text{ orang/menit}$.
- Kapasitas efektif:
  $$C_{\text{effective}} = \lfloor 500 \times (10.000 - 500) / 10.000 \rfloor = \lfloor 500 \times 0,95 \rfloor = \mathbf{475\text{ orang}}$$

### Menit ke-1:
- Okupansi awal: $O = 460\text{ orang}$.
- Kapasitas sisa ke batas efektif: $S = 475 - 460 = 15\text{ orang}$.
- Token bucket terisi penuh: $T = 30\text{ token}$.
- Tamu yang mengantre: $Q = 20\text{ orang}$.
- Evaluasi Admisi:
  $$A = \min(20, 15, 30) = \mathbf{15\text{ orang}}$$
- Penolakan (ditahan):
  $$R = 20 - 15 = \mathbf{5\text{ orang}}$$
- Okupansi akhir Menit-1: $460 + 15 = \mathbf{475\text{ orang}}$ (mencapai batas efektif).

### Menit ke-2:
- Dinamika keluar: $10\text{ orang}$ meninggalkan zona $\implies$ Okupansi menjadi $475 - 10 = 465\text{ orang}$.
- Kapasitas sisa ke batas efektif: $S = 475 - 465 = 10\text{ orang}$.
- Token bucket diisi ulang: $T = 30\text{ token}$.
- Tamu baru yang datang mengantre: $Q = 40\text{ orang}$.
- Evaluasi Admisi:
  $$A = \min(40, 10, 30) = \mathbf{10\text{ orang}}$$
- Penolakan (ditahan):
  $$R = 40 - 10 = \mathbf{30\text{ orang}}$$
- Okupansi akhir Menit-2: $465 + 10 = \mathbf{475\text{ orang}}$.

## 4. Sertifikat Matematis & Pembuktian
Setiap pemrosesan batch menghasilkan objek bersertifikat:
```php
final readonly class ZoneCapacityCertificate
{
    public function __construct(
        public int $legalCapacity,
        public int $safetyMarginBps,
        public int $effectiveCapacity,
        public int $occupancyBefore,
        public int $waitingCount,
        public int $admittedCount,
        public int $rejectedCount,
        public int $occupancyAfter,
        public string $hmacSignature,
    ) {}
}
```
Invarian yang diverifikasi oleh `ZoneCapacityCertificateVerifier`:
1. $O_{\text{new}} \le C_{\text{effective}}$
2. $A_{\text{admitted}} + R_{\text{rejected}} = Q_{\text{waiting}}$
3. $A_{\text{admitted}} \le R_{\text{rate}}$

## 5. Kompleksitas & Karakteristik Kinerja
- **Kompleksitas Waktu**: $\mathcal{O}(1)$ komputasi konstan.
- **Kompleksitas Ruang**: $\mathcal{O}(1)$ alokasi memori.
- **Lokasi Kode**: `Modules\Venue\Domain\Algorithms\ZoneCapacityGovernor`.
