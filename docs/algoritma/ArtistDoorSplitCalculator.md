# Algoritma V5: ArtistDoorSplitCalculator

## 1. Intuisi Bisnis & Masalah
Dalam mengontrak musisi, DJ internasional, dan band penampil utama (*headliners*), model kompensasi finansial sering kali menggabungkan unsur kepastian honor (*guaranteed minimum fee*) dan insentif keberhasilan penjualan tiket pintu (*door split gross percentage*).

Sengketa sering terjadi bila:
1. Pemotongan beban acara langsung (*direct event expenses*) tidak transparan dan tidak dibatasi secara kontraktual.
2. Perhitungan pemotongan pajak penghasilan WHT (PPh 21 untuk artis domestik atau PPh 26 sebesar 20% untuk artis asing) dilakukan terlambat setelah uang kas ditransfer.
3. Rekonsiliasi pembayaran uang muka (*advance payment*) tidak tercatat secara terpadu di buku besar.
4. Fluktuasi nilai tukar mata uang asing (USD/SGD) menimbulkan selisih kurs yang merugikan salah satu pihak.

Algoritma V5 **`ArtistDoorSplitCalculator`** menyelesaikan seluruh masalah tersebut melalui formula komputasi versus deal yang deterministik, pencatatan pajak otomatis, konversi valuta asing presisi integer sen, dan penerbitan sertifikat keaslian ber-HMAC.

---

## 2. Rumus Matematis & Spesifikasi Formal
Diberikan parameter masukan:
- $\text{Deal Type} \in \{\text{'guarantee'}, \text{'door\_split'}, \text{'versus'}\}$
- $G \in \mathbb{N}$: Jaminan honor minimal artis dalam Rupiah penuh (`guarantee_idr`).
- $D_{\text{gross}} \in \mathbb{N}$: Total penjualan kotor tiket pintu acara (`door_gross_idr`).
- $E_{\text{approved}} \in \mathbb{N}$: Beban operasional langsung yang disetujui untuk dipotong (`approved_expenses_idr`).
- $S_{\text{bps}} \in [0, 10.000]$: Porsi bagi hasil pintu dalam basis poin (di mana $10.000\text{ bps} = 100\%$).
- $W_{\text{bps}} \in [0, 10.000]$: Tarif pajak withholding tax PPh 21/26 dalam basis poin (misal $2.000\text{ bps} = 20\%$).
- $A_{\text{paid}} \in \mathbb{N}$: Uang muka yang telah dicairkan sebelumnya (`advance_paid_idr`).
- $R_{\text{fx}} \in \mathbb{N}$: Nilai tukar kurs spot valas terhadap IDR (misal $16.250\text{ IDR/USD}$).

### Tahap 1: Evaluasi Pintu Bersih & Porsi Hak Artis
$$\text{Door Net} = \max(0, D_{\text{gross}} - E_{\text{approved}})$$
$$\text{Door Share} = \left\lfloor \frac{\text{Door Net} \times S_{\text{bps}}}{10.000} \right\rfloor$$

### Tahap 2: Penentuan Hak Bruto (Gross Pay)
$$\text{Gross Pay} = \begin{cases}
G & \text{jika } \text{Deal Type} = \text{'guarantee'} \\
\text{Door Share} & \text{jika } \text{Deal Type} = \text{'door\_split'} \\
\max(G, \text{Door Share}) & \text{jika } \text{Deal Type} = \text{'versus'}
\end{cases}$$

### Tahap 3: Pemotongan Pajak & Amortisasi Uang Muka
$$\text{WHT IDR} = \left\lfloor \frac{\text{Gross Pay} \times W_{\text{bps}}}{10.000} \right\rfloor$$
$$\text{Net Payable IDR} = \max(0, \text{Gross Pay} - \text{WHT IDR} - A_{\text{paid}})$$

### Tahap 4: Konversi Valuta Asing (Minor Unit Cents)
$$\text{Valas Minor Cents} = \left\lfloor \frac{\text{Net Payable IDR} \times 100}{R_{\text{fx}}} \right\rfloor$$

---

## 3. Pembuktian Langkah Demi Langkah Vektor Acuan
**Parameter Uji:**
- Deal Type: `versus`
- Jaminan Minimal $G = 100.000.000\text{ IDR}$
- Gross Penjualan Pintu $D_{\text{gross}} = 600.000.000\text{ IDR}$
- Beban Operasional Disetujui $E_{\text{approved}} = 150.000.000\text{ IDR}$
- Porsi Bagi Hasil $S_{\text{bps}} = 3.000\text{ bps}$ ($30\%$)
- Pajak PPh 26 $W_{\text{bps}} = 2.000\text{ bps}$ ($20\%$)
- Uang Muka Ditransfer $A_{\text{paid}} = 50.000.000\text{ IDR}$
- Kurs Spot $R_{\text{fx}} = 16.250\text{ IDR/USD}$

**Evaluasi:**
1. $\text{Door Net} = 600.000.000 - 150.000.000 = 450.000.000\text{ IDR}$.
2. $\text{Door Share} = \lfloor (450.000.000 \times 3.000) / 10.000 \rfloor = 135.000.000\text{ IDR}$.
3. $\text{Gross Pay} = \max(100.000.000, 135.000.000) = \mathbf{135.000.000\text{ IDR}}$.
4. $\text{WHT IDR} = \lfloor (135.000.000 \times 2.000) / 10.000 \rfloor = \mathbf{27.000.000\text{ IDR}}$.
5. $\text{Net Payable IDR} = 135.000.000 - 27.000.000 - 50.000.000 = \mathbf{58.000.000\text{ IDR}}$.
6. $\text{Valas Cents} = \lfloor (58.000.000 \times 100) / 16.250 \rfloor = \mathbf{356.923\text{ sen}}$ (setara $\$3.569,23\text{ USD}$).

Invarian terpenuhi: $\text{Gross Pay} \ge G$ dan sertifikat HMAC terverifikasi valid oleh `ArtistDoorSplitVerifier`.
