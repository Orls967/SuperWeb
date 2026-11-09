# Analisis Umur Piutang Klaim (AR Aging) & Integrasi Akuntansi Rumah Sakit

## 1. Arsitektur Chart of Accounts Rumah Sakit (HospitalAccounts)

Pencatatan akuntansi rumah sakit terikat pada standar Pelaporan Keuangan Rumah Sakit (PSAK 71 / IFRS 9 tentang Instrumen Keuangan serta Standar Akuntansi Keuangan Entitas Rumah Sakit). Seluruh transaksi keuangan penagihan ditransformasikan menjadi entri jurnal pembukuan berpasangan (*double-entry*) menggunakan 27 kode akun resmi yang didefinisikan pada kelas `HospitalAccounts`.

### Struktur 27 Akun Resmi Buku Besar Rumah Sakit
| Kode Akun | Nama Akun Buku Besar (*Account Title*) | Kelompok Laporan | Saldo Normal | Fungsi Transaksional |
| :--- | :--- | :--- | :--- | :--- |
| **1010** | Kas Kecil Operasional Kasir | Aset Lancar | Debit | Penerimaan pembayaran tunai langsung di kasir poliklinik / IGD |
| **1020** | Bank Operasional Utama RS | Aset Lancar | Debit | Rekening operasional utama penerimaan transfer pasien umum & korporasi |
| **1030** | Bank Rekening Penampung BPJS | Aset Lancar | Debit | Rekening giro penerimaan transfer klaim INA-CBG dari kantor cabang BPJS |
| **1110** | Piutang Pelayanan Pasien Umum | Aset Lancar | Debit | Tagihan berjalan pasien mandiri sebelum penerbitan faktur / pelunasan |
| **1120** | Piutang Klaim BPJS Kesehatan | Aset Lancar | Debit | Piutang klaim INA-CBG yang telah disetujui verifikator BPJS (E-Klaim) |
| **1130** | Piutang Asuransi Swasta / TPA | Aset Lancar | Debit | Piutang klaim ekses / jaminan nontunai asuransi komersial (G7-K31) |
| **1140** | Piutang Kerjasama Korporat | Aset Lancar | Debit | Piutang tagihan berkala perusahaan rekanan (Medical Check Up / Inpatient) |
| **1190** | Cadangan Kerugian Piutang Ragu | Kontra-Aset | Kredit | Akun pengurang estimasi kerugian piutang tak tertagih (ECL Reserve) |
| **1210** | Persediaan Obat Farmasi | Aset Lancar | Debit | Nilai perolehan stok obat paten & generik di gudang instalasi farmasi |
| **1220** | Persediaan Bahan Medis Habis Pakai | Aset Lancar | Debit | Nilai stok spuit, kateter, kassa, infus, implan bedah, APD |
| **1230** | Persediaan Reagen Laboratorium | Aset Lancar | Debit | Reagen kimia darah, biakan kultur, pewarna patologi anatomi |
| **1240** | Persediaan Darah Bank Darah | Aset Lancar | Debit | Biaya Pengolahan Pelayanan Darah (BPPD) kantung darah PMI di BDRS |
| **2010** | Hutang Usaha Pedagang Besar Farmasi | Kewajiban Lancar | Kredit | Kewajiban pembayaran tempo kepada distributor obat / PBF |
| **2020** | Titipan Uang Muka Pasien (Escrow) | Kewajiban Lancar | Kredit | Deposit awal saat admisi rawat inap atau pemesanan kamar operasi |
| **2030** | Pendapatan Diterima di Muka | Kewajiban Lancar | Kredit | Pembayaran paket medical check up atau paket persalinan di muka |
| **3010** | Modal Ekuitas Rumah Sakit | Ekuitas | Kredit | Modal disetor yayasan / pemegang saham perseroan rumah sakit |
| **3020** | Saldo Laba Ditahan (*Retained Earnings*) | Ekuitas | Kredit | Akumulasi surplus laba operasional tahun-tahun sebelumnya |
| **4010** | Pendapatan Jasa Rawat Jalan | Pendapatan Operasional | Kredit | Pendapatan jasa konsultasi poliklinik spesialis dan subspesialis |
| **4020** | Pendapatan Akomodasi Rawat Inap & ICU | Pendapatan Operasional | Kredit | Pendapatan sewa kamar rawat inap, ruang isolasi, dan tempat tidur ICU |
| **4030** | Pendapatan Tindakan Bedah & Anestesi | Pendapatan Operasional | Kredit | Jasa medis tindakan operasi di instalasi bedah sentral (IBS) |
| **4040** | Pendapatan Penjualan Farmasi Obat | Pendapatan Operasional | Kredit | Pendapatan penyerahan resep obat farmasi rawat jalan & rawat inap |
| **4050** | Pendapatan Layanan Diagnostik Penunjang | Pendapatan Operasional | Kredit | Pendapatan pemeriksaan laboratorium patologi, radiologi CT/MRI |
| **4110** | Diskon & Potongan Kemitraan Asuransi | Kontra-Pendapatan | Debit | Selisih potongan tarif kontrak kemitraan asuransi & dispute settlement |
| **4120** | Subsidi Pasien Tidak Mampu (Charity) | Kontra-Pendapatan | Debit | Alokasi pembiayaan cuma-cuma pasien dhuafa / duka cita keluarga prasejahtera |
| **5010** | Beban Pokok Obat & Alkes Terjual | Beban Pokok | Debit | Harga Pokok Penjualan (HPP) obat dan alkes yang terpakai pada pasien |
| **5020** | Beban Pengolahan Limbah Medis B3 | Beban Operasional | Debit | Biaya jasa pemusnahan limbah medis pihak ketiga transporter/insinerator |
| **5030** | Beban Penyisihan Kerugian Piutang | Beban Operasional | Debit | Beban pembentukan cadangan kerugian piutang tak tertagih tahun berjalan |

---

## 2. Siklus Jurnal Penagihan & Koordinasi Manfaat (O2C Journal Flows)

Layanan `HospitalAccountsPostingService` mengotomatisasi posting buku besar untuk setiap tahapan siklus tagihan:

### Tahap 1: Pembentukan Tagihan Tindakan di Kamar Operasi
```
(Dr) 1110 Piutang Pelayanan Pasien Umum        Rp 25.000.000
    (Cr) 4020 Pendapatan Sewa Kamar ICU           Rp  5.000.000
    (Cr) 4030 Pendapatan Tindakan Bedah & Anestesi Rp 12.000.000
    (Cr) 4040 Pendapatan Penjualan Farmasi Obat   Rp  6.000.000
    (Cr) 4050 Pendapatan Penunjang Lab/Radiologi  Rp  2.000.000
```

### Tahap 2: Pengakuan Pemakaian Persediaan Obat (HPP Farmasi)
```
(Dr) 5010 Beban Pokok Obat & Alkes Terjual     Rp  4.200.000
    (Cr) 1210 Persediaan Obat Farmasi              Rp  3.500.000
    (Cr) 1220 Persediaan Bahan Medis Habis Pakai  Rp    700.000
```

### Tahap 3: Rekonsiliasi Waterfall Koordinasi Manfaat (CoB)
Tagihan bruto Rp 25.000.000 diselesaikan melalui kombinasi penjamin:
- BPJS Kesehatan INA-CBG menanggung Rp 15.000.000.
- Asuransi Swasta Prudential (G7-K31) menanggung ekses Rp 7.000.000.
- Deposit pasien yang telah disetor di awal dipotong Rp 2.000.000.
- Dana CSR Charity RS memberikan subsidi sisa Rp 1.000.000.
```
(Dr) 1120 Piutang Klaim BPJS Kesehatan        Rp 15.000.000
(Dr) 1130 Piutang Asuransi Swasta / TPA       Rp  7.000.000
(Dr) 2020 Titipan Uang Muka Pasien (Escrow)   Rp  2.000.000
(Dr) 4120 Subsidi Pasien Dhuafa (Charity)     Rp  1.000.000
    (Cr) 1110 Piutang Pelayanan Pasien Umum        Rp 25.000.000
```

### Tahap 4: Pelunasan Kas dari Bank BPJS dengan Dispute Penyesuaian Tarif
BPJS mentransfer klaim sebesar Rp 14.700.000 (terdapat penyesuaian koding Rp 300.000):
```
(Dr) 1030 Bank Rekening Penampung BPJS        Rp 14.700.000
(Dr) 4110 Diskon & Potongan Adjudikasi        Rp    300.000
    (Cr) 1120 Piutang Klaim BPJS Kesehatan         Rp 15.000.000
```

---

## 3. Analisis Umur Piutang Klaim (Aging Buckets) & Expected Credit Loss (ECL)

Layanan `ClaimAgingAnalysisService` mengelompokkan portofolio piutang klaim ke dalam 4 ember umur penagihan (*aging buckets*) untuk menghitung cadangan penurunan nilai piutang sesuai standar PSAK 71:

### Matriks Provisi Cadangan Kerugian Piutang
| Bucket Umur Piutang | Rentang Keterlambatan | Probabilitas Gagal Bayar (*Default Rate*) | Prosedur Tindak Lanjut Finansial |
| :--- | :--- | :--- | :--- |
| **Current Bucket** | $0 \text{ s/d } 30 \text{ hari}$ | $1.0\%$ | Pengajuan berkas digital lengkap melalui sistem E-Klaim BPJS |
| **Aging Bucket 1** | $31 \text{ s/d } 60 \text{ hari}$ | $5.0\%$ | Penerbitan Surat Peringatan (SP-1) dan konfirmasi rekonsiliasi TPA |
| **Aging Bucket 2** | $61 \text{ s/d } 90 \text{ hari}$ | $15.0\%$ | Sidang mediasi berkas pending klaim bersama Tim TKMKB Kemenkes |
| **Aging Bucket 3** | $> 90 \text{ hari}$ | $50.0\%$ | Eskalasi somasi bagian hukum RS / pertimbangan usulan *write-off* |

Formula Perhitungan Cadangan Kerugian Piutang (ECL Provision):
$$\text{Total Impairment Provision} = \sum_{k=1}^{4} \Big( \text{Outstanding Balance}_k \times \text{ECL Rate}_k \Big)$$

---

## 4. Indikator Kinerja Utama Revenue Cycle Management (RCM KPI)

Layanan `HospitalBillingVerticalAuditService` dan perintah konsol `php artisan hsp:o2c-kpi` mengukur kesehatan finansial siklus penagihan rumah sakit:

1. **Days Sales Outstanding (DSO)**:
   $$\text{DSO} = \left( \frac{\text{Total Piutang Berjalan (AR)}}{\text{Total Pendapatan Bruto Tahunan}} \right) \times 365$$
   - Ambang Sehat: $\le 45 \text{ hari}$. Di atas 60 hari mengindikasikan hambatan serius pada proses pengajuan berkas klaim BPJS.
2. **Clean Claim Rate (CCR)**:
   $$\text{CCR} = \left( \frac{\text{Klaim Disetujui Pengajuan Pertama}}{\text{Total Seluruh Klaim yang Diajukan}} \right) \times 100\%$$
   - Ambang Target: $\ge 90.0\%$. Tingkat di bawah $85\%$ menunjukkan perlunya pelatihan ulang koder medis terkait kaidah ICD-10/ICD-9-CM.
3. **Initial Denial Rate (IDR)**:
   $$\text{IDR} = \left( \frac{\text{Klaim Ditolak / Dipending Verifikator}}{\text{Total Seluruh Klaim yang Diajukan}} \right) \times 100\%$$
   - Ambang Sehat: $\le 5.0\%$.
4. **Net Collection Ratio (NCR)**:
   $$\text{NCR} = \left( \frac{\text{Total Kas Bersih Diterima}}{\text{Gross Revenue} - \text{Diskon Kontraktual Disetujui}} \right) \times 100\%$$
   - Ambang Sehat: $\ge 92.0\%$.
