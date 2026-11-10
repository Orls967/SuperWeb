# Arsitektur & Kepatuhan Pajak Pemotongan Intercompany (PPh Pasal 23 & 26)

## 1. Landasan Yuridis Perpajakan Indonesia
Dalam transaksi antar-perusahaan afiliasi di Indonesia, pemenuhan kewajiban pemotongan pajak penghasilan (*withholding tax*) diatur secara tegas dalam Undang-Undang Nomor 7 Tahun 1983 tentang Pajak Penghasilan sebagaimana telah diubah terakhir dengan Undang-Undang Nomor 7 Tahun 2021 tentang Harmonisasi Peraturan Perpajakan (UU HPP):

### A. Pajak Penghasilan Pasal 23 (Transaksi Afiliasi Domestik)
1. **Objek Bunga Pinjaman**:
   - Penghasilan bunga pinjaman antar-badan usaha dalam negeri dikenakan pemotongan PPh Pasal 23 sebesar $15\%$ dari jumlah bruto bunga (Pasal 23 ayat 1 huruf a angka 2).
   - Saat Terutang: Pada akhir bulan dibayarkannya bunga, atau pada saat jatuh tempo pembayaran sesuai perjanjian pinjaman (*whichever is earlier*).
   - Penyetoran: Paling lambat tanggal 10 bulan berikutnya ke kas negara melalui Surat Setoran Pajak (SSP / Kode Billing).
   - Pelaporan: Paling lambat tanggal 20 bulan berikutnya melalui SPT Masa Unifikasi PPh.
2. **Objek Dividen Antar-Badan Usaha**:
   - Sesuai UU Cipta Kerja dan UU HPP Pasal 4 ayat (3) huruf f, dividen yang diterima atau diperoleh perseroan terbatas sebagai Wajib Pajak dalam negeri dari penyertaan modal pada badan usaha di Indonesia dikecualikan dari objek pajak penghasilan (Non-Objek PPh 23) tanpa syarat persentase kepemilikan minimal.
3. **Objek Imbalan Jasa Manajemen / Jasa Teknik (Cost Sharing)**:
   - Dikenakan pemotongan PPh Pasal 23 sebesar $2\%$ dari jumlah bruto imbalan jasa manajemen (tidak termasuk PPN).

### B. Pajak Penghasilan Pasal 26 (Transaksi Afiliasi Lintas Negara)
1. **Tarif Umum**:
   - Sebesar $20\%$ dari jumlah bruto penghasilan bunga atau dividen yang dibayarkan kepada Wajib Pajak Luar Negeri (WPLN) selain Bentuk Usaha Tetap (BUT) di Indonesia.
2. **Tarif Perjanjian Penghindaran Pajak Berganda (P3B / Tax Treaty)**:
   - Jika kreditur berkedudukan di yurisdiksi yang memiliki P3B aktif dengan Indonesia (misalnya Singapura, Malaysia, Jepang), tarif pemotongan bunga dapat diturunkan (misalnya $10\%$ untuk P3B Indonesia-Singapura), dengan syarat WPLN dapat menyerahkan Surat Keterangan Domisili (*Certificate of Residence* / Form DGT-1) yang masih berlaku dan memenuhi uji *beneficial owner*.

---

## 2. Matriks Akuntansi Pemotongan Pajak Bunga Pinjaman Intercompany
Setiap transaksi bunga pinjaman antar-entitas dicatat secara simetris di kedua buku entitas dengan single-sign convention:

```
[Buku Entitas Debitur / Peminjam]
Debit  : ic:interest_expense:{lender}:{borrower}:{ccy}  (Nilai Bunga Bruto 100%)
Kredit : ic:interest_payable:{lender}:{borrower}:{ccy}  (Nilai Bunga Bersih 85%)
Kredit : ic:withholding_tax_payable:{borrower}:{ccy}    (Kewajiban Setor PPh 23 15%)

[Buku Entitas Kreditur / Pemberi Pinjaman]
Debit  : ic:interest_receivable:{borrower}:{lender}:{ccy} (Hak Tagih Bunga Bersih 85%)
Debit  : tax:prepaid_pph23:{lender}:{ccy}                 (Uang Muka PPh 23 15%)
Kredit : ic:interest_income:{borrower}:{lender}:{ccy}     (Pendapatan Bunga Bruto 100%)
```

---

## 3. Formulasi Aritmetika Integer Tanpa Floating Point
Untuk mencegah deviasi desimal pada perhitungan pajak pemotongan:
- Seluruh tarif pajak dinyatakan dalam basis points integer ($T_{\text{wht}}$):
  - PPh 23 Bunga: $1.500 \text{ bps}$ ($15,00\%$).
  - PPh 23 Jasa Manajemen: $200 \text{ bps}$ ($2,00\%$).
  - PPh 26 Bunga Non-Treaty: $2.000 \text{ bps}$ ($20,00\%$).
  - PPh 26 Bunga Treaty Singapura: $1.000 \text{ bps}$ ($10,00\%$).
- Rumus Pembulatan Menjauhi Nol Simetris:
  $$\text{TaxWithheld} = \left\lfloor \frac{2 \times (\text{GrossInterest} \times T_{\text{wht}}) + 10000}{20000} \right\rfloor$$
  $$\text{NetDisbursement} = \text{GrossInterest} - \text{TaxWithheld}$$
- Invarian Pajak:
  $$\text{NetDisbursement} + \text{TaxWithheld} = \text{GrossInterest}$$
  Selisih $\Delta = 0$ di setiap baris voucher jurnal.

---

## 4. Bukti Potong Pajak & Penomoran Dokumen Gapless
Sistem menerbitkan Bukti Pemotongan Pajak Unifikasi Elektronik (simulasi e-Bupot) dengan format penomoran deterministik:
$$\text{BUPOT/IC/}\{YYYYMM\}/\{\text{SeqNumber}\}$$
Nomor ini dikaitkan langsung dengan baris jadwal pinjaman `ic_loan_schedules` dan transaksi ledger `bank_ledger_transactions` untuk menjamin jejak audit tak terbantahkan saat pemeriksaan kantor pelayanan pajak (KPP).
