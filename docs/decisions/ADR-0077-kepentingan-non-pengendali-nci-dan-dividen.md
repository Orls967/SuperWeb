# ADR-0077: Pengakuan Kepentingan Non-Pengendali (NCI) & Dividen Minoritas

## Status
Diterima (Accepted)

## Konteks
Pada anak perusahaan yang tidak dimiliki 100% oleh induk (misalnya entitas patungan dengan kepemilikan 80% induk dan 20% pemegang saham pihak ketiga eksternal), porsi laba bersih dan ekuitas milik pihak ketiga harus diidentifikasi secara terpisah sebagai Kepentingan Non-Pengendali (*Non-Controlling Interest* - NCI) sesuai PSAK 65 (IFRS 10).

Pada sistem lama, perhitungan NCI hanya dilakukan secara terisolasi tanpa pembagian dividen minoritas, tanpa pemotongan pajak dividen, dan tanpa pencatatan ke buku besar ekuitas minoritas.

## Keputusan
1. Menyimpan data entitas anak dan proporsi kepemilikan NCI dalam permil integer (`nci_ownership_permille`, mis. 20000 untuk 20.00%) pada tabel `ic_subsidiary_nci`.
2. Menghitung alokasi laba bersih periodik anak perusahaan:
   $$\text{NciProfitShare} = \left\lfloor \frac{\text{SubsidiaryNetIncome} \times \text{NciOwnershipPermille}}{100000} \right\rfloor$$
   $$\text{ParentProfitShare} = \text{SubsidiaryNetIncome} - \text{NciProfitShare}$$
3. Memposting alokasi laba ke `LedgerService`:
   - Debit `ic:nci_profit_share:{anak}:IDR` = $-\text{NciProfitShare}$ (pengurang laba konsolidasi diatribusikan ke induk)
   - Kredit `ic:nci_equity:{anak}:IDR` = $+\text{NciProfitShare}$ (penambah ekuitas kepentingan non-pengendali di neraca konsolidasi)
4. Pengelolaan Dividen Minoritas (`ic_nci_dividends`):
   - Ketika anak perusahaan mendeklarasikan dividen kas, porsi dividen NCI dibayarkan langsung ke pihak ketiga:
     $$\text{NciDividend} = \left\lfloor \frac{\text{TotalDividendDeclared} \times \text{NciOwnershipPermille}}{100000} \right\rfloor$$
   - Posting pembayaran dividen NCI:
     - Debit `ic:nci_equity:{anak}:IDR` = $-\text{NciDividend}$ (mengurangi ekuitas minoritas)
     - Kredit `trs:bank_clearing:{anak}:IDR` = $+\text{NciDividend}$ (kas keluar dari rekening anak perusahaan)

## Konsekuensi
- Laporan laba rugi konsolidasi secara transparan memisahkan laba yang dapat diatribusikan kepada pemilik entitas induk vs kepentingan non-pengendali.
- Arus kas dividen keluar kepada investor minoritas tercatat dengan akurat dan tertelusuri.

