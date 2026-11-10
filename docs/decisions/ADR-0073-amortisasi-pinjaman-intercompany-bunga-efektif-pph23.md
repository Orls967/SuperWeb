# ADR-0073: Amortisasi Pinjaman Intercompany Bunga Efektif & Pemotongan Pajak PPh 23/26

## Status
Diterima (Accepted)

## Konteks
Pinjaman antar-perusahaan (*intercompany loan*) dalam konglomerasi sering digunakan untuk menyalurkan likuiditas surplus ke entitas anak yang membutuhkan modal kerja. Sesuai ketentuan perpajakan:
1. Suku bunga pinjaman wajib mencerminkan tingkat bunga pasar wajar (*arm's length interest rate*), biasanya berupa suku bunga dasar (mis. BI-Rate / SOFR) ditambah spread risiko kredit yang wajar.
2. Atas penghasilan bunga yang dibayarkan, entitas peminjam (debitur) wajib memotong Pajak Penghasilan (PPh) Pasal 23 sebesar 15% untuk kreditur domestik atau PPh Pasal 26 sebesar 20% (atau tarif tax treaty/P3B) untuk kreditur luar negeri.
3. Pada sistem lama, bunga pinjaman disimpan sebagai float sederhana tanpa skedul amortisasi periodik, tanpa pemotongan pajak otomatis, dan tanpa posting jurnal ke buku besar.

## Keputusan
1. Mengimplementasikan algoritma bersertifikat `IntercompanyAmortizationScheduler` (D6):
   - Menghitung jadwal amortisasi periodik bulanan/triwulanan secara deterministik.
   - Suku bunga dan spread dinyatakan dalam basis points integer (`interest_rate_bps`, `spread_bps`).
   - Rumus akrual bunga:
     $$\text{InterestGross} = \left\lfloor \frac{\text{Principal} \times (\text{RateBps} + \text{SpreadBps}) \times \text{Days}}{360 \times 10000} \right\rfloor$$
   - Rumus potongan pajak PPh 23/26:
     $$\text{TaxWithheld} = \left\lfloor \frac{\text{InterestGross} \times \text{WithholdingTaxBps}}{10000} \right\rfloor$$
     $$\text{InterestNet} = \text{InterestGross} - \text{TaxWithheld}$$
2. Menghasilkan baris jadwal amortisasi terperinci pada tabel `ic_loan_schedules`.
3. Memposting jurnal akrual dan pelunasan pinjaman secara simetris di kedua entitas ke `LedgerService`, termasuk membukukan kewajiban setor pajak pemotongan pada akun `ic:withholding_tax_payable:{peminjam}:{ccy}` dan uang muka pajak pada `tax:prepaid_pph23:{pemberi}:{ccy}`.

## Konsekuensi
- Entitas terhindar dari sanksi administrasi keterlambatan pemotongan dan penyetoran PPh 23/26.
- Likuiditas kas neto yang berpindah antar-rekening bank tercatat persis sesuai nilai neto setelah pajak.
- Skedul amortisasi dapat diekspor langsung sebagai lampiran dokumen SPT Masa Pajak.

