# Algoritma V4: MinimumSpendSettlement

## 1. Intuisi Bisnis & Masalah
Pada layanan meja VIP (*daybed*, *cabana*, *VIP sofa*), tamu memesan dengan komitmen belanja minimum (*minimum spend*) serta menyetorkan uang muka deposit yang ditahan di akun escrow (`ven:vip_escrow:IDR`). Ketika tamu hadir dan memesan makanan/minuman, tagihan akhir harus membandingkan belanja aktual terhadap minimum spend:
- Bila belanja melebihi minimum spend, seluruh belanja ditagihkan.
- Bila belanja kurang dari minimum spend, selisih kekurangan diakui sebagai pendapatan *shortfall* (`ven:table_revenue:IDR`).
- Bila tamu tidak hadir (*no-show*), deposit dipotong sebagai denda hangus sebesar persentase tertentu (fixture: 50% ke `ven:vip_noshow_rev:IDR`) dan sisanya dikembalikan ke tamu.

## 2. Rumus Matematis & Spesifikasi
Diberikan:
- $S_{\text{min}} \in \mathbb{N}$: Komitmen minimum spend meja VIP dalam integer Rupiah (IDR).
- $D_{\text{held}} \in \mathbb{N}$: Uang muka deposit yang telah ditahan di akun escrow (IDR).
- $B_{\text{actual}} \in \mathbb{N}$: Total belanja makanan/minuman aktual dari POS (IDR).
- $F_{\text{noshow}} \in [0, 10.000]$: Persentase forfeiture no-show dalam basis poin (fixture: $5.000\text{ bps} = 50\%$).

### Alur Skenario Hadir (*Present Guests*):
1. **Total Tagihan Akhir ($T_{\text{bill}}$)**:
   $$T_{\text{bill}} = \max(B_{\text{actual}}, S_{\text{min}})$$
2. **Pemisahan Komponen Pendapatan**:
   - Pendapatan Konsumsi F&B riil:
     $$R_{\text{fb}} = B_{\text{actual}}$$
   - Pendapatan Kekurangan Minimum Spend (*Shortfall Revenue*):
     $$R_{\text{shortfall}} = \max(0, S_{\text{min}} - B_{\text{actual}})$$
   - Pembuktian Kesetimbangan:
     $$R_{\text{fb}} + R_{\text{shortfall}} = T_{\text{bill}}$$
3. **Eksekusi Penagihan Finansial**:
   - Alokasi Penangkapan Deposit Escrow:
     $$P_{\text{captured}} = \min(D_{\text{held}}, T_{\text{bill}})$$
   - Tagihan Tambahan ke Kartu/Dompet Pelanggan:
     $$P_{\text{additional}} = \max(0, T_{\text{bill}} - D_{\text{held}})$$
   - Pengembalian Sisa Deposit (bila ada deposit berlebih):
     $$R_{\text{refund}} = \max(0, D_{\text{held}} - T_{\text{bill}})$$

### Alur Skenario Tidak Hadir (*No-Show Guests*):
1. **Denda Hangus Pembatalan Sepihak ($D_{\text{forfeited}}$)**:
   $$D_{\text{forfeited}} = \left\lfloor \frac{D_{\text{held}} \times F_{\text{noshow}}}{10.000} \right\rfloor$$
2. **Pengembalian Sisa Deposit ($D_{\text{refund}}$)**:
   $$D_{\text{refund}} = D_{\text{held}} - D_{\text{forfeited}}$$
3. **Pengakuan Buku Besar**:
   - Debit: `ven:vip_unearned_rev:IDR` sebesar $D_{\text{held}}$.
   - Kredit: `ven:vip_noshow_rev:IDR` sebesar $D_{\text{forfeited}}$.
   - Kredit: `ven:vip_escrow:IDR` (pengembalian ke pelanggan) sebesar $D_{\text{refund}}$.

## 3. Contoh Vektor Pengujian Langkah Demi Langkah
Parameter sistem: $S_{\text{min}} = 5.000.000\text{ IDR}$, $D_{\text{held}} = 2.000.000\text{ IDR}$, $F_{\text{noshow}} = 5.000\text{ bps}$ ($50\%$).

### Kasus 1: Belanja Melebihi Minimum Spend
- Belanja aktual: $B_{\text{actual}} = 6.300.000\text{ IDR}$.
- Tagihan: $\max(6.300.000, 5.000.000) = 6.300.000\text{ IDR}$.
- Capture deposit yang ditahan: $2.000.000\text{ IDR}$.
- Tagihan tambahan: $6.300.000 - 2.000.000 = \mathbf{4.300.000\text{ IDR}}$.
- Shortfall: $0\text{ IDR}$.

### Kasus 2: Belanja Di Bawah Minimum Spend (Shortfall)
- Belanja aktual: $B_{\text{actual}} = 3.200.000\text{ IDR}$.
- Tagihan: $\max(3.200.000, 5.000.000) = 5.000.000\text{ IDR}$.
- Capture deposit yang ditahan: $2.000.000\text{ IDR}$.
- Tagihan tambahan: $5.000.000 - 2.000.000 = \mathbf{3.000.000\text{ IDR}}$.
- Komposisi pendapatan: F&B $3.200.000\text{ IDR}$ + Shortfall $\mathbf{1.800.000\text{ IDR}}$.

### Kasus 3: Tamu Tidak Hadir (No-Show)
- Status booking: `no_show_forfeited`.
- Denda hangus: $\lfloor 2.000.000 \times 5.000 / 10.000 \rfloor = \mathbf{1.000.000\text{ IDR}}$.
- Pengembalian sisa ke tamu: $2.000.000 - 1.000.000 = \mathbf{1.000.000\text{ IDR}}$.

## 4. Sertifikat Matematis & Pembuktian
Setiap penyelesaian menghasilkan objek bersertifikat:
```php
final readonly class MinimumSpendCertificate
{
    public function __construct(
        public int $minimumSpendIdr,
        public int $depositHeldIdr,
        public int $actualSpendIdr,
        public int $totalBillIdr,
        public int $capturedDepositIdr,
        public int $additionalChargeIdr,
        public int $shortfallIdr,
        public int $forfeitedDepositIdr,
        public int $refundedDepositIdr,
        public string $hmacSignature,
    ) {}
}
```

## 5. Kompleksitas & Karakteristik Kinerja
- **Kompleksitas Waktu**: $\mathcal{O}(1)$ operasi aritmatika integer.
- **Kompleksitas Ruang**: $\mathcal{O}(1)$ alokasi memori.
- **Lokasi Kode**: `Modules\Venue\Domain\Algorithms\MinimumSpendSettlement`.
