# Panduan Operasional & Finansial Night Economics Venue (Fase 90)

## 1. Pendahuluan & Latar Belakang Domain
Operasional venue hiburan malam skala enterprise—meliputi beach club pesisir, kelab malam bertingkat (*multi-level nightclubs*), rooftop lounge, serta area festival berkapasitas ribuan pengunjung—memiliki karakteristik finansial yang unik dibandingkan dengan ritel atau perhotelan konvensional:
1. **Volatilitas Permintaan Terkonsentrasi:** Mayoritas pendapatan (hingga 85%) dihasilkan dalam rentang waktu yang sangat sempit, yakni 6 hingga 8 jam operasional malam (pukul 20.00 hingga 04.00 WITA).
2. **Ketergantungan pada Nilai Margin Minuman:** Kategori minuman keras beralkohol tinggi (spirit golongan C dan champagne vintage) menyumbang marjin laba kotor tertinggi (75%–85%), namun rentan terhadap penyusutan fisik (*spillage*), konsumsi staf yang tidak tercatat, dan regulasi kepabeanan yang ketat (PMK 160/2023).
3. **Struktur Kontrak Artis Berisiko Tinggi:** Mengontrak bakat bertaraf internasional (*headliner DJs*) memerlukan pembayaran uang muka (*advance*) dalam valuta asing, komitmen pemenuhan rider teknis dan perhotelan, pemotongan pajak penghasilan pasal 21/26, serta skema pembagian pendapatan pintu (*door split*) yang menuntut transparansi rekonsiliasi.

Panduan ini mendokumentasikan prosedur operasional standar (SOP), aturan bisnis, formula algoritma bersertifikat, dan integrasi buku besar yang diimplementasikan pada Fase 90 di bawah payung konsep arsitektur SuperWeb Gelombang 7.

---

## 2. Metrik Kinerja Meja VIP: Revenue Per Available Table-Hour (RevPAT)

### 2.1 Konsep & Formula Dasar
Metrik tradisional seperti Gross Revenue atau Total Covers tidak memadai untuk mengevaluasi efisiensi spasial dan temporal meja VIP. Modul Venue mengadopsi standar internasional industri perhotelan dan hiburan malam: **RevPAT (Revenue Per Available Table-hour)**.

$$\text{Total Table Revenue} = \text{Food Revenue} + \text{Beverage Revenue} + \text{Minimum Spend Shortfall}$$
$$\text{RevPAT} = \left\lfloor \frac{\text{Total Table Revenue}}{\text{Available Table Hours}} \right\rfloor$$

Di mana:
- **Available Table Hours:** Jumlah jam di mana meja siap dipesan selama periode operasional aktif (misalnya 8 jam per malam, mulai pukul 20.00 hingga 04.00).
- **Occupied Table Hours:** Durasi aktual meja diduduki oleh tamu pemegang reservasi.
- **Minimum Spend Shortfall:** Selisih lebih komitmen pengeluaran minimal meja terhadap realisasi tagihan F&B aktual tamu yang diakui sebagai pendapatan definitif venue sesuai aturan Fase 89 (D89-4).

### 2.2 Klasifikasi Zona & Bobot Ambang Minimum Spend
Setiap zona hiburan di dalam beach club atau kelab malam memiliki target RevPAT minimum yang disesuaikan dengan visibilitas panggung, kedekatan dengan bibir pantai, dan akses fasilitas privat:

| Kode Zona | Nama Zona Operasional | Kapasitas Meja | Minimum Spend Standar | Target RevPAT / Jam |
|---|---|---|---|---|
| `BF-CABANA` | Beachfront VIP Cabana | 10–15 Tamu | Rp 15.000.000 | Rp 1.875.000 |
| `POOL-DAYBED` | Sunset Pool Daybed | 4–6 Tamu | Rp 5.000.000 | Rp 625.000 |
| `DANCE-SOFA` | Dancefloor Frontline Sofa | 8–10 Tamu | Rp 10.000.000 | Rp 1.250.000 |
| `MEZZ-LOUNGE` | VIP Mezzanine Balcony | 6–8 Tamu | Rp 7.500.000 | Rp 937.500 |
| `INDOOR-BAR` | Indoor Club High-Table | 2–4 Tamu | Rp 2.500.000 | Rp 312.500 |

### 2.3 Mekanisme Adaptif Cuaca & Waktu Puncak (Surge Pricing)
1. **Faktor Cuaca Pesisir:** Bila probabilitas hujan melebihi 60% berdasarkan telemetri cuaca BMKG, sistem secara otomatis menurunkan komitmen minimum spend area terbuka (*daybed*) sebesar 20% dan menawarkan opsi relokasi ke *indoor lounge*.
2. **Faktor Jam Puncak (*Peak Hour Surge*):** Pada pukul 22.00 hingga 01.30, algoritma mengizinkan manajer venue menaikkan batas minimum spend meja yang masih kosong hingga +30% untuk menyaring tamu berdaya beli tertinggi.

---

## 3. Rantai Pasok Bar & Kepatuhan Bea Cukai Minuman Beralkohol (MMEA)

### 3.1 Regulasi Cukai Spesifik per Liter (PMK 160/2023)
Peredaran Minuman Mengandung Etil Alkohol (MMEA) impor di Indonesia diatur secara ketat oleh Peraturan Menteri Keuangan No. 160/PMK.010/2023. Modul Venue mengotomatiskan pencatatan harga pokok perolehan (*landed cost*) dengan memanggil kontrak aditif `Modules\Trade\Contracts\ImportLandedCostQuoter` (G7-K19):

- **Golongan A (Kadar Alkohol s/d 5%):** Dikenakan tarif cukai spesifik Rp 16.500 per liter. Contoh: bir impor, hard seltzer, dan cider.
- **Golongan B (Kadar Alkohol > 5% s/d 20%):** Dikenakan tarif cukai spesifik Rp 42.500 per liter. Contoh: wine, soju, sake, dan vermouth impor.
- **Golongan C (Kadar Alkohol > 20% s/d 55%):** Dikenakan tarif cukai spesifik Rp 150.000 per liter. Contoh: single malt whisky, premium gin, vodka, tequila, rum, dan cognac.

### 3.2 Alur Komputasi Landed Cost Impor Terpadu
Kalkulasi landed cost botol spirit impor dilakukan secara deterministik tanpa toleransi galat pembulatan:
1. **Nilai CIF (Cost, Insurance, and Freight):** Nilai faktur pembelian dalam IDR penuh.
2. **Bea Masuk (BM):** $\text{BM} = \lfloor (\text{CIF} \times \text{duty\_bps}) / 10.000 \rfloor$ (umumnya 5% atau 500 bps).
3. **Cukai MMEA Spesifik:** $\text{Cukai} = \text{Volume Liter} \times \text{Tarif PMK 160/2023}$.
4. **Nilai Impor:** $\text{Nilai Impor} = \text{CIF} + \text{BM} + \text{Cukai}$.
5. **Pajak Pertambahan Nilai Impor (PPN Impor):** $\text{PPN} = \lfloor (\text{Nilai Impor} \times 1.100) / 10.000 \rfloor$ (11%).
6. **Pajak Penghasilan Pasal 22 Impor (PPh 22):** $\text{PPh 22} = \lfloor (\text{Nilai Impor} \times 250) / 10.000 \rfloor$ (2,5% bagi pemilik Angka Pengenal Importir).
7. **Total Harga Pokok Perolehan (Landed Cost):** $\text{Total} = \text{CIF} + \text{BM} + \text{Cukai} + \text{PPN} + \text{PPh 22}$.

### 3.3 Prosedur Audit Varians Stok Antar-Shift (*Shift Reconciliation*)
Setiap pergantian shift bartender dan kasir bar (shift siang ke shift malam, dan shift malam ke closing) wajib menjalankan aksi audit via antarmuka `venue::bar.revpat`:
- **Formula Botol Ekspektasi:**
  $$\text{Expected Closing} = \text{Opening Bottles} - \text{POS Sales} - \left\lfloor \frac{\text{Spillage (ml)}}{\text{Unit Volume (ml)}} \right\rfloor$$
- **Deteksi Varians:**
  $$\text{Variance} = \text{Actual Physical Bottles} - \text{Expected Closing}$$
  - $\text{Variance} = 0$: Status shift ditutup sebagai `reconciled`.
  - $\text{Variance} \ne 0$: Status shift ditandai sebagai `audited_with_variance` dan mewajibkan catatan investigasi supervisor.
  - Kelebihan tumpahan (*excess spillage*) melebihi 500 ml memicu pemblokiran otomatis pembukaan segel botol cadangan di gudang bar hingga diotorisasi manajer.

---

## 4. Penyelesaian Kontrak Artis & Protokol Versus Deal (V5)

### 4.1 Logika Kesepakatan Versus (*Guarantee vs Door Split*)
Untuk headliner internasional, manajemen artis sering kali meminta jaminan honor minimal (*guarantee fee*) dengan klausul bonus persentase dari laba kotor penjualan pintu acara (*door split percentage*) mana yang menghasilkan nilai lebih tinggi (*whichever is greater*):

$$\text{Door Net} = \max(0, \text{Door Gross Sales} - \text{Approved Direct Expenses})$$
$$\text{Door Share} = \left\lfloor \frac{\text{Door Net} \times \text{Share Bps}}{10.000} \right\rfloor$$
$$\text{Gross Pay} = \begin{cases} \text{Guarantee IDR} & \text{jika } \text{Deal Type} = \text{'guarantee'} \\ \text{Door Share} & \text{jika } \text{Deal Type} = \text{'door\_split'} \\ \max(\text{Guarantee IDR}, \text{Door Share}) & \text{jika } \text{Deal Type} = \text{'versus'} \end{cases}$$

### 4.2 Pemotongan Pajak Penghasilan Pasal 21 & Pasal 26 (WHT)
- **Artis Wajib Pajak Dalam Negeri (WPDN):** Dikenakan PPh Pasal 21 atas imbalan tenaga ahli/seniman sebesar 50% $\times$ tarif Pasal 17 UU HPP, atau tarif efektif rata-rata (TER).
- **Artis Wajib Pajak Luar Negeri (WPLN):** Dikenakan PPh Pasal 26 sebesar 20% (2.000 bps) flat dari penghasilan bruto, kecuali jika melampirkan Surat Keterangan Domisili (Form DGT) yang sah di bawah perjanjian penghindaran pajak berganda (P3B/Tax Treaty).

### 4.3 Alur Buku Besar Akrual Double-Entry Artis
1. **Saat Uang Muka (Advance Paid) Dicairkan:**
   - Debit: `ven:artist_advance:IDR` (Aset Uang Muka)
   - Kredit: `ven:creator_payable:IDR` (Hutang Rekening Bank Artis)
   - Kredit: `ven:wht_payable:IDR` (Hutang PPh 21/26 bila pajak dipungut di muka)
2. **Saat Penampilan Selesai & Final Settlement Disetujui:**
   - Debit: `ven:artist_fee_expense:IDR` (Beban Operasional Acara)
   - Kredit: `ven:artist_advance:IDR` (Pembalikan Saldo Uang Muka)
   - Kredit: `ven:wht_payable:IDR` (Hutang PPh 21/26 atas sisa honor)
   - Kredit: `ven:creator_payable:IDR` (Hutang Bersih yang Ditransfer)

---

## 5. Ringkasan Kepatuhan & Checklist Penutupan Acara
Sebelum manajer venue menjalankan penutupan formal acara via `CloseEntertainmentEventAction`, seluruh item verifikasi berikut harus berstatus tervalidasi:
- [x] Seluruh tiket berstatus `used` atau `refunded` (tidak ada tiket yang menggantung dalam status sementara).
- [x] Audit shift seluruh stasiun bar telah disetujui (*reconciled* atau *variance explained*).
- [x] Kontrak artis berstatus `settled` dengan sertifikat HMAC `ArtistDoorSplitCertificate` yang sah.
- [x] Seluruh tagihan F&B meja VIP telah dilunasi atau deposit no-show telah dieksekusi.
- [x] Perintah konsol `php artisan venue:audit-economy` menghasilkan exit code 0 (`HEALTHY`).
