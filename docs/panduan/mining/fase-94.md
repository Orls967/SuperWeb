# Panduan Operasional Pertambangan Fase 94 (Standard Operating Procedures)

## 1. Ikhtisar Panduan
Dokumen ini merupakan panduan operasional standar (*Standard Operating Procedure / SOP*) untuk modul Pertambangan (*Mining*) Fase 94. Panduan ini dirancang untuk memandu seluruh pemangku kepentingan lapangan dan kantor operasional dalam menjalankan transaksi harian, pengendalian kadar, pemantauan K3, pembayaran royalti negara, dan eksekusi kontrak offtake.

---

## 2. Prosedur Operator Jembatan Timbang (Weighbridge Operator)

### 2.1 Akses Antarmuka Bergerak (Mobile Friendly 375px)
- **Rute Web**: `/mining/weighbridge`
- **Peran Pengguna**: `weighbridge_operator`, `site_manager`, `admin`
- Antarmuka dirancang khusus untuk layar sentuh terminal industri maupun perangkat genggam tablet di pos timbang pit keluar (*pit out-gate*) dan dermaga (*jetty in-gate*).

### 2.2 Alur Penimbangan Truk Angkut (Haul Truck Weighing Cycle)
1. **Penimbangan Truk Bermuatan (Gross Weighing)**:
   - Truk berhenti di atas platform timbangan jembatan timbang digital.
   - Operator memindai tag RFID truk atau memasukkan nomor lambung unit (misalnya `DT-CAT-777D-014`).
   - Sistem membaca data sensor timbangan secara otomatis.
   - Operator memilih komoditas (`COAL_RAW`, `COAL_CRUSHED`, atau `NICKEL_ORE_SAPROLITE`) dan stockpile tujuan.
   - Mengklik **Simpan Tiket Masuk**. Sistem menghasilkan tiket dengan `gross_weight_kg`.
2. **Penimbangan Truk Kosong (Tare Weighing)**:
   - Setelah menumpahkan muatan di crusher atau run-of-mine (ROM) stockpile, truk kembali melintasi timbangan keluar.
   - Operator memasukkan nomor tiket atau memindai barcode tiket timbang.
   - Sistem mencatat `tare_weight_kg` dan secara otomatis menghitung `net_weight_kg = gross_weight_kg - tare_weight_kg`.
   - Tiket dimeteraikan dengan tanda tangan hash kriptografis SHA-256 yang merujuk tiket sebelumnya.
3. **Penanganan Peringatan Anomali**:
   - Jika sistem menampilkan peringatan **"BERAT TARRA DI LUAR TOLERANSI (> 3.5%)"**: Operator wajib menginstruksikan pengemudi memeriksa adanya penumpukan lumpur pada sasis truk (*carryback dirt*). Bila bersih, truk diarahkan ke kalibrasi ulang.
   - Jika sistem menampilkan peringatan **"MUATAN MELEBIHI KAPASITAS DAYA ANGKUT (> 115% GVW)"**: Tiket tetap tercatat namun bertanda *Payload Violation* untuk evaluasi supervisor dispatch.

---

## 3. Prosedur Ahli Metalurgi & Pengendalian Kadar (Metallurgist / Grade Control)

### 3.1 Antarmuka Rekonsiliasi & Blending
- **Rute Web**: `/mining/grade`
- **Peran Pengguna**: `metallurgist`, `mine_manager`, `admin`

### 3.2 Optimasi Pencampuran Batubara / Bijih (Blending Run)
1. Masuk ke tab **Blending Optimization**.
2. Masukkan target tonase pengapalan (misal: $50.000\text{ ton}$).
3. Tentukan spesifikasi kontrak pembeli:
   - Target Kalori GAR (misal: $5.800\text{ kcal/kg}$).
   - Maksimum Abu (misal: $\le 8.5\%$).
   - Maksimum Sulfur (misal: $\le 0.8\%$).
4. Pilih daftar stockpile sumber yang tersedia dengan hasil uji lab terakhir.
5. Klik **Jalankan Optimasi Blending**.
6. Sistem memproses algoritma optimasi deterministik dan menampilkan resep pencampuran tonase dari tiap stockpile beserta proyeksi kualitas akhir campuran.

### 3.3 Rekonsiliasi Neraca Massa Pabrik Pengolahan (Mass Balance Run - M5)
1. Masuk ke tab **Metallurgical Mass Balance**.
2. Masukkan data periode harian atau mingguan dari pabrik reduksi / pencucian:
   - Tonase dan kadar bijih masuk (*Feed*).
   - Tonase dan kadar konsentrat/ferronikel dihasilkan (*Concentrate*).
   - Tonase dan kadar ampas (*Tailings*).
3. Klik **Hitung Rekonsiliasi Neraca Massa**.
4. Sistem mengeksekusi algoritma kuadrat terkecil berbobot varians `MassBalanceReconciler`, memeriksa uji Chi-Kuadrat, dan menerbitkan sertifikat neraca massa bersertifikat.
5. Bila status menunjukkan `ANOMALY_DETECTED` ($\chi^2 > \text{ambang batas}$), segera terbitkan perintah kalibrasi untuk timbangan ban berjalan (*belt conveyor scale*) unit bersangkutan.

---

## 4. Prosedur Petugas Royalti & Kepatuhan Pajak (Royalty Officer)

### 4.1 Antarmuka Royalti & Referensi Harga
- **Rute Web**: `/mining/royalty`
- **Peran Pengguna**: `royalty_officer`, `mine_manager`, `admin`

### 4.2 Verifikasi Tarif & Perhitungan Royalti Bulanan (PP 19/2025 - M4)
1. Periksa ketersediaan Harga Batubara Acuan (HBA) dan Harga Mineral Acuan (HMA) bulan berjalan pada tabel **Reference Prices**.
2. Masuk ke form **Kalkulasi Royalti Terverifikasi**:
   - Pilih konsesi / site tambang terkait.
   - Masukkan periode bulan buku (misal: `2026-11`).
   - Masukkan total tonase produksi dan penjualan yang telah divalidasi oleh ringkasan tiket timbang dan laporan draft survey.
   - Masukkan rata-rata kalori GAR terverifikasi lab.
3. Klik **Hitung & Terbitkan Surat Pemberitahuan Royalti**.
4. Sistem memanggil `CalculateVerifiedRoyaltyAction` yang mengeksekusi `RoyaltyTierResolver`, menetapkan tier regulasi resmi, menerbitkan sertifikat perhitungan, dan membuat draft dokumen pengakuan utang royalti pada buku besar keuangan (`min:royalty_payable`).
5. Unduh ikhtisar perhitungan berformat PDF/CSV untuk lampiran pelaporan resmi e-PNBP Kementerian ESDM.

---

## 5. Prosedur Pengawas K3 & Lingkungan Hidup (HSE Officer)

### 5.1 Antarmuka K3 & Izin Kerja Lapangan
- **Rute Web**: `/mining/hse`
- **Peran Pengguna**: `hse_officer`, `mine_manager`, `admin`

### 5.2 Penerbitan Izin Kerja Berisiko Tinggi (Permit to Work / PTW)
1. Masuk ke form **Penerbitan Izin Kerja K3**.
2. Pilih jenis aktivitas berisiko:
   - `BLASTING`: Pekerjaan peledakan dan penanganan bahan peledak.
   - `CONFINED_SPACE`: Pekerjaan ruang terbatas (dalam silo, tangki, atau terowongan).
   - `WORKING_AT_HEIGHTS`: Pekerjaan pada ketinggian $\ge 1.8\text{ meter}$.
   - `HOT_WORK`: Pengelasan dan pemotongan dengan api terbuka.
   - `HIGH_VOLTAGE`: Pemeliharaan transformator dan gardu induk tambang.
3. Isi ID pekerja penanggung jawab, nama pengawas bersertifikat K3 Pengawas Operasional Pertama (POP), zona geofence kerja, tingkat risiko, dan masa berlaku izin (maksimum 24 jam).
4. Klik **Terbitkan Izin Kerja**. Sistem menerbitkan nomor izin resmi bertanda unik `PTW-YYYYMM-XXXX`.

### 5.3 Evaluasi Getaran Peledakan (SNI 7571:2010)
1. Sebelum peledakan dimulai, tim juru ledak (*blaster*) memasukkan parameter rencana peledakan:
   - Jarak terdekat ke pemukiman warga atau struktur infrastruktur (dalam meter).
   - Muatan bahan peledak per delay tunda (*charge per delay* dalam kg).
   - Kelas struktur terdampak (`RESIDENTIAL` atau `INDUSTRIAL`).
2. Klik **Evaluasi Batas Getaran & Airblast**.
3. Sistem menghitung proyeksi PPV dan Airblast. Jika nilai melampaui $3.0\text{ mm/s}$ atau $120\text{ dBL}$, sistem menolak izin peledakan dan merekomendasikan reduksi beban per delay atau penambahan waktu tunda elektronik (*electronic detonator delay*).

---

## 6. Prosedur Pembeli Mandiri Offtaker (Offtaker Self-Service Portal)

### 6.1 Akses Portal Pembeli
- **Rute Web**: `/offtaker/portal`
- **Peran Pengguna**: `offtaker`, `admin`
- Pembeli terotentikasi diisolasi secara ketat sehingga hanya dapat melihat kontrak dan pengapalan yang sah dialokasikan untuk perusahaannya.

### 6.2 Alur Nominasi Kapal & Penyelesaian Dokumen Pengapalan
1. **Nominasi Kapal / Tongkang**:
   - Pembeli memilih kontrak offtake aktif.
   - Mengisi nama kapal/tongkang (*Vessel/Barge Name*), estimasi waktu tiba (*ETA*), dan tonase nominasi.
   - Mengunggah referensi *Letter of Credit* (LC) yang diterbitkan bank mitra.
   - Mengklik **Ajukan Nominasi Kapal**.
2. **Pemantauan Pemuatan & Pengunduhan Dokumen**:
   - Pembeli dapat memantau progres pemuatan kargo (*Loading Progress*), hasil *Draft Survey*, dan sertifikat analisis lab muat (*Certificate of Sampling and Analysis*).
   - Setelah kapal berlayar, pembeli dapat mengunduh faktur sementara (*Provisional Invoice*).
3. **Penyelesaian Final (Final Settlement)**:
   - Setelah kargo tiba di pelabuhan bongkar dan uji independen selesai, pembeli mengunggah hasil analisis kadar pelabuhan bongkar (*Discharge Assay*).
   - Sistem memicu rekonsiliasi otomatis (`SettleFinalAssayOfftakeInvoiceAction`) untuk menerbitkan faktur penyelesaian final dengan penyesuaian penalti/bonus komersial.


---

## 7. Prosedur Pelaporan Kepatuhan & Ekspor Data Regulasi (Compliance Export)

### 7.1 Ekspor Data Kepatuhan Format CSV
Untuk memenuhi kewajiban pelaporan berkala ke portal MODI (*Minerba One Data Indonesia*) dan MOMS (*Minerba One Monitoring System*), sistem menyediakan perintah konsol dan antarmuka web ekspor kepatuhan:
- **Perintah Konsol**:
  ```bash
  php artisan mining:export-compliance --site="SITE-COAL-MAHAKAM-01" --month="2026-11" --type="royalty,hse,production"
  ```
- **Struktur Berkas CSV yang Dihasilkan**:
  1. `mining_production_summary_YYYYMM.csv`: Memuat agregat tonase masuk dan keluar, kadar rata-rata GAR/Ni, dan identitas stockpile asal.
  2. `mining_royalty_pnbp_YYYYMM.csv`: Memuat nomor ketetapan royalti, tarif bps efektif, dasar pengenaan pajak, dan kode NTPN (*Nomor Transaksi Penerimaan Negara*).
  3. `mining_hse_incident_and_permits_YYYYMM.csv`: Memuat statistik jam kerja selamat (*Safe Man-Hours*), daftar izin kerja PTW berisiko tinggi aktif, dan status investigasi near-miss.

### 7.2 Verifikasi Kepatuhan Perizinan Konsesi Tambang
Petugas kepatuhan (*Compliance Officer*) secara rutin menjalankan pemindaian masa berlaku izin operasional:
- Masuk ke `/mining/hse` dan klik tombol **Audit Izin Konsesi**.
- Sistem memeriksa status legalitas seluruh izin: IUP Operasi Produksi, Izin Pinjam Pakai Kawasan Hutan (PPKH), Persetujuan Lingkungan (AMDAL), dan Rencana Kerja & Anggaran Biaya (RKAB) tahunan.
- Izin yang tersisa $< 90\text{ hari}$ masa berlakunya diberi tanda peringatan kuning, sedangkan izin yang telah kedaluwarsa secara otomatis membekukan penerbitan tiket timbang baru dan izin kerja lapangan terkait.

---

## 8. Pemantauan Emisi Gas Rumah Kaca & ESG Tambang (Scope 1 & Scope 2)

### 8.1 Metodologi Perhitungan Emisi Karbon
Sistem mengadopsi standar *Greenhouse Gas Protocol (GHG Protocol)* dan pedoman teknis Kementerian ESDM:
- **Emisi Cakupan 1 (Scope 1 - Direct Emissions)**:
  - Pembakaran solar alat berat (excavator, haul truck, dozer): $E_{\text{diesel}} = \text{Volume Solar (Liter)} \times \text{Faktor Emisi } (2.68\text{ kg CO}_2\text{e/L})$.
  - Peledakan bahan peledak ANFO: $E_{\text{blast}} = \text{Tonase ANFO} \times 0.17\text{ ton CO}_2\text{e/ton}$.
  - Emisi gas metana fugitive dari lapisan batubara (*coal seam methane*): dihitung dari data sensor gas terintegrasi.
- **Emisi Cakupan 2 (Scope 2 - Indirect Emissions)**:
  - Konsumsi energi listrik dari jaringan PLN untuk fasilitas pengolahan/smelter: $E_{\text{grid}} = \text{kWh Terpakai} \times \text{Faktor Emisi Grid Jamali/Kaltim } (0.78\text{ kg CO}_2\text{e/kWh})$.

### 8.2 Pelaporan Intensitas Emisi Karbon
Setiap akhir bulan, sistem menghitung rasio intensitas emisi:

$$\text{Intensitas Karbon} = \frac{\text{Total Emisi Scope 1 + Scope 2 (ton CO}_2\text{e)}}{\text{Total Tonase Batubara / Bijih Tertambang (ton)}}$$

Nilai ini digunakan sebagai dasar klaim sertifikat pengurangan emisi (*Carbon Credits*) atau pemenuhan taksonomi hijau OJK.

---

## 9. Integrasi Ekosistem Lintas Modul (Enterprise Architecture)

Modul Pertambangan Fase 94 terhubung secara harmonis dengan modul-modul lain dalam platform ERP:
1. **Modul Keuangan & Buku Besar (`Finance / Ledger`)**:
   - Seluruh mutasi persediaan, timbulnya kewajiban royalti, pengakuan piutang dagang, dan penyusutan provisi reklamasi dibukukan secara *real-time* ke sistem akuntansi double-entry.
2. **Modul Pengadaan & EPC (`Epc`)**:
   - Jadwal reklamasi pascatambang yang diterbitkan oleh `ScheduleMineReclamationAction` secara otomatis memicu pembuatan draft proyek reklamasi di modul EPC melalui kontrak `ProjectDraftFactory`.
3. **Modul Perdagangan Internasional (`TradeFinance`)**:
   - Kontrak penjualan ekspor mineral/batubara mengandalkan layanan `OfftakeLetterOfCreditGatewayService` untuk verifikasi Letter of Credit, memastikan keamanan penagihan devisa hasil ekspor (DHE).
4. **Modul Manufaktur & Pengolahan (`Manufacturing`)**:
   - Siklus hilirisasi bijih nikel menjadi ferronikel/NPI mencatat transfer material umpan dan hasil olahan melalui antarmuka `ProcessOutputRecorder`.

---

## 10. Prosedur Kalibrasi Berkala & Pemeliharaan Sensor Lapangan

Untuk memastikan keandalan data operasional dan mencegah perselisihan komersial:
1. **Kalibrasi Jembatan Timbang Digital**:
   - Setiap 30 hari kalender, jembatan timbang wajib menjalani uji tara dan beban menggunakan unit *Test Truck* bersertifikat Direktorat Metrologi Kementerian Perdagangan.
   - Hasil kalibrasi dicatat melalui form `/mining/weighbridge/calibration` yang menerbitkan stempel kalibrasi resmi dan memperbarui koefisien sensitivitas sel beban.
2. **Uji Banding Laboratorium Uji (Check Assay Round Robin)**:
   - Setiap 100 sampel pengapalan, satu sampel cadangan (*split sample*) dikirimkan ke laboratorium independen terakreditasi ISO/IEC 17025.
   - Perbedaan hasil uji dianalisis menggunakan aksi `EvaluateAssayLabBiasAction`. Jika bias sistematis melebihi batas $2.5\%$, sistem memicu kalibrasi instrumen spektrometri laboratorium site.
