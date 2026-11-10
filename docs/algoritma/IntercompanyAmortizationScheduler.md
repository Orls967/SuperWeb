# Dokumentasi Algoritma: `IntercompanyAmortizationScheduler` (D6)

**Namespace**: `Modules\Intercompany\Domain\Algorithms\IntercompanyAmortizationScheduler`  
**Fase Terkait**: Fase 52 — Intercompany, Transfer Pricing & Konsolidasi Keuangan Grup  
**Sertifikat Terkait**: `IntercompanyAmortizationCertificate`, `IntercompanyAmortizationVerifier`  

---

## 1. Intuisi Bisnis & Landasan Teori
Pinjaman antar-perusahaan (*intercompany loans*) dalam struktur pembiayaan grup korporasi tunduk pada regulasi perpajakan yang ketat:
1. **Suku Bunga Wajar**: Wajib menerapkan tingkat suku bunga arm's length, terdiri atas suku bunga acuan pasar (mis. BI-Rate / SOFR) ditambah spread risiko kredit pihak terafiliasi.
2. **Kewajiban Pemotongan Pajak Penghasilan (Withholding Tax)**:
   - Sesuai UU PPh Pasal 23, peminjam (debitur) domestik wajib memotong 15% dari bunga bruto atas pembayaran bunga kepada kreditur badan usaha dalam negeri.
   - Sesuai UU PPh Pasal 26, peminjam wajib memotong 20% (atau tarif P3B / tax treaty jika kreditur berada di yurisdiksi mitra perjanjian pajak berganda).
3. **Integritas Aritmetika**: Jadwal pembayaran cicilan pokok dan bunga harus bebas dari galat akumulasi satu sen (*penny-drift*). Selisih pembulatan fraksional diserap penuh pada angsuran terakhir (*final installment absorption*), sehingga $\sum \text{PrincipalDue} = \text{OriginalPrincipal}$.

Algoritma `IntercompanyAmortizationScheduler` menyusun skedul amortisasi periodik deterministik, menghitung bunga kotor, pajak potong, dan kas transfer bersih secara otomatis.

---

## 2. Formulasi Matematis

Diberikan:
- $P$: Pokok pinjaman awal dalam unit minor (integer, misal Rupiah penuh).
- $r$: Suku bunga acuan dasar dalam basis points (bps, 100 bps = 1,00%).
- $s$: Spread risiko kredit arm's length dalam basis points.
- $R_{\text{total}} = r + s$: Total suku bunga tahunan dalam basis points.
- $T_{\text{wht}}$: Tarif pajak pemotongan bunga dalam basis points (misal 1500 bps untuk PPh 23).
- $N$: Jumlah angsuran / periode pembayaran (integer, misal 12 bulan).
- $\text{Days}_k$: Jumlah hari aktual pada periode $k$ (basis konvensi tahun perbankan 360 hari).

### A. Alokasi Pokok Angsuran Per Periode
Untuk amortisasi pokok tetap (*straight-line principal*):
$$\text{PrincipalBase} = \left\lfloor \frac{P}{N} \right\rfloor$$
Untuk periode $k = 1, 2, \dots, N - 1$:
$$\text{PrincipalDue}_k = \text{PrincipalBase}$$
Untuk periode terakhir $k = N$:
$$\text{PrincipalDue}_N = P - \sum_{j=1}^{N-1} \text{PrincipalDue}_j$$

### B. Perhitungan Bunga Kotor & Pemotongan Pajak
Untuk setiap periode $k$ dengan saldo pokok awal periode $B_k$:
1. **Bunga Kotor (Gross Interest)**:
   $$\text{InterestGross}_k = \left\lfloor \frac{B_k \times R_{\text{total}} \times \text{Days}_k}{360 \times 10000} \right\rfloor$$
2. **Pajak Pemotongan (Tax Withheld PPh 23/26)**:
   $$\text{TaxWithheld}_k = \left\lfloor \frac{\text{InterestGross}_k \times T_{\text{wht}}}{10000} \right\rfloor$$
3. **Bunga Bersih (Net Interest Disbursed)**:
   $$\text{InterestNet}_k = \text{InterestGross}_k - \text{TaxWithheld}_k$$
4. **Total Kas Ditransfer ke Pemberi Pinjaman**:
   $$\text{CashDisbursed}_k = \text{PrincipalDue}_k + \text{InterestNet}_k$$
5. **Kas Disetor ke Kas Negara (PPh 23)**:
   $$\text{TaxRemittance}_k = \text{TaxWithheld}_k$$
6. **Saldo Pokok Akhir Periode**:
   $$B_{k+1} = B_k - \text{PrincipalDue}_k$$

---

## 3. Vektor Uji Algoritma

### Vektor 1: Pinjaman Rp 120.000.000, 12 Bulan, Bunga 6,00% + Spread 0,50% = 6,50% (650 bps), PPh 23 15% (1500 bps)
- $P = 120.000.000$, $N = 12$, $R_{\text{total}} = 650 \text{ bps}$, $T_{\text{wht}} = 1500 \text{ bps}$.
- Pokok per bulan: $\frac{120.000.000}{12} = 10.000.000$ IDR per bulan.
- Bulan 1 (Saldo Awal Rp 120.000.000, 30 hari):
  - $\text{InterestGross}_1 = \lfloor \frac{120.000.000 \times 650 \times 30}{3.600.000} \rfloor = 650.000$ IDR.
  - $\text{TaxWithheld}_1 = \lfloor \frac{650.000 \times 1500}{10000} \rfloor = 97.500$ IDR (PPh 23).
  - $\text{InterestNet}_1 = 650.000 - 97.500 = 552.500$ IDR.
  - $\text{CashDisbursed}_1 = 10.000.000 + 552.500 = 10.552.500$ IDR.
  - Sisa Saldo Pokok: Rp 110.000.000.
- Bulan 2 (Saldo Awal Rp 110.000.000, 30 hari):
  - $\text{InterestGross}_2 = \lfloor \frac{110.000.000 \times 650 \times 30}{3.600.000} \rfloor = 595.833$ IDR.
  - $\text{TaxWithheld}_2 = \lfloor \frac{595.833 \times 1500}{10000} \rfloor = 89.374$ IDR.
  - $\text{InterestNet}_2 = 595.833 - 89.374 = 506.459$ IDR.

### Vektor 2: Penyerapan Remainder Pokok Ganjil
- $P = 100.000.000$ IDR, $N = 3$ bulan.
- $\text{PrincipalBase} = \lfloor \frac{100.000.000}{3} \rfloor = 33.333.333$ IDR.
- Periode 1: Rp 33.333.333
- Periode 2: Rp 33.333.333
- Periode 3: $100.000.000 - (33.333.333 \times 2) = 33.333.334$ IDR (penyerapan 1 rupiah sisa).
- Invarian: Total Pokok = $33.333.333 + 33.333.333 + 33.333.334 = 100.000.000$ IDR (tepat 100%).

### Vektor 3: Pinjaman Luar Negeri PPh 26 (2000 bps / 20%)
- $P = 50.000.000$ IDR, $R_{\text{total}} = 800 \text{ bps}$ (8,00%), $T_{\text{wht}} = 2000 \text{ bps}$ (20,00%).
- Periode 30 hari:
  - $\text{InterestGross} = \lfloor \frac{50.000.000 \times 800 \times 30}{3.600.000} \rfloor = 333.333$ IDR.
  - $\text{TaxWithheld} = \lfloor \frac{333.333 \times 2000}{10000} \rfloor = 66.666$ IDR (PPh 26).
  - $\text{InterestNet} = 333.333 - 66.666 = 266.667$ IDR.

---

## 4. Sertifikat Hash SHA-256
Sertifikat `IntercompanyAmortizationCertificate` memuat:
- `loan_agreement_number`, `principal_minor`, `interest_rate_bps`, `spread_bps`, `wht_rate_bps`, `total_periods`
- `total_interest_gross_minor`, `total_tax_withheld_minor`, `total_interest_net_minor`
- Daftar seluruh baris skedul amortisasi
- `certificate_hash` = $\text{SHA-256}(\text{JSON canonical terurut})$
- Diverifikasi oleh `IntercompanyAmortizationVerifier`.
