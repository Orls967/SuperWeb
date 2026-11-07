# Algoritma V1: TierCountdownPricer

## 1. Intuisi Bisnis & Masalah
Harga tiket acara hiburan berfluktuasi secara dinamis tergantung pada periode penjualan (countdown tier seperti Early Bird, Presale, General Admission, Door), tekanan permintaan pasar (*demand factor* berdasarkan rasio tiket terjual terhadap kuota), serta faktor ketidakpastian cuaca luar ruangan (*coastal weather risk*). Tiket perlu dihargai secara deterministik, memiliki batas perlindungan dasar artis (*floor price*) dan batas kenyamanan penonton (*ceiling price*), serta dibulatkan ke bawah ke kelipatan Rp 1.000 untuk kemudahan transaksi kasir maupun QRIS.

## 2. Rumus Matematis & Spesifikasi
Diberikan himpunan parameter masukan:
- $P_{\text{base}} \in \mathbb{N}$: Harga dasar tier aktif saat ini dalam integer Rupiah (IDR).
- $R_{\text{demand}} \in [0, 10.000]$: Rasio tiket terjual terhadap total kuota kapasitas tier dalam basis poin (bps), di mana $10.000\text{ bps} = 100\%$.
- $P_{\text{rain}} \in [0, 10.000]$: Peluang presipitasi curah hujan pesisir dalam basis poin (bps) yang dibaca dari sensor/BMKG.
- $P_{\text{floor}} \in \mathbb{N}$: Batas harga minimum terendah (floor price jaminan artis).
- $P_{\text{ceiling}} \in \mathbb{N}$: Batas harga maksimum tertinggi (ceiling price batas kewajaran pasar).

Faktor-faktor penyesuaian dievaluasi sebagai bilangan rasional:
1. **Faktor Permintaan ($F_{\text{demand}}$)**:
   $$F_{\text{demand}} = \begin{cases} 
   0 & \text{jika } R_{\text{demand}} < 5.000\text{ bps} \quad (0\%) \\ 
   +1.000\text{ bps} & \text{jika } 5.000 \le R_{\text{demand}} < 8.000\text{ bps} \quad (+10\%) \\ 
   +2.000\text{ bps} & \text{jika } R_{\text{demand}} \ge 8.000\text{ bps} \quad (+20\%) 
   \end{cases}$$

2. **Faktor Risiko Cuaca Hujan ($F_{\text{weather}}$)**:
   $$F_{\text{weather}} = \begin{cases} 
   -1.000\text{ bps} & \text{jika } P_{\text{rain}} \ge 6.000\text{ bps} \quad (-10\%) \\ 
   0 & \text{jika } P_{\text{rain}} < 6.000\text{ bps} \quad (0\%) 
   \end{cases}$$

Harga mentah dihitung secara beruntun (*sequential multiplier*):
$$P_{\text{demand\_adj}} = \left\lfloor \frac{P_{\text{base}} \times (10.000 + F_{\text{demand}})}{10.000} \right\rfloor$$
$$P_{\text{weather\_adj}} = \left\lfloor \frac{P_{\text{demand\_adj}} \times (10.000 + F_{\text{weather}})}{10.000} \right\rfloor$$

Pembulatan ke bawah ke kelipatan seribu Rupiah (Floor to Rp 1.000):
$$P_{\text{rounded}} = \left\lfloor \frac{P_{\text{weather\_adj}}}{1.000} \right\rfloor \times 1.000$$

Penjepitan Batas Floor & Ceiling (*Clamping*):
$$P_{\text{final}} = \min(P_{\text{ceiling}}, \max(P_{\text{floor}}, P_{\text{rounded}}))$$

## 3. Contoh Perhitungan Langkah Demi Langkah dari Vektor Pengujian
Batas sistem uji coba: $P_{\text{floor}} = 280.000\text{ IDR}$, $P_{\text{ceiling}} = 500.000\text{ IDR}$.

### Kasus 1: General Admission, Lonjakan Permintaan, Cuaca Cerah
- Masukan: Tier GA, $P_{\text{base}} = 350.000$, $R_{\text{demand}} = 8.500\text{ bps}$, $P_{\text{rain}} = 2.000\text{ bps}$.
- Evaluasi Langkah Demi Langkah:
  1. $R_{\text{demand}} = 8.500 \ge 8.000 \implies F_{\text{demand}} = +2.000\text{ bps} = +20\%$.
  2. $P_{\text{rain}} = 2.000 < 6.000 \implies F_{\text{weather}} = 0\text{ bps} = 0\%$.
  3. $P_{\text{demand\_adj}} = \lfloor 350.000 \times 12.000 / 10.000 \rfloor = 420.000$.
  4. $P_{\text{weather\_adj}} = \lfloor 420.000 \times 10.000 / 10.000 \rfloor = 420.000$.
  5. Pembulatan: $\lfloor 420.000 / 1.000 \rfloor \times 1.000 = 420.000$.
  6. Penjepitan: $\min(500.000, \max(280.000, 420.000)) = \mathbf{420.000\text{ IDR}}$.

### Kasus 2: Early Bird, Permintaan Rendah, Hujan Lebat (Floor Hit)
- Masukan: Tier EB, $P_{\text{base}} = 250.000$, $R_{\text{demand}} = 3.000\text{ bps}$, $P_{\text{rain}} = 7.000\text{ bps}$.
- Evaluasi Langkah Demi Langkah:
  1. $R_{\text{demand}} = 3.000 < 5.000 \implies F_{\text{demand}} = 0\text{ bps}$.
  2. $P_{\text{rain}} = 7.000 \ge 6.000 \implies F_{\text{weather}} = -1.000\text{ bps} = -10\%$.
  3. $P_{\text{demand\_adj}} = \lfloor 250.000 \times 10.000 / 10.000 \rfloor = 250.000$.
  4. $P_{\text{weather\_adj}} = \lfloor 250.000 \times 9.000 / 10.000 \rfloor = 225.000$.
  5. Pembulatan: $\lfloor 225.000 / 1.000 \rfloor \times 1.000 = 225.000$.
  6. Penjepitan: $\min(500.000, \max(280.000, 225.000)) = \mathbf{280.000\text{ IDR}}$ (terkena floor jaminan artis).

### Kasus 3: Door Price, Permintaan Sangat Tinggi (Ceiling Hit)
- Masukan: Tier Door, $P_{\text{base}} = 450.000$, $R_{\text{demand}} = 9.000\text{ bps}$, $P_{\text{rain}} = 1.000\text{ bps}$.
- Evaluasi Langkah Demi Langkah:
  1. $R_{\text{demand}} = 9.000 \ge 8.000 \implies F_{\text{demand}} = +20\%$.
  2. $P_{\text{rain}} = 1.000 < 6.000 \implies F_{\text{weather}} = 0\%$.
  3. $P_{\text{demand\_adj}} = \lfloor 450.000 \times 1,20 \rfloor = 540.000$.
  4. $P_{\text{weather\_adj}} = 540.000$.
  5. Penjepitan: $\min(500.000, \max(280.000, 540.000)) = \mathbf{500.000\text{ IDR}}$ (terkena batas ceiling kewajaran pasar).

### Kasus 4: GA, Permintaan Menengah, Hujan Lebat
- Masukan: Tier GA, $P_{\text{base}} = 350.000$, $R_{\text{demand}} = 7.999\text{ bps}$, $P_{\text{rain}} = 6.000\text{ bps}$.
- Evaluasi Langkah Demi Langkah:
  1. $R_{\text{demand}} = 7.999 \in [5.000, 7.999] \implies F_{\text{demand}} = +10\% = 1,10$.
  2. $P_{\text{rain}} = 6.000 \ge 6.000 \implies F_{\text{weather}} = -10\% = 0,90$.
  3. $P_{\text{demand\_adj}} = \lfloor 350.000 \times 1,10 \rfloor = 385.000$.
  4. $P_{\text{weather\_adj}} = \lfloor 385.000 \times 0,90 \rfloor = 346.500$.
  5. Pembulatan ke bawah Rp 1.000: $\lfloor 346.500 / 1.000 \rfloor \times 1.000 = 346.000$.
  6. Penjepitan: $\min(500.000, \max(280.000, 346.000)) = \mathbf{346.000\text{ IDR}}$.

## 4. Struktur Sertifikat Kriptografis
Setiap eksekusi komputasi harga menghasilkan objek bersertifikat:
```php
final readonly class TierCountdownPriceCertificate
{
    public function __construct(
        public int $basePriceIdr,
        public int $demandBps,
        public int $rainProbabilityBps,
        public int $floorPriceIdr,
        public int $ceilingPriceIdr,
        public int $finalPriceIdr,
        public string $hmacSignature,
    ) {}
}
```
Kelas `TierCountdownPriceCertificateVerifier` mengomputasi ulang secara independen seluruh rantai perkalian dan memvalidasi keabsahan tanda tangan HMAC. Apabila terdeteksi manipulasi harga, sistem seketika melempar eksepsi `InvalidCertificateException`.

## 5. Kompleksitas & Karakteristik Kinerja
- **Kompleksitas Waktu**: $\mathcal{O}(1)$ operasi aritmatika integer murni.
- **Kompleksitas Ruang**: $\mathcal{O}(1)$ alokasi memori.
- **Deterministik**: Seluruh operasi menggunakan pembagian integer dengan tie-break deterministik tanpa angka mengambang (float).
- **Lokasi Kode**: `Modules\Venue\Domain\Algorithms\TierCountdownPricer`.
