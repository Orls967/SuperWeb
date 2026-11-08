# Pembuktian Matematis & Teorema Algoritma Fase 114: MICE, Banquet BEO & Global Event Sales Engine

## 1. Algoritma H14: BlockAllotmentReleaser

### 1.1 Formulasi Masalah
Diberikan alokasi awal kamar blok untuk acara MICE sebesar $K_0 \in \mathbb{N}_{\ge 1}$, jumlah kamar yang telah diisi nama tamu (*pickup count*) pada hari pemeriksaan $t$ sebesar $P_t \in \mathbb{N}_0$ dengan $P_t \le K_t$.
Misalkan $T_{\text{event}}$ adalah tanggal awal kedatangan acara, dan selisih hari menuju acara adalah $\Delta = T_{\text{event}} - t$.
Tingkat pelepasan bertingkat ditentukan oleh aturan cutoff:
- Jika $\Delta > 30$, faktor pelepasan fraksional $\alpha_t = 0$.
- Jika $14 < \Delta \le 30$, faktor pelepasan fraksional $\alpha_t = r_1$ (standar $r_1 = 0.25$).
- Jika $7 < \Delta \le 14$, faktor pelepasan fraksional $\alpha_t = r_2$ (standar $r_2 = 0.50$).
- Jika $\Delta \le 7$, faktor pelepasan fraksional $\alpha_t = 1.00$ (seluruh sisa kamar un-picked dilepaskan).

Jumlah kamar yang belum di-pickup pada waktu $t$ adalah:
$$U_t = K_t - P_t$$

Jumlah kamar yang harus dilepaskan ke inventori publik adalah:
$$R_t = \lfloor \alpha_t \cdot U_t \rfloor$$

Setelah pelepasan dilakukan, kuota kamar blok baru yang tersisa untuk grup adalah:
$$K_{t+1} = K_t - R_t$$

### 1.2 Teorema Invarian Konservasi dan Anti-Oversell
**Teorema 1 (Ketiadaan Pelepasan Negatif):**
Untuk setiap $t$, berlaku $0 \le R_t \le U_t$, sehingga alokasi kamar baru $K_{t+1} \ge P_t$. Tidak ada tamu yang telah terdaftar (*pickup*) yang kehilangan kamar akibat pelepasan otomatis.
*Bukti:*
Karena $\alpha_t \in [0, 1]$ dan $U_t \ge 0$, maka $\alpha_t \cdot U_t \ge 0$. Fungsi floor $\lfloor x \rfloor$ mempertahankan tanda tak-negatif sehingga $R_t \ge 0$.
Selain itu, $\alpha_t \le 1 \implies \lfloor \alpha_t U_t \rfloor \le U_t$.
Maka:
$$K_{t+1} = K_t - R_t = K_t - \lfloor \alpha_t (K_t - P_t) \rfloor \ge K_t - (K_t - P_t) = P_t$$
Terbukti bahwa kuota sisa tidak pernah lebih kecil daripada jumlah kamar yang telah terkonfirmasi.

---

## 2. Algoritma H15: BanquetBomExploder

### 2.1 Model Komputasi Bahan Baku & Minuman
Untuk jamuan makan banquet dengan jumlah tamu terjamin $N_{\text{pax}}$, dapur hotel menyiapkan makanan dengan faktor penyangga (*safety buffer*) $\beta$ (standar $\beta = 1.05$ atau 5% ekstra porsi):
$$N_{\text{prep}} = \lceil N_{\text{pax}} \cdot \beta \rceil$$

Setiap item menu $i \in \{1, \dots, M\}$ terdiri atas komponen bahan baku mentah $j \in \{1, \dots, C_i\}$ dengan takaran porsi standar $w_{ij}$ (dalam gram atau mililiter).
Tingkat kehilangan saat pembersihan/pemasakan (*cooking & trimming yield loss*) untuk bahan $j$ dinyatakan sebagai rasio kehilangan $\lambda_j \in [0, 0.5]$.
Maka kebutuhan kotor bahan baku mentah $W_{ij}^{\text{gross}}$ dihitung sebagai:
$$W_{ij}^{\text{gross}} = \frac{N_{\text{prep}} \cdot w_{ij}}{1 - \lambda_j}$$

Untuk minuman beralkohol atau minuman botolan berukuran volume $V_{\text{bottle}}$, dengan takaran penuangan per gelas $v_{\text{pour}}$ dan faktor varians tumpah/susut penuangan (*pouring shrinkage variance*) $\sigma_{\text{pour}} \in [0, 0.2]$:
Jumlah porsi minuman yang dibutuhkan adalah:
$$N_{\text{pour}} = \lceil N_{\text{prep}} \cdot (1 + \sigma_{\text{pour}}) \rceil$$
Jumlah botol yang wajib ditarik dari gudang adalah:
$$B = \left\lceil \frac{N_{\text{pour}} \cdot v_{\text{pour}}}{V_{\text{bottle}}} \right\rceil$$

### 2.2 Estimasi Biaya Tenaga Kerja Kasual (Casual Labor)
Biaya tenaga kerja kasual banquet dimodelkan sebagai fungsi rasio pramusaji per pax ($\rho_{\text{waiter}} = \frac{1}{20}$) dan juru masak per pax ($\rho_{\text{cook}} = \frac{1}{40}$):
$$L_{\text{waiter}} = \lceil N_{\text{pax}} \cdot \rho_{\text{waiter}} \rceil, \quad L_{\text{cook}} = \lceil N_{\text{pax}} \cdot \rho_{\text{cook}} \rceil$$
Total biaya upah per jam $H_{\text{duration}}$ dengan tarif upah standar per jam $w_{\text{rate}}$ adalah:
$$\text{Cost}_{\text{labor}} = (L_{\text{waiter}} + L_{\text{cook}}) \cdot H_{\text{duration}} \cdot w_{\text{rate}}$$

---

## 3. Algoritma H16: EventMarginCalculator

### 3.1 Formulasi Struktur Pendapatan dan Biaya Langsung
Pendapatan total event $R_{\text{total}}$ adalah jumlahan dari:
$$R_{\text{total}} = R_{\text{hall}} + R_{\text{fnb}} + R_{\text{av}} + R_{\text{rooms}} + R_{\text{expo}} + R_{\text{misc}}$$

Biaya langsung total $C_{\text{direct}}$ adalah jumlahan dari:
$$C_{\text{direct}} = C_{\text{food\_raw}} + C_{\text{bev\_raw}} + C_{\text{labor}} + C_{\text{av\_vendor}} + C_{\text{expo\_utility}} + C_{\text{cleaning}}$$

Margin kotor moneter adalah:
$$\text{GM}_{\text{idr}} = R_{\text{total}} - C_{\text{direct}}$$

Margin kotor persentase adalah:
$$\text{GM}_{\%} = \begin{cases} 
\left( \frac{\text{GM}_{\text{idr}}}{R_{\text{total}}} \right) \times 100\%, & \text{jika } R_{\text{total}} > 0 \\
0\%, & \text{jika } R_{\text{total}} = 0 
\end{cases}$$

### 3.2 Teorema Guardrail Profitabilitas
**Teorema 2 (Invarian Batas Bawah Margin 40%):**
Sebuah proposal acara hanya berstatus `APPROVED_PROPOSAL` jika dan hanya jika:
$$\text{GM}_{\%} \ge 40.0\%$$
Bila $\text{GM}_{\%} < 40.0\%$, sistem mengembalikan status `GUARDRAIL_BREACH_REJECTED` dengan rekomendasi kenaikan harga minimum $\Delta R_{\text{min}}$ agar mencapai ambang 40%:
$$\Delta R_{\text{min}} = \max\left(0, \frac{C_{\text{direct}}}{1 - 0.40} - R_{\text{total}}\right) = \max\left(0, \frac{C_{\text{direct}}}{0.60} - R_{\text{total}}\right)$$

---

## 4. Algoritma H17: MilestoneScheduleBuilder

### 4.1 Formulasi Pembagian Termin Pembayaran
Diberikan nilai total kontrak pernikahan atau konferensi $V_{\text{contract}} \in \mathbb{N}_{> 0}$, tanggal penandatanganan $D_{\text{sign}}$, dan tanggal pelaksanaan acara $D_{\text{event}}$.
Selang waktu hari total adalah $D_{\text{span}} = D_{\text{event}} - D_{\text{sign}}$.

Jadwal termin dibentuk atas 4 tonggak (milestone) pembayaran berurutan:
1. **Milestone 1 (DP Konfirmasi 20%):**
   - Nilai: $M_1 = \lfloor 0.20 \times V_{\text{contract}} \rfloor$
   - Jatuh tempo: $D_{\text{sign}} + 7 \text{ hari}$
2. **Milestone 2 (Termin Tengah 30%):**
   - Nilai: $M_2 = \lfloor 0.30 \times V_{\text{contract}} \rfloor$
   - Jatuh tempo: $\max(D_{\text{sign}} + 14 \text{ hari}, D_{\text{event}} - 90 \text{ hari})$
3. **Milestone 3 (Termin Jaminan Final 30%):**
   - Nilai: $M_3 = \lfloor 0.30 \times V_{\text{contract}} \rfloor$
   - Jatuh tempo: $\max(D_{\text{sign}} + 21 \text{ hari}, D_{\text{event}} - 30 \text{ hari})$
4. **Milestone 4 (Pelunasan Sisa):**
   - Nilai penyeimbang (*reconciling residual*):
     $$M_4 = V_{\text{contract}} - (M_1 + M_2 + M_3)$$
   - Jatuh tempo: $D_{\text{event}} - 7 \text{ hari}$

### 4.2 Teorema Konservasi Nilai Kontrak
**Teorema 3 (Konservasi Nilai Total Tanpa Selisih Pembulatan):**
$$\sum_{k=1}^4 M_k = M_1 + M_2 + M_3 + (V_{\text{contract}} - (M_1 + M_2 + M_3)) = V_{\text{contract}}$$
Sisa pembulatan rupiah (*rounding residual cents*) diserap sempurna oleh termin terakhir ($M_4$), sehingga tidak pernah ada selisih nilai sepeser pun antara jumlah jadwal termin dan nilai kontrak.

