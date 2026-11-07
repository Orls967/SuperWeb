# Algoritma V8: SponsorExposureValuator

## 1. Intuisi Bisnis & Masalah
Dalam kemitraan merek (*corporate sponsorship*) acara festival musik dan beach club, merek sponsor membayar nilai kontrak yang signifikan dengan harapan memperoleh eksposur visual maksimal kepada pengunjung target.

Masalah utama dalam sistem sponsorship konvensional:
1. **Laporan Eksposur Subjektif:** Penyelenggara menyajikan estimasi pengunjung manual yang sering kali dibesar-besarkan tanpa bukti telemetri gerbang.
2. **Ketiadaan Pembobotan Ruang & Waktu:** 1.000 penonton yang melintas cepat di lorong keluar dihitung setara dengan 1.000 penonton yang duduk selama 3 jam di depan layar videotron panggung utama.
3. **Ketidakjelasan Mekanisme Pencairan Termin:** Sponsor menolak membayar pelunasan jika target tidak tercapai 100%, sementara penyelenggara menuntut pembayaran penuh karena acara telah selesai digelar.

Algoritma V8 **`SponsorExposureValuator`** menyelesaikan masalah ini dengan mengombinasikan data telemetri turnstile masuk, dwell time zona terbobot, kalkulasi ekuivalen CPM moneter, serta aturan pencairan termin berjenjang (*100% full, pro-rata, atau zero under-delivery default*).

---

## 2. Formulasi Matematis
Diberikan parameter masukan:
- Himpunan pengiriman zona $Z = \{z_1, \dots, z_m\}$, di mana setiap zona memiliki:
  - $\text{Footfall}_z \in \mathbb{N}$: Jumlah pengunjung terverifikasi masuk zona tersebut.
  - $\text{Dwell Weight Bps}_z \in [0, 50.000]$: Bobot lama tinggal dalam basis poin ($10.000\text{ bps} = 1.0x$).
  - $\text{Screen Share Bps}_z \in [0, 10.000]$: Porsi slot tayang videotron sponsor ($10.000\text{ bps} = 100\%$).
- $T_{\text{impressions}} \in \mathbb{N}^+$: Target total impresi kontrak.
- $\text{CPM Rate IDR} \in \mathbb{N}$: Tarif ekuivalen per 1.000 impresi dalam Rupiah.
- $B_{\text{milestone}} \in \mathbb{N}$: Anggaran termin pembayaran milestone yang dievaluasi.

### Tahap 1: Komputasi Impresi Terbobot per Zona
Untuk setiap zona $z \in Z$:
$$\text{Step 1}_z = \left\lfloor \frac{\text{Footfall}_z \times \text{Dwell Weight Bps}_z}{10.000} \right\rfloor$$
$$\text{Delivered}_z = \left\lfloor \frac{\text{Step 1}_z \times \text{Screen Share Bps}_z}{10.000} \right\rfloor$$
$$\text{Delivered Impressions} = \sum_{z \in Z} \text{Delivered}_z$$

### Tahap 2: Nilai Eksposur Ekuivalen Moneter (CPM Value)
$$\text{Exposure Value IDR} = \left\lfloor \frac{\text{Delivered Impressions}}{1.000} \right\rfloor \times \text{CPM Rate IDR}$$

### Tahap 3: Rasio Capaian Kontrak & Aturan Payout
$$\text{Achievement Bps} = \left\lfloor \frac{\text{Delivered Impressions} \times 10.000}{T_{\text{impressions}}} \right\rfloor$$

$$\text{Payout Ratio Bps} = \begin{cases}
10.000 & \text{jika } \text{Achievement Bps} \ge 10.000 \quad (100\% \text{ Payout}) \\
\text{Achievement Bps} & \text{jika } 7.000 \le \text{Achievement Bps} < 10.000 \quad (\text{Pro-Rata Settlement}) \\
0 & \text{jika } \text{Achievement Bps} < 7.000 \quad (\text{Under-Delivery Penalty})
\end{cases}$$

$$\text{Milestone Payout IDR} = \left\lfloor \frac{B_{\text{milestone}} \times \text{Payout Ratio Bps}}{10.000} \right\rfloor$$

---

## 3. Pembuktian Langkah Demi Langkah Vektor Acuan
**Parameter Uji:**
- Target Impresi $T_{\text{impressions}} = 50.000$
- Tarif CPM $= 60.000\text{ IDR}$
- Anggaran Termin $B_{\text{milestone}} = 100.000.000\text{ IDR}$
- Pengiriman Zona (Screen Share 100% atau 10.000 bps):
  1. Main Stage LED: Footfall $40.000$, Dwell $10.000\text{ bps}$ ($1.0x$) $\implies 40.000$ impresi.
  2. Beachfront Deck: Footfall $15.000$, Dwell $6.000\text{ bps}$ ($0.6x$) $\implies 9.000$ impresi.
  3. VIP Cabana: Footfall $2.000$, Dwell $25.000\text{ bps}$ ($2.5x$) $\implies 5.000$ impresi.

**Evaluasi:**
1. $\text{Delivered Impressions} = 40.000 + 9.000 + 5.000 = \mathbf{54.000\text{ Impresi}}$.
2. $\text{Exposure Value IDR} = \lfloor 54.000 / 1.000 \rfloor \times 60.000 = \mathbf{3.240.000\text{ IDR}}$.
3. $\text{Achievement Bps} = \lfloor (54.000 \times 10.000) / 50.000 \rfloor = \mathbf{10.800\text{ bps}}$ ($108\%$).
4. Karena $10.800 \ge 10.000 \implies \text{Payout Ratio} = \mathbf{10.000\text{ bps}}$ ($100\%$).
5. $\text{Milestone Payout IDR} = \lfloor (100.000.000 \times 10.000) / 10.000 \rfloor = \mathbf{100.000.000\text{ IDR}}$.

Sertifikat HMAC `SponsorExposureCertificate` terverifikasi sah oleh `SponsorExposureVerifier`.
