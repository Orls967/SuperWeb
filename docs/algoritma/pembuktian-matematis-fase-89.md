# Pembuktian Matematis & Verifikasi Algoritma Fase 89

Dokumen ini memuat formulasi matematika formal, batas kekonvergenan, dan jaminan invarian untuk empat algoritma bersertifikat Fase 89: Ticketing, Access Control, Usia, dan Venue Operations.

---

## 1. Algoritma V1: TierCountdownPricer

### 1.1 Formulasi Matematis
Harga tiket pada saat $t$ dan tingkat permintaan $s$ dimodelkan dengan fungsi kuotasi dinamis berbobot ganda:

$$P(t, s, w) = \text{clamp}\left(\left\lfloor \frac{P_{base} \cdot (1 + \Delta_{demand}(s)) \cdot (1 + \Delta_{weather}(w))}{1000} \right\rfloor \cdot 1000, P_{floor}, P_{ceiling}\right)$$

Di mana:
- $P_{base}$ adalah harga dasar tier nominal (IDR integer).
- $\Delta_{demand}(s)$ adalah fungsi tangga elastisitas permintaan terkuantisasi dalam satuan basis points (bps):
  $$\Delta_{demand}(s) = \begin{cases} 0.00 & \text{bila } s < 5000 \text{ bps} \\ +0.10 & \text{bila } 5000 \le s < 8000 \text{ bps} \\ +0.20 & \text{bila } s \ge 8000 \text{ bps} \end{cases}$$
- $\Delta_{weather}(w)$ adalah faktor sensitivitas cuaca hujan terbuka:
  $$\Delta_{weather}(w) = \begin{cases} -0.10 & \text{bila peluang hujan } w \ge 6000 \text{ bps} \\ 0.00 & \text{lainnya} \end{cases}$$
- Operator $\text{clamp}(x, L, U) = \max(L, \min(x, U))$ menjamin bahwa harga tiket tidak pernah menembus batas bawah lantai artis ($P_{floor}$) maupun plafon legal perlindungan konsumen ($P_{ceiling}$).

### 1.2 Bukti Keamanan Pembulatan (Rounding Monotonicity)
Karena pembulatan dilakukan dengan pembagian integer $\lfloor \cdot / 1000 \rfloor \cdot 1000$, maka:
1. Tidak ada nilai floating-point yang disimpan ke dalam basis data (mematuhi aturan A3).
2. Hasil harga selalu merupakan kelipatan tepat Rp 1.000.
3. Hak artis terlindungi karena $\forall x, \text{clamp}(x, P_{floor}, P_{ceiling}) \ge P_{floor}$.

---

## 2. Algoritma V2: AntiPassbackGate

### 2.1 Model State Automaton & Invarian Arah
Setiap tiket fisik maupun digital $T$ dimodelkan sebagai sistem transisi diskrit dengan tupel:
$$M_T = \langle S, \Sigma, \delta, s_0, F \rangle$$
Dengan himpunan state $S = \{\text{ISSUED}, \text{IN}, \text{OUT}, \text{REFUNDED}\}$, alfabet input $\Sigma = \{\text{SCAN\_IN}, \text{SCAN\_OUT}\}$, dan transisi $\delta$:
$$\delta(\text{ISSUED}, \text{SCAN\_IN}) = \text{IN}$$
$$\delta(\text{IN}, \text{SCAN\_OUT}) = \text{OUT}$$
$$\delta(\text{OUT}, \text{SCAN\_IN}) = \text{IN}$$

### 2.2 Batasan Re-Entry & Deteksi Replay Serangan Kriptografi
1. **Aturan Anti-Passback**: Transisi $\delta(\text{IN}, \text{SCAN\_IN})$ menghasilkan penolakan langsung `PASSBACK_VIOLATION`. Tiket yang sudah berada di dalam area pertunjukan dilarang keras dipindai masuk kembali sebelum tercatat pemindaian keluar yang sah.
2. **Kapasitas Re-Entry Maksimum**: Jumlah siklus masuk-keluar dibatasi oleh parameter $N_{max} = 2$.
   $$\sum_{k=1}^m \mathbf{1}_{\Sigma}(\text{SCAN\_IN}_k) \le N_{max} + 1$$
3. **Ketahanan Replay (Nonce Nonce Invariant)**: Setiap pemindaian menyertakan token acak sekali pakai $nonce \in \{0, 1\}^{128}$. Bila $nonce \in \mathcal{H}_{scan}$, gerbang menolak transmisi dengan kode `REPLAY_ATTACK`.

---

## 3. Algoritma V3: ZoneCapacityGovernor

### 3.1 Teorema Aliran Konservasi Massa
Dalam sebuah zona hiburan $Z$, okupansi aktual $O(t)$ pada detik $t$ dihitung semata-mata dari fakta pemindaian gerbang turnstile (Ground Truth Factual Occupancy):
$$O(t) = \sum_{i=1}^t \text{Inflow}(i) - \sum_{i=1}^t \text{Outflow}(i)$$

### 3.2 Kapasitas Efektif & Koefisien Reduksi Cuaca
Kapasitas legal maksimum $C_{legal}$ direduksi oleh marjin keselamatan statis $\mu = 0.05$ (500 bps) dan koefisien cuaca dinamis $\kappa(w)$:
$$C_{eff}(w) = \left\lfloor C_{legal} \cdot (1 - \mu) \cdot \kappa(w) \right\rfloor$$
Di mana:
$$\kappa(w) = \begin{cases} 0.70 & \text{bila } w \ge 7000 \text{ bps (hujan lebat / badai)} \\ 1.00 & \text{bila cuaca normal} \end{cases}$$

Kriteria admisi gerbang:
$$\text{Admisi Disetujui} \iff O(t) + 1 \le C_{eff}(w)$$

---

## 4. Algoritma V4: MinimumSpendSettlement

### 4.1 Persamaan Rekonsiliasi Tagihan Meja VIP
Pada saat penutupan sesi meja VIP $B$, sistem menghitung kewajiban finansial final antara deposit muka ($D$), komitmen belanja minimum ($M$), dan konsumsi riil botol/makanan ($C$):

$$\text{FinalCharge}(C, M, D) = \max\left(0, \max(M, C) - D\right)$$

### 4.2 Teorema Hangus No-Show (Forfeiture Guarantee)
Bila tamu gagal hadir hingga batas toleransi jam operasional $T_{grace}$:
$$\text{ForfeitureAmount} = D$$
Pendapatan hangus diakui langsung ke buku besar:
$$\text{Dr. } \texttt{ven:vip\_unearned\_rev:IDR} \quad D$$
$$\text{Cr. } \texttt{ven:vip\_noshow\_rev:IDR} \quad D$$

Hal ini membuktikan secara matematis bahwa neraca saldo penampungan deposit selalu seimbang dan tidak pernah menyisakan saldo mengambang tanpa pemilik.
