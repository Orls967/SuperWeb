# Manajemen Risiko Finansial Komoditas Logam & Hedging LME (Fase 121)

## 1. Pendahuluan & Profil Risiko Komoditas Hilir

Sebagai produsen produk hilir nikel laterit (NPI / Ferronickel), kelangsungan arus kas (*cash flow stability*) perusahaan tambang sangat rentan terhadap siklus harga global komoditas logam bursa *London Metal Exchange* (LME). Harga kontrak tunai nikel historis menunjukkan deviasi standar tahunan ($\sigma$) berkisar antara $20\% - 45\%$, yang diperparah oleh dinamika geopolitik, kebijakan kuota ekspor negara produsen utama, dan pergeseran permintaan industri kendaraan listrik (*EV batteries*).

```
+---------------------------------------------------------------------------------------------------+
|                        COMMODITY RISK MANAGEMENT FRAMEWORK (FASE 121)                             |
+---------------------------------------------------------------------------------------------------+
|   [PHYSICAL PRODUCTION]       [PRICE RISK EXPOSURE]       [DERIVATIVE HEDGE]       [BANK SCF LIEN]|
|    - 1,500 MT / Month          - Floating LME Price        - Short Forward LME      - Digital SRG |
|    - Breakeven: $13,500/t      - Revenue Volatility        - Lock $16,500/t Floor   - LTV 80%     |
|    - Production Cost: Fixed    - Downside Risk             - MTM Daily Valuation    - Working Cap |
+---------------------------------------------------------------------------------------------------+
```

---

## 2. Strategi Lindung Nilai (Hedging Strategy) Portofolio Smelter

Untuk memitigasi risiko penurunan harga (*downside price risk*) sekaligus mempertahankan potensi keuntungan saat pasar mengalami kenaikan (*upside participation*), trading desk menerapkan tiga instrumen lindung nilai terstruktur:

### A. Short Forward Hedging (Vanilla Forward)
- **Mekanisme**: Perusahaan menjual kontrak berjangka 3-bulan pada harga kesepakatan tetap ($P_{\text{lock}}$) untuk volume yang setara dengan $50\% - 70\%$ dari target produksi bulanan.
- **Profil Pembayaran**: Menjamin pendapatan pasti sebesar $P_{\text{lock}}$ per ton tanpa mempedulikan seberapa dalam harga spot LME turun pada saat tanggal serah fisik (*physical delivery date*).

### B. Collar Option Strategy (Zero-Cost Collar)
- **Mekanisme**: Membeli instrumen opsi jual (*put option*) pada harga batas bawah (*floor strike price* $P_{\text{floor}}$) dengan membiayai preminya melalui penjualan instrumen opsi beli (*call option*) pada harga batas atas (*cap strike price* $P_{\text{cap}}$).
- **Profil Pembayaran**: Melindungi margin operasi di bawah $P_{\text{floor}}$ dengan mengorbankan keuntungan jika harga pasar melonjak melampaui $P_{\text{cap}}$.

### C. Cash-and-Carry Arbitrage & Hedging Berbasis Contango
- Pada kondisi pasar *Contango* di mana kurva forward miring ke atas ($P_{3\text{M}} > P_{\text{Cash}}$), perusahaan memanfaatkan penyimpanan stok di gudang terakreditasi ISWARE Bappebti.
- Menjual kontrak forward 3-bulan dengan premi contango sambil menjaminkan resi gudang (SRG) untuk memperoleh pembiayaan modal kerja bunga rendah SCF perbankan.

---

## 3. Sensitivitas Risiko Derivatif (The Greeks)

Trading desk memantau sensitivitas portofolio kontrak terhadap perubahan variabel pasar menggunakan turunan parsial (*Greeks*):

### A. Delta ($\Delta$ - Price Sensitivity)
Rasio perubahan nilai wajar portofolio derivatif terhadap pergerakan satu unit harga spot logam acuan:
$$\Delta = \frac{\partial V}{\partial S}$$
Untuk posisi lindung nilai sempurna (*perfect short hedge*), delta portofolio total dijaga pada kondisi netral:
$$\Delta_{\text{portfolio}} = \Delta_{\text{physical}} + \Delta_{\text{derivative}} \approx +1.0 + (-1.0) = 0.0$$

### B. Gamma ($\Gamma$ - Curvature Sensitivity)
Laju perubahan Delta terhadap pergerakan harga komoditas:
$$\Gamma = \frac{\partial^2 V}{\partial S^2}$$
Gamma mengukur seberapa sering posisi lindung nilai perlu disesuaikan kembali (*rehedging frequency*). Pada kondisi volatilitas pasar tinggi, nilai Gamma yang besar memerlukan penyesuaian dinamis harian.

### C. Vega ($\nu$ - Volatility Sensitivity)
Sensitivitas nilai portofolio opsi terhadap perubahan volatilitas pasar tersirat (*implied volatility* $\sigma$):
$$\nu = \frac{\partial V}{\partial \sigma} = S \sqrt{T} \mathcal{N}'(d_1)$$
Di mana $\mathcal{N}'(d_1)$ adalah fungsi kerapatan probabilitas distribusi normal standar.

### D. Theta ($\Theta$ - Time Decay)
Tingkat peluruhan nilai opsi seiring berjalannya waktu menuju tanggal kedaluwarsa (*time decay*):
$$\Theta = \frac{\partial V}{\partial t}$$

---

## 4. Pengukuran Risiko: Value at Risk (VaR) & Stress Testing

Sistem Fase 121 mengevaluasi eksposur portofolio komoditas menggunakan metrik *Value at Risk* (VaR) parametrik dan simulasi historis dengan interval kepercayaan $99\%$ untuk horizon waktu 1 hari ($1\text{-day VaR}$):

$$\text{VaR}_{99\%} = \text{Nilai Nosional} \times Z_{0.99} \times \sigma_{\text{daily}} \times \sqrt{t}$$

Di mana untuk distribusi normal standar, $Z_{0.99} = 2.326$.
Jika batas toleransi risiko VaR melampaui batas modal kerja yang diizinkan oleh Komite Manajemen Risiko Direksi, sistem memberikan rekomendasi otomatis untuk memperbesar porsi lindung nilai atau menutup posisi spekulatif terbuka.

---

## 5. Mitigasi Risiko Likuiditas Margin Call

Risiko terbesar pada posisi *short hedge* adalah terjadinya lonjakan harga (*bullish spike*) yang memicu panggilan margin variasi (*variation margin call*). Sistem mengantisipasi risiko ini melalui:
1. **Penyangga Likuiditas Otomatis**: Mempertahankan fasilitas standby kredit bank SCF berbasis resi gudang yang siap ditarik dalam hitungan menit untuk memenuhi setoran margin broker kliring.
2. **Peringatan Dini Multi-Level**: Peringatan otomatis diterbitkan saat ekuitas rekening margin mencapai $85\%$ dari batas margin pemeliharaan, memberikan waktu 48 jam bagi tim treasury untuk mengonsolidasikan likuiditas sebelum terjadi penalti default bursa.
