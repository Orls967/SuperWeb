# Katalog Galat & Panduan Resolusi Pertambangan Fase 94

## 1. Ikhtisar Penanganan Galat Sistem
Modul Pertambangan Fase 94 menerapkan konvensi penomoran galat terstandardisasi `ERR-MIN-94xx` untuk memfasilitasi diagnosa cepat, pemantauan otomatis, dan integrasi dengan sistem alert sentral.

---

## 2. Matriks Katalog Galat

| Kode Galat | Nama Galat | Tingkat | Modul Terkait | Dampak Bisnis |
|---|---|---|---|---|
| `ERR-MIN-9401` | `WeighbridgeHashMismatch` | Kritis | Timbangan Digital | Tiket timbang ditolak, jalur timbangan dibekukan. |
| `ERR-MIN-9402` | `PayloadGrossOverload` | Peringatan | Timbangan Digital | Penalti keselamatan, pemotongan premi ritase pengemudi. |
| `ERR-MIN-9403` | `MassBalanceGrossError` | Mayor | Pengolahan / Smelter | Siklus rekonsiliasi ditandai anomali, investigasi sensor. |
| `ERR-MIN-9404` | `RoyaltyTierUnresolved` | Mayor | Kepatuhan Royalti | Gagal hitung royalti, penundaan e-PNBP ESDM. |
| `ERR-MIN-9405` | `OfftakeCargoRejection` | Kritis | Offtake & Trading | Kargo ditolak pembeli, pengapalan ditunda. |
| `ERR-MIN-9406` | `BlastingSeismicExceeded` | Kritis | K3 & Peledakan | Izin peledakan dibatalkan, perancangan ulang ledakan. |
| `ERR-MIN-9407` | `TailingsDamSafetyAlert` | Kritis | Lingkungan & TSF | Alarm darurat GISTM, penghentian pembuangan slurry. |
| `ERR-MIN-9408` | `ExpiredWorkPermitAction` | Mayor | K3 Lapangan | Penghentian pekerjaan seketika di geofence kerja. |
| `ERR-MIN-9409` | `BargeDraftSurveyMismatch` | Sedang | Pelabuhan Muat | Penahanan faktur sementara, survei ulang bersama. |
| `ERR-MIN-9410` | `OfftakeTradeFinanceLcFailed` | Mayor | Integrasi Finansial | Pemuatan palka kapal tidak diizinkan sandar/muat. |

---

## 3. Detail & Prosedur Resolusi Tiap Galat

### 3.1 ERR-MIN-9401: WeighbridgeHashMismatch
- **Deskripsi**: Hash tiket timbang sebelumnya yang dikirim oleh sensor timbangan tidak cocok dengan hash rekaman tiket terakhir di basis data (`previous_ticket_hash`).
- **Penyebab Utama**:
  1. Adanya tiket offline yang tertunda pengirimannya atau hilang dari koneksi jaringan pos timbang.
  2. Modifikasi baris basis data secara langsung tanpa melalui domain action.
  3. Kesalahan waktu jam perangkat pos timbang (*system clock drift*).
- **Langkah Resolusi**:
  1. Jalankan `php artisan mining:audit-weighbridge-hash-chain --scale-id=<ID>` untuk melokalisasi indeks tiket putus.
  2. Sinkronkan antrean offline pada terminal penimbangan lokal.
  3. Lakukan verifikasi dual-supervisor untuk menerbitkan jangkar hash baru.

---

### 3.2 ERR-MIN-9402: PayloadGrossOverload
- **Deskripsi**: Muatan bersih truk melebihi $115\%$ kapasitas daya angkut terdaftar (*GVW Overload*).
- **Penyebab Utama**:
  1. Operator excavator mengisi bucket melebihi batas beban aman (*over-digging*).
  2. Kerapatan batuan/ore insitu lebih tinggi dari asumsi perencanaan geologi.
- **Langkah Resolusi**:
  1. Arahkan truk ke area dump stock penyesuaian muatan (*load shave area*).
  2. Terbitkan peringatan otomatis ke modul dispatch tambang untuk menyesuaikan jumlah pass excavator.

---

### 3.3 ERR-MIN-9403: MassBalanceGrossError
- **Deskripsi**: Uji statistik Chi-Kuadrat rekonsiliasi neraca massa metalurgi melampaui nilai kritis pada tingkat signifikansi $95\%$ ($\chi_{\text{calc}}^2 > \chi_{\text{crit}}^2$).
- **Penyebab Utama**:
  1. Kerusakan sel beban (*load cell*) pada belt scale conveyor umpan atau konsentrat.
  2. Kelembapan material basah tidak dikoreksi dengan benar pada uji laboratorium.
  3. Kalibrasi tabung analisis sinar-X (XRF) mengalami bias sistematis.
- **Langkah Resolusi**:
  1. Lakukan uji statis beban tera (*zero and span calibration*) pada seluruh belt scale sirkuit terkait.
  2. Ambil sampel duplikat (*check assay*) untuk dikirim ke laboratorium independen terakreditasi ISO 17025.

---

### 3.4 ERR-MIN-9404: RoyaltyTierUnresolved
- **Deskripsi**: Sistem gagal mencocokkan tier tarif royalti PP 19/2025 karena parameter masukan di luar rentang matriks yang diizinkan (misal harga HBA negatif atau kalori GAR nol).
- **Penyebab Utama**:
  1. Referensi harga HBA resmi bulan bersangkutan belum diinput atau diterbitkan oleh Kementerian ESDM.
  2. Kesalahan input data analisis kalori laboratorium.
- **Langkah Resolusi**:
  1. Pastikan tabel `min_reference_prices` telah memuat data HBA bulan aktif.
  2. Validasi ulang sertifikat mutu COA dari laboratorium site tambang.

---

### 3.5 ERR-MIN-9405: OfftakeCargoRejection
- **Deskripsi**: Karakteristik kualitas kargo batubara atau bijih nikel melanggar batas penolakan mutlak (*Rejection Threshold*) dalam kontrak SPA pembeli.
- **Penyebab Utama**:
  1. Kesalahan proporsi pencampuran stockpile (*blending failure*).
  2. Kontaminasi tanah lempung atau batuan samping pada saat pengupasan di pit.
- **Langkah Resolusi**:
  1. Hentikan pemuatan kargo ke palka tongkang/kapal.
  2. Jalankan ulang aksi `OptimizeOreBlendingAction` untuk mencari kargo substitusi berkadar lebih baik sebagai kompensasi pencampuran kembali (*re-blending*).
  3. Hubungi perwakilan pembeli untuk merundingkan addendum penyesuaian diskon harga komersial.

---

### 3.6 ERR-MIN-9406: BlastingSeismicExceeded
- **Deskripsi**: Perhitungan prediksi getaran tanah (PPV) atau tekanan suara ledakan (Airblast) melampaui baku tingkat getaran peledakan SNI 7571:2010.
- **Penyebab Utama**:
  1. Muatan bahan peledak per waktu tunda (*maximum instantaneous charge / MIC*) terlalu besar untuk jarak pemukiman terkait.
  2. Interval tunda detonator terlalu rapat (< 17 ms) sehingga terjadi interferensi konstruktif gelombang kejut.
- **Langkah Resolusi**:
  1. Kurangi diameter lubang ledak atau kurangi kedalaman isian bahan peledak ANFO/emulsi.
  2. Pasang decking udara (*air deck*) atau perbanyak waktu tunda detonator elektronik non-listrik.
  3. Pasang seismograf pemantau getaran lapangan di pagar terluar batas pemukiman untuk kalibrasi konstanta situs riil.


---

### 3.7 ERR-MIN-9407: TailingsDamSafetyAlert
- **Deskripsi**: Sensor piezometer vibrating wire atau instrumentasi rembesan tanggul tailing TSF mencatat kondisi bahaya (garis freatik $> 0.45$, debit rembesan $> 1.5\times$ baseline, atau rasio likuifaksi seismik $CSR/CRR \ge 1.0$).
- **Penyebab Utama**:
  1. Curah hujan ekstrem berkepanjangan yang menaikkan muka air kolam pengendapan slurry.
  2. Penyumbatan saluran pipa drainase horizontal di kaki tanggul hilir.
  3. Gempa bumi lokal atau aktivitas getaran peledakan pit di dekat perimeter TSF.
- **Langkah Resolusi**:
  1. Hentikan seketika pembuangan slurry dari pabrik pengolahan / smelter.
  2. Buka pintu pengalihan darurat menuju kolam tampung penampungan cadangan (*emergency cell*).
  3. Hidupkan pompa apung darurat (*pontoon dewatering pumps*) untuk menurunkan elevasi muka air kolam minimal $1.5\text{ m}$ di bawah mercu bendungan (*crest freeboard*).
  4. Inspeksi fisik mercu bendungan untuk memeriksa adanya rekahan tarik (*tension cracks*) atau deformasi lereng hilir.

---

### 3.8 ERR-MIN-9408: ExpiredWorkPermitAction
- **Deskripsi**: Pekerjaan berisiko tinggi (peledakan, ruang terbatas, ketinggian, panas) terdeteksi berlangsung di zona geofence saat masa berlaku izin kerja (PTW) telah melampaui batas waktu sah (*valid_until*).
- **Penyebab Utama**:
  1. Durasi pekerjaan lapangan melebihi estimasi rencana awal tanpa pengajuan perpanjangan (*shift overrun*).
  2. Pengawas pekerjaan (supervisor) lupa melakukan penutupan (*sign-off closure*) pada portal sistem K3.
- **Langkah Resolusi**:
  1. Hentikan seluruh aktivitas kerja di geofence terkait seketika (*immediate stand-down*).
  2. Pengawas K3 lapangan melakukan inspeksi keselamatan ulang di lokasi kerja.
  3. Ajukan perpanjangan izin kerja atau terbitkan tiket izin kerja baru bila kondisi bahaya telah dinilai ulang secara aman.

---

### 3.9 ERR-MIN-9409: BargeDraftSurveyMismatch
- **Deskripsi**: Perbedaan tonase antara akumulasi timbangan dermaga (*conveyor belt scale weightometer*) dan hasil pengukuran sarat air tongkang (*draft survey displacement calculation*) melampaui toleransi komersial $2.0\%$.
- **Penyebab Utama**:
  1. Kesalahan pengukuran hidrometer densitas air pelabuhan akibat perubahan salinitas pasang-surut muara sungai.
  2. Terdapat sisa air ballast atau kebocoran kompartemen ganda tongkang yang belum terdeteksi.
  3. Terjadi penguapan kelembapan (*moisture loss*) atau sebaliknya penambahan air hujan selama pemuatan di musim basah.
- **Langkah Resolusi**:
  1. Lakukan pembacaan sarat enam titik secara bersama (*joint reading*) antara surveyor penjual dan surveyor independen pembeli.
  2. Ukur ulang densitas air pelabuhan pada tiga titik kedalaman air laut/sungai yang representatif.
  3. Jalankan aksi `ReconcileMoistureLossAction` untuk menghitung ekuivalen berat kering (*Dry Metric Ton*) berdasarkan kadar kelembapan total (*Total Moisture*) sampel lab bersama.

---

### 3.10 ERR-MIN-9410: OfftakeTradeFinanceLcFailed
- **Deskripsi**: Sistem gagal mengonfirmasi atau memvalidasi keabsahan instrumen *Irrevocable Documentary Letter of Credit* (LC) yang diterbitkan bank pembeli melalui modul `TradeFinance`.
- **Penyebab Utama**:
  1. Dokumen pengapalan yang diserahkan memiliki diskrepansi data (*document discrepancy*) terhadap syarat dan ketentuan LC.
  2. Masa berlaku LC (*LC expiry date*) telah terlampaui sebelum pengapalan berlayar (*bill of lading date*).
  3. Batas kredit perbankan penerbit (*issuing bank counterparty limit*) tidak mencukupi atau bank penerbit tidak memenuhi peringkat kelayakan bank internasional (*unacceptable issuing bank rating*).
- **Langkah Resolusi**:
  1. Tahan muatan kargo di stockpile pelabuhan; jangan izinkan kapal memulai pemuatan palka (*do not issue Notice of Readiness / NOR acceptance*).
  2. Terbitkan permintaan addendum perpanjangan LC atau perbaikan klausul diskrepansi melalui bank koresponden.
  3. Jika pembeli gagal memperbaiki LC dalam batas waktu kontrak, aktifkan kompensasi pembatalan pengapalan dan alihkan kargo batubara/bijih ke pembeli domestik berkualifikasi.

---

## 4. Pemantauan Telemetri, Health Check & Audit Alerting

Untuk mendeteksi kegagalan sistem secara dini sebelum berdampak pada operasional lapangan tambang:
- **Health Check Endpoint**: `/mining/health` menyediakan pemeriksaan berkala terhadap konektivitas sensor timbangan, status antrean pemrosesan pesan asinkron, dan latensi pembacaan data geoteknik.
- **Alerting Prometheus / Grafana**: Metrik `mining_weighbridge_anomaly_total`, `mining_hse_permit_expiry_total`, dan `mining_tsf_pore_pressure_ratio` di-scrape setiap 15 detik. Peringatan otomatis dikirimkan ke bot Telegram K3 & Operasi Tambang bila terdapat anomali level kritis.


---

## 5. Ringkasan Kepatuhan & Matriks Tanggap Darurat

Seluruh penanganan galat `ERR-MIN-9401` s/d `ERR-MIN-9410` wajib dicatat dalam buku jurnal insiden (*Incident Log Book*) dan dilaporkan pada rapat koordinasi operasi mingguan (*Weekly Mine Coordination Meeting*). Tindakan korektif dan pencegahan (*Corrective and Preventive Action / CAPA*) yang diterbitkan harus ditinjau dan ditutup dalam jangka waktu maksimal 14 hari kalender oleh Kepala Teknik Tambang (KTT).
