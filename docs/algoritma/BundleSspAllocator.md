# Algoritma V6: BundleSspAllocator (IFRS 15 / PSAK 72)

## 1. Intuisi Bisnis & Masalah
Ketika konsumen membeli paket festival kombo (*staycation bundle*) seharga Rp 2.999.999 yang terdiri dari tiket festival, kamar hotel rekanan, kredit makanan di tenant resto, dan shuttle bus bandara, total nilai jual berdiri sendiri (*Standalone Selling Price* / SSP) dari seluruh komponen biasanya lebih tinggi daripada harga paket (misal total SSP adalah Rp 4.000.000, mencerminkan diskon paket sekitar 25%).

Sesuai standar akuntansi **IFRS 15 / PSAK 72**, diskon paket tersebut harus dialokasikan secara proporsional ke setiap kewajiban pelaksanaan berdasarkan perbandingan SSP relatif masing-masing.

Tantangan teknis utama adalah **masalah pembagian bilangan bulat moneter**:
- Pembagian matematika biasa selalu menyisakan pecahan sen / rupiah yang tidak dapat dicatat dalam mata uang integer IDR.
- Deviasi pembulatan dapat menyebabkan ketidakseimbangan jurnal umum atau selisih piutang-hutang ke vendor mitra.

Algoritma V6 **`BundleSspAllocator`** menyelesaikan masalah ini dengan mengadopsi **Metode Hamilton-Hare (Largest Remainder)** yang terbukti secara matematis menjamin total alokasi tepat sama dengan harga transaksi tanpa sisa 1 Rupiah pun.

---

## 2. Formulasi Matematis Hamilton-Hare Integer Penuh
Diberikan himpunan komponen paket $C = \{c_1, c_2, \dots, c_n\}$, di mana setiap komponen memiliki nilai SSP $s_i \in \mathbb{N}^+$, harga paket transaksi $P_{\text{bundle}} \in \mathbb{N}^+$, dan tarif komisi platform $F_{\text{bps}} \in [0, 10.000]$.

1. **Total Nilai Standalone Selling Price:**
   $$S_{\text{total}} = \sum_{i=1}^n s_i$$

2. **Tahap 1: Kuota Integer Dasar & Sisa Modulo:**
   Untuk setiap komponen $i \in \{1, \dots, n\}$:
   $$\text{BaseAlloc}_i = \left\lfloor \frac{P_{\text{bundle}} \times s_i}{S_{\text{total}}} \right\rfloor$$
   $$\text{Remainder}_i = (P_{\text{bundle}} \times s_i) \pmod{S_{\text{total}}}$$

3. **Tahap 2: Residu Pembulatan Total:**
   $$R = P_{\text{bundle}} - \sum_{i=1}^n \text{BaseAlloc}_i \quad (0 \le R < n)$$

4. **Tahap 3: Alokasi Residu Deterministik:**
   Urutkan komponen berdasarkan tripel kunci $(\text{Remainder}_i \downarrow, s_i \downarrow, i \uparrow)$.
   Untuk $k = 1$ hingga $R$, tambahkan 1 Rupiah ke komponen peringkat ke-$k$:
   $$\text{Allocated}_i = \text{BaseAlloc}_i + \begin{cases} 1 & \text{jika } \text{rank}(i) \le R \\ 0 & \text{lainnya} \end{cases}$$

5. **Tahap 4: Pembagian Platform Fee & Hak Bersih Vendor:**
   $$\text{Platform Fee}_i = \left\lfloor \frac{\text{Allocated}_i \times F_{\text{bps}}}{10.000} \right\rfloor$$
   $$\text{Vendor Net}_i = \text{Allocated}_i - \text{Platform Fee}_i$$

---

## 3. Pembuktian Langkah Demi Langkah Vektor Acuan
**Parameter Uji:**
- $P_{\text{bundle}} = 2.999.999\text{ IDR}$
- $F_{\text{bps}} = 500\text{ bps}$ ($5\%$)
- Komponen:
  1. `pass` ($s_1 = 1.800.000$)
  2. `hotel` ($s_2 = 1.500.000$)
  3. `fnb` ($s_3 = 400.000$)
  4. `shuttle` ($s_4 = 300.000$)
- $S_{\text{total}} = 1.800.000 + 1.500.000 + 400.000 + 300.000 = 4.000.000\text{ IDR}$.

**Langkah Komputasi:**
1. Alokasi Dasar & Remainder:
   - Item 1: $\lfloor 2.999.999 \times 1.800.000 / 4.000.000 \rfloor = 1.349.999$. Modulo: $2.200.000$.
   - Item 2: $\lfloor 2.999.999 \times 1.500.000 / 4.000.000 \rfloor = 1.124.999$. Modulo: $2.500.000$.
   - Item 3: $\lfloor 2.999.999 \times 400.000 / 4.000.000 \rfloor = 299.999$. Modulo: $3.600.000$.
   - Item 4: $\lfloor 2.999.999 \times 300.000 / 4.000.000 \rfloor = 224.999$. Modulo: $3.700.000$.
2. Jumlah Alokasi Dasar $= 1.349.999 + 1.124.999 + 299.999 + 224.999 = 2.999.996\text{ IDR}$.
3. Residu $R = 2.999.999 - 2.999.996 = 3\text{ IDR}$.
4. Peringkat Remainder Terbesar:
   - Peringkat 1: Item 4 ($3.700.000$) $\implies +1 \implies 225.000\text{ IDR}$.
   - Peringkat 2: Item 3 ($3.600.000$) $\implies +1 \implies 300.000\text{ IDR}$.
   - Peringkat 3: Item 2 ($2.500.000$) $\implies +1 \implies 1.125.000\text{ IDR}$.
   - Peringkat 4: Item 1 ($2.200.000$) $\implies +0 \implies 1.349.999\text{ IDR}$.
5. Total Alokasi Akhir $= 1.349.999 + 1.125.000 + 300.000 + 225.000 = \mathbf{2.999.999\text{ IDR}}$.

**Konservasi Uang Terpenuhi Secara Sempurna Tanpa Sisa 1 Rupiah Pun.**
