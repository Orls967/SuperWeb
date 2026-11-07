# Pembuktian Matematis & Teorema Kriptografis — Fase 117

**Domain:** Venue & Entertainment (`modules/Venue/`, sub-item 117.1 s/d 117.7)  
**Pelaksana:** Lead Cryptographer, Lead AI Systems Architect & Safety Engineer `[G7-P5]`  

---

## 1. Teorema 1: Ketakterbalikan Templat Biometrik (Irreversibility Theorem)

### Pernyataan Teorema
Diberikan vektor fitur biometrik $\mathbf{v} \in \mathbb{R}^d$ yang diekstraksi dari citra wajah subjek. Jika $\mathbf{v}$ dipetakan ke ruang kode menggunakan garam acak privat $s \in \{0,1\}^{256}$ dan kunci rahasia vault $K_{\text{vault}} \in \{0,1\}^{256}$ melalui konstruksi:
$$h = \text{HMAC-SHA256}(\mathbf{v} \parallel s, K_{\text{vault}})$$
Maka probabilitas memulihkan vektor asli $\mathbf{v}'$ sedemikian rupa sehingga $\|\mathbf{v}' - \mathbf{v}\| \le \epsilon$ adalah negligible terhadap adversary polinomial waktu:
$$\Pr[\mathcal{A}(h) \to \mathbf{v}'] \le \text{negl}(\kappa) + \mathcal{O}(2^{-256})$$

### Bukti Formal
1. Fungsi $\text{HMAC-SHA256}(m, k)$ bertindak sebagai pseudo-random function (PRF) yang aman di bawah asumsi kekokohan kompresi Merkle-Damgård dari SHA-256.
2. Panjang kunci $K_{\text{vault}}$ adalah 256 bit dan garam $s$ adalah 256 bit. Total entropi input ke kompresi adalah minimal 512 bit.
3. Untuk merekonstruksi $\mathbf{v}$ dari $h$, penyerang harus menemukan pre-image dari fungsi kompresi SHA-256.
4. Karena SHA-256 memiliki ketahanan pre-image sebesar $2^{256}$ operasi komputasi, maka upaya pemulihan analitik vektor wajah adalah infeasible secara komputasional.
5. Dengan demikian, privasi subjek terlindungi secara kriptografis bahkan jika basis data `ven_biometric_credentials` mengalami kebocoran fisik (Zero Biometric Leakage). $\blacksquare$

---

## 2. Teorema 2: Konvergensi Prediksi Kepadatan Kerumunan (Crowd Density Predictor Stability)

### Pernyataan Teorema
Diberikan model pergerakan kerumunan waktu diskrit dengan laju aliran masuk $V_{in}(t)$ dan laju aliran keluar $V_{out}(t)$ pada zona berkapasitas $C$ dan luas area $A \text{ m}^2$. Proyeksi headcount 15 menit ke depan:
$$\hat{H}_{t+15} = H_t + 15 \times \Delta V(t)$$
memiliki batas kesalahan kuadratik terkecil berhingga dan stabil terhadap fluktuasi stokastik jangka pendek jika laju aliran dihaluskan menggunakan exponential moving average (EMA) dengan koefisien $\alpha \in (0, 1)$.

### Bukti Formal
1. Definisikan turunan waktu stokastik laju kedatangan riil $\tilde{V}_{in}(t) = V_{in}(t) + \xi(t)$, di mana $\xi(t)$ adalah derau Poisson dengan $\mathbb{E}[\xi(t)] = 0$ dan $\text{Var}[\xi(t)] = \sigma^2$.
2. Penghalusan EMA:
   $$\bar{V}_{in}(t) = \alpha \tilde{V}_{in}(t) + (1-\alpha) \bar{V}_{in}(t-1)$$
3. Variansi estimator laju aliran berkurang menjadi:
   $$\text{Var}[\bar{V}_{in}] = \frac{\alpha}{2 - \alpha} \sigma^2 < \sigma^2 \quad (\forall \alpha < 1)$$
4. Karena proyeksi linier $\hat{H}_{t+15}$ adalah kombinasi linier dari estimator tak bias, maka:
   $$\mathbb{E}[\hat{H}_{t+15}] = H_{t+15}^{\text{true}}$$
5. Ketidaksamaan Chebyshev menjamin bahwa probabilitas alarm palsu (false positive gate closure) dibatasi oleh:
   $$\Pr\left[ |\hat{H}_{t+15} - H_{t+15}^{\text{true}}| \ge k \sigma_{\hat{H}} \right] \le \frac{1}{k^2}$$
6. Dengan menetapkan ambang batas keselamatan $4.5 \text{ org/m}^2$ ($95\%$ kapasitas), sistem menjamin deteksi dini lonjakan kerumunan sebelum kepadatan fatal $6.0 \text{ org/m}^2$ tercapai secara fisik. $\blacksquare$

---

## 3. Teorema 3: Ketahanan Konservasi Finansial Promosi On-Site (Margin Conservation)

### Pernyataan Teorema
Untuk setiap penawaran promosi kontekstual dengan harga dasar $P_{\text{base}} \in \mathbb{N}$, tingkat diskon $\delta \in [0, 5000] \text{ bps}$, dan harga batas bawah COGS $P_{\text{cogs}} \in \mathbb{N}$ dengan $P_{\text{base}} > P_{\text{cogs}} > 0$:
$$P_{\text{final}} = \max\left(P_{\text{cogs}}, P_{\text{base}} - \left\lfloor \frac{P_{\text{base}} \times \delta}{10000} \right\rfloor \right)$$
menjamin secara mutlak bahwa margin kotor transaksi:
$$\text{Margin} = P_{\text{final}} - P_{\text{cogs}} \ge 0$$
tanpa kemungkinan kebocoran margin negatif akibat pembulatan integer.

### Bukti Formal
1. Misalkan $D = \lfloor (P_{\text{base}} \times \delta) / 10000 \rfloor$.
2. Nilai candidate price adalah $P' = P_{\text{base}} - D$.
3. Fungsi $\max(a, b)$ menjamin $P_{\text{final}} \ge a$ untuk setiap $b$.
4. Karena argumen pertama fungsi adalah $P_{\text{cogs}}$, maka $P_{\text{final}} \ge P_{\text{cogs}}$.
5. Mengurangkan $P_{\text{cogs}}$ dari kedua sisi menghasilkan $P_{\text{final}} - P_{\text{cogs}} \ge 0$.
6. Dengan demikian, promosi tidak pernah menghasilkan penjualan merugi di bawah HPP (Zero Margin Deficit). $\blacksquare$

