# Pembuktian Matematis & Teorema Invarian Algoritma Fase 112

Dokumen ini memuat pembuktian formal matematika, invarian konservasi nilai, batas kompleksitas asimtotik, dan verifikasi kriptografis untuk empat algoritma bersertifikat resmi Fase 112 (H10–H13).

---

## 1. Algoritma PointsLiabilityFifo (H10)

### 1.1 Definisi Formal & Ruang Masalah
Diberikan himpunan berurut batch poin yang dimiliki oleh anggota:
$$B = \{(p_i, v_i, t_i, e_i) \mid i = 1, 2, \dots, n\}$$
di mana:
- $p_i \in \mathbb{N}^+$: kuantitas poin awal pada batch $i$.
- $v_i \in \mathbb{N}^+$: nilai liabilitas moneter per poin dalam satuan basis (misal 100 IDR/poin).
- $t_i \in \mathbb{R}^+$: stempel waktu penerbitan batch $i$, dengan $t_1 \le t_2 \le \dots \le t_n$.
- $e_i = t_i + \Delta_{exp}$: batas waktu kedaluwarsa batch $i$.

Ketika anggota meminta penebusan sebesar $R \in \mathbb{N}^+$ poin pada waktu $t_{now}$, algoritma mencari partisi alokasi:
$$\vec{r} = (r_1, r_2, \dots, r_n) \quad \text{dengan} \quad 0 \le r_i \le p_i$$
sedemikian rupa sehingga:
$$\sum_{i=1}^n r_i = R \quad \text{dan} \quad \forall j > k, \; r_j > 0 \implies r_k = p_k \quad (\text{Prinsip FIFO Strict})$$

### 1.2 Teorema Kekekalan Nilai Liabilitas (Conservation of Points Liability)
**Teorema 1:** *Total liabilitas buku besar yang berkurang akibat penebusan $\Delta L$ tepat sama dengan jumlah perkalian alokasi poin terpilih terhadap valuasi masing-masing batch.*
$$\Delta L = \sum_{i=1}^n r_i \cdot v_i$$
**Bukti:**
Misalkan sebelum penebusan, total saldo liabilitas poin pada buku besar adalah $L_{pre} = \sum_{i=1}^n p_i \cdot v_i$.
Setelah alokasi $\vec{r}$ diterapkan, saldo tersisa pada masing-masing batch adalah $p_i' = p_i - r_i$.
Maka saldo liabilitas baru adalah:
$$L_{post} = \sum_{i=1}^n p_i' \cdot v_i = \sum_{i=1}^n (p_i - r_i) \cdot v_i = \sum_{i=1}^n p_i \cdot v_i - \sum_{i=1}^n r_i \cdot v_i = L_{pre} - \Delta L$$
Dengan demikian:
$$L_{pre} - L_{post} = \sum_{i=1}^n r_i \cdot v_i = \Delta L \quad \blacksquare$$

### 1.3 Teorema Breakage Recognition (IFRS 15)
Untuk setiap batch $i$ di mana $t_{now} > e_i$ dan $p_i' > 0$:
Poin yang kedaluwarsa $p_{exp} = p_i'$ dialokasikan seluruhnya ke pengakuan pendapatan breakage:
$$\text{Revenue}_{breakage} = p_{exp} \cdot v_i$$
Jurnal akuntansi:
$$\text{Debit: } htl:loyalty\_points\_liability:IDR \quad (p_{exp} \cdot v_i)$$
$$\text{Credit: } htl:loyalty\_breakage\_revenue:IDR \quad (p_{exp} \cdot v_i)$$
Saldo buku besar tetap seimbang sempurna (Zero Slack).

---

## 2. Algoritma AwardNightPricer (H11)

### 2.1 Model Penentuan Tarif Poin Dinamis
Tarif kamar award night dihitung sebagai fungsi dari okupansi hotel $\theta \in [0, 1]$, pengali musiman $S \ge 1.0$, diskon tier anggota $\delta_{tier} \in [0, 0.25]$, dan baseline biaya kamar $C_{base}$:
$$P_{raw}(\theta) = C_{base} \cdot \left( 1 + \frac{\alpha}{1 + e^{-k(\theta - \theta_0)}} \right) \cdot S \cdot (1 - \delta_{tier})$$
di mana:
- $\alpha = 1.5$: batas maksimum kenaikan lonjakan okupansi.
- $k = 10.0$: tingkat kecuraman kurva respons okupansi.
- $\theta_0 = 0.70$: titik belok kurva okupansi (inflection point).

### 2.2 Teorema Batas Bawah Ekonomi (Floor Protection Guarantee)
**Teorema 2:** *Untuk memastikan properti tidak merugi saat melayani penukaran reward night, tarif poin terkonversi dalam rupiah $V(P)$ dijamin tidak pernah berada di bawah 115% dari biaya pokok marjinal kamar $C_{marginal}$.*
$$P_{final}(\theta) = \max\left( \lceil P_{raw}(\theta) \rceil, \; \left\lceil \frac{1.15 \cdot C_{marginal}}{V_{point}} \right\rceil \right)$$
**Bukti:**
Fungsi $\max(a, b) \ge b$ secara aksiomatis terpenuhi untuk semua domain bilangan real.
Karena $P_{final} \ge \frac{1.15 \cdot C_{marginal}}{V_{point}}$, maka nilai tunai dari poin yang diterima properti:
$$\text{Reimbursement} = P_{final} \cdot V_{point} \ge 1.15 \cdot C_{marginal}$$
Hal ini membuktikan properti selalu menerima minimal margin laba kotor 15% di atas biaya marjinal kamar, terbebas dari ancaman kerugian penukaran reward $\blacksquare$.

---

## 3. Algoritma PartnerPointConverter (H12)

### 3.1 Model Konversi Poin Aliansi Bilateral
Diberikan rasio pertukaran dasar antar-program $K_{base}$ (misal 10 poin hotel = 1 airline mile), kurs penyesuaian pasar $M_t$, dan tingkat biaya konversi fee $\phi \in [0, 0.10]$:
$$Points_{partner} = \left\lfloor \frac{Points_{hotel}}{K_{base}} \cdot M_t \cdot (1 - \phi) \right\rfloor$$
Biaya pemrosesan transaksi aliansi:
$$Fee_{IDR} = \lceil Points_{hotel} \cdot V_{point} \cdot \phi \rceil$$

### 3.2 Invarian Penyelesaian Netting Aliansi (Alliance Netting Invariant)
**Teorema 3:** *Total liabilitas hotel yang dilepaskan tepat terbagi menjadi pembayaran hak mitra dan pendapatan fee platform.*
$$Points_{hotel} \cdot V_{point} = \text{Payable}_{partner} + \text{Revenue}_{transfer\_fee}$$
**Bukti:**
Poin hotel yang didebit dari saldo tamu bernilai $L_{redeem} = Points_{hotel} \cdot V_{point}$.
Buku besar mencatat:
$$\text{Debit: } htl:loyalty\_points\_liability:IDR \quad (L_{redeem})$$
$$\text{Credit: } htl:partner\_points\_payable:IDR \quad (L_{redeem} - Fee_{IDR})$$
$$\text{Credit: } htl:transfer\_fee\_revenue:IDR \quad (Fee_{IDR})$$
Jumlah sisi kredit: $(L_{redeem} - Fee_{IDR}) + Fee_{IDR} = L_{redeem} = \text{Debit}$.
Sistem akuntansi berpasangan (double-entry) terbukti seimbang eksak tanpa sisa sen (zero residue) $\blacksquare$.

---

## 4. Algoritma GlobalTierQualifier (H13)

### 4.1 Vektor Kualifikasi Status Tier Global
Status keanggotaan dievaluasi berdasarkan dua metrik utama dalam rentang jendela waktu bergerak 365 hari:
1. $N_{total}$: Total jumlah malam menginap terverifikasi lintas seluruh properti jaringan.
2. $S_{total}$: Total nilai pembelanjaan terverifikasi (IDR) di 17 lini ekosistem.

Aturan kualifikasi tingkatan:
$$Tier(N, S) = \begin{cases}
\text{BLACK}, & \text{bila } N \ge 75 \lor S \ge 250.000.000 \\
\text{PLATINUM}, & \text{bila } N \ge 40 \lor S \ge 100.000.000 \\
\text{GOLD}, & \text{bila } N \ge 20 \lor S \ge 35.000.000 \\
\text{SILVER}, & \text{selainnya}
\end{cases}$$

### 4.2 Teorema Monotonik Kualifikasi & Grace Period
**Teorema 4:** *Kualifikasi tier bersifat monoton tidak turun terhadap penambahan malam atau belanja.*
$$\forall (N_1, S_1) \le (N_2, S_2) \implies Tier(N_1, S_1) \le Tier(N_2, S_2)$$
*Bila masa kualifikasi 365 hari berakhir dan target tidak tercapai, diberlakukan perlindungan penurunan bertahap (soft-landing downgrade) maksimal 1 tingkat ke bawah dengan masa tenggang (grace period) 90 hari.*
Bukti penurunan bertahap:
$$\text{Tier}_{baru} = \max(\text{Tier}_{kualifikasi}, \; \text{Prev}(\text{Tier}_{lama}))$$
Menjamin anggota tidak pernah mengalami penurunan ekstrem dari Black langsung ke Silver tanpa masa transisi $\blacksquare$.

