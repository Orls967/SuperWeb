# Konsep & Spesifikasi Domain Rumah Sakit (Hospital)

Dokumen ini merupakan sumber kebenaran (single source of truth) untuk domain Rumah Sakit (Hospital) pada SuperWeb Enterprise ERP, mencakup Fase 87 (Identitas Pasien, EMR, Bed, Pathway & Critical Care) dan Fase 88 (Order-to-Cash, Farmasi, Lab, Cold-Chain, Klaim & Revenue Cycle).

---

# FASE 87 — RUMAH SAKIT I: IDENTITAS PASIEN, EMR, BED MANAGEMENT, CLINICAL PATHWAY & CRITICAL CARE

## 1. Masalah Bisnis & Latar Belakang
Pengelolaan rumah sakit skala metropolitan menuntut koordinasi presisi tinggi antara keselamatan klinis pasien, optimalisasi kapasitas ranjang yang terbatas, mitigasi keterlambatan jalur klinis (clinical pathways), respons seketika terhadap situasi gawat darurat (code blue), serta kepatuhan ketat terhadap undang-undang perlindungan data pribadi dan standar rekam medis nasional.

Ketidakefisienan alokasi ranjang mengakibatkan terjadinya penumpukan pasien di IGD (Emergency Room crowding), lonjakan waktu tunggu, hingga pembatalan operasi elektif. Di sisi lain, kelambatan deteksi pemburukan vital signs pasien di bangsal rawat inap meningkatkan angka mortalitas yang sebenarnya dapat dicegah bila skor peringatan dini (early warning score) dievaluasi secara otomatis dan deterministik.

## 2. Aktor & Peran (RBAC)
1. `hospital_registration`: Petugas loket pendaftaran rawat jalan dan rawat inap. Bertanggung jawab memverifikasi identitas pasien (Party KYC), melakukan pencarian blind-index NIK tanpa mengekspos data plaintext, menerbitkan nomor antrean, memindai QR Human Passport, dan menginisiasi permintaan admisi.
2. `bed_manager`: Penanggung jawab alokasi dan logistik tempat tidur rumah sakit. Memantau papan okupansi real-time, mengeksekusi optimasi alokasi ranjang deterministik (B1), menyetujui pemindahan bangsal (ward transfer), dan mengarahkan ranjang kotor ke siklus housekeeping.
3. `doctor`: Dokter penanggung jawab pelayanan (DPJP). Bertanggung jawab menyusun asesmen klinis, merilis perintah jalur klinis (Clinical Pathway Orders) berurutan, memeriksa riwayat alergi dan imunisasi terenkripsi, serta memimpin tim resusitasi saat terjadi code blue.
4. `nurse`: Perawat pelaksana di bangsal dan IGD. Mengoperasikan papan perawat teroptimasi mobile 375 px, mencatat tanda-tanda vital (vital signs), menerima peringatan dini keterlambatan order (> 15 menit), dan merespons aktivasi alarm code blue pada menit awal.
5. `rs_admin`: Direksi dan administrator operasional rumah sakit. Memantau dashboard efisiensi ranjang (BOR, ALOS, TOI, BTO), mengelola parameter kepekaan eksternal (KLB demam berdarah, lonjakan ISPA akibat kabut asap, banjir), serta mengaudit konsistensi buku besar.

## 3. Konsep Inti & Terminologi
- **Human Passport Medis**: Rantai paspor kesehatan berbasis kriptografi append-only (CanonicalHashChain) yang menyimpan rekaman alergi, golongan darah, riwayat pembedahan, diagnosa kronis, dan status imunisasi. Data medis dienkripsi at-rest menggunakan kunci terkelola, sementara pencarian cepat dilakukan via HMAC Blind Index (UU 27/2022 PDP).
- **Bed Overlap Prevention**: Kebijakan di mana satu ranjang hanya boleh ditempati oleh tepat satu pasien aktif dalam satu jendela waktu. Seluruh alokasi menggunakan kuncian pesimistik database (`lockForUpdate`).
- **Kelas Hak vs Kelas Rawat (Upgrade Policy)**: Hak kelas pasien dicatat berdasarkan polis/asuransi. Jika kelas yang menjadi haknya penuh dan sistem menempatkan pasien ke kelas lebih tinggi, selisih tarif kamar ditanggung sepenuhnya oleh pihak rumah sakit (`upgrade_absorbed_by_hospital = true`).
- **NEWS2 (National Early Warning Score 2)**: Protokol skoring fisiologis berstandar Royal College of Physicians yang mengagregasikan 7 parameter: laju pernapasan, saturasi oksigen SpO2, terapi oksigen tambahan, tekanan darah sistolik, denyut nadi, tingkat kesadaran (AVPU), dan suhu tubuh.
- **Barber-Johnson Indicators**: Empat indikator statistik efisiensi penggunaan tempat tidur:
  - Bed Occupancy Rate (BOR): persentase pemanfaatan ranjang dalam basis points (bps).
  - Average Length of Stay (ALOS): rata-rata lama hari rawat per pasien dalam satuan mili (1/1000 hari).
  - Turn Over Interval (TOI): rata-rata hari sebuah tempat tidur kosong sebelum diisi kembali.
  - Bed Turn Over (BTO): frekuensi pemakaian tempat tidur dalam suatu periode waktu.

## 4. Invarian Bisnis (Wajib Lulus Audit)
- **INV-87-01**: Satu ranjang tempat tidur rumah sakit tidak boleh dialokasikan kepada lebih dari satu pasien aktif (`active_patient_count <= 1`).
- **INV-87-02**: Seluruh transaksi pengakuan hari rawat inap (bed-day) wajib tercatat di buku besar akuntansi pada akun debit `hsp:patient_receivable:IDR` dan kredit `hsp:hospital_revenue:IDR` tanpa posting bernilai negatif.
- **INV-87-03**: Setiap admisi aktif wajib berpasangan dengan identitas pasien yang valid dan memiliki paspor kesehatan terverifikasi hash-chain.
- **INV-87-04**: Setiap peristiwa kegawatdaruratan Code Blue wajib memiliki jejak audit kriptografis yang membuktikan urutan eskalasi waktu menit 0 (perawat), menit 2 (dokter jaga), dan menit 5 (tim resusitasi).
- **INV-87-05**: Tidak ada informasi medis sensitif (diagnosis, resep, alergi, NIK) yang tersimpan dalam bentuk teks terbuka (plaintext) pada basis data (UU 27/2022 Pasal 34-36).

## 5. Sumber Regulasi & Kebijakan Berversi (T-8)
1. **Permenkes No. 24 Tahun 2022 tentang Rekam Medis**:
   - Pasal 3 & Pasal 4: Kewajiban penyelenggaraan Rekam Medis Elektronik (RME) yang terintegrasi bagi seluruh fasilitas pelayanan kesehatan paling lambat 31 Desember 2023.
   - Pasal 32: Kewajiban menjaga keamanan, kerahasiaan, keutuhan, dan ketersediaan data rekam medis.
   - Pasal 39: Kewajiban penyimpanan dan retensi data rekam medis paling singkat 25 tahun sejak tanggal kunjungan terakhir pasien.
   - Sumber tautan: https://pasal.id/peraturan/permen/permenkes-no-24-tahun-2022 (diakses 2026-11-08). Klasifikasi: `regulasi`.
2. **Royal College of Physicians (NEWS2 Guideline)**:
   - Standar skor peringatan dini fisiologis terbitan RCP London.
   - Parameter: Laju napas (<=8: 3, 9-11: 1, 12-20: 0, 21-24: 2, >=25: 3); SpO2 Skala 1 (<=91: 3, 92-93: 2, 94-95: 1, >=96: 0); Oksigen Suplemental (Ya: 2, Tidak: 0); Tekanan Sistolik (<=90: 3, 91-100: 2, 101-110: 1, 111-219: 0, >=220: 3); Nadi (<=40: 3, 41-50: 1, 51-90: 0, 91-110: 1, 111-130: 2, >=131: 3); Kesadaran (Alert: 0, CVPU: 3); Suhu (<=35.0: 3, 35.1-36.0: 1, 36.1-38.0: 0, 38.1-39.0: 1, >=39.1: 2).
   - Klasifikasi: `regulasi` / `asumsi_simulasi`.
3. **Undang-Undang No. 27 Tahun 2022 tentang Pelindungan Data Pribadi (UU PDP)**:
   - Data kesehatan dikategorikan sebagai data pribadi spesifik yang wajib dilindungi dengan enkripsi kuat, akses berbasis otorisasi ketat, serta audit jejak akses. Klasifikasi: `regulasi`.

## 6. Matriks Kepekaan Eksternal (T-5)
Semua angka dan variabel lingkungan luar dibaca dari gerbang tunggal `HospitalExternalFactors` melalui tabel `hsp_external_factors`:

| Faktor Eksternal | Parameter Basis Data | Aturan / Elastisitas Respons | Jeda Respons | Sumber Regulasi / Data |
|---|---|---|---|---|
| Lonjakan Kasus DBD | `epidemic_dengue_index` | Indeks >= 150 memicu pencadangan 20% bed isolasi dan penundaan operasi elektif kelas 3 | Evaluasi harian | Surveilans Dinkes / Simulasi |
| Krisis Kualitas Udara (ISPA) | `air_quality_pm25_index` | PM2.5 > 150 ug/m3 memicu penambahan kuota bed anak & HDU respirasi sebesar 15% | Real-time / 6 jam | Sensor Lingkungan KLHK |
| Bencana Banjir Akses RS | `flood_access_blocked_hours` | Akses terputus > 2 jam mengaktifkan status siaga darurat, penundaan transfer antar-RS, dan aktivasi helipad | Seketika (< 15 menit) | BPBD / SAR Nasional |
| Fluktuasi Kurs Alkes Impor | `usd_exchange_rate` | Kenaikan kurs > 500 bps di atas pagu dasar memicu peninjauan margin tarif prosedur bedah | Mingguan | Bank Indonesia |

## 7. Katalog Kasus Pengujian (Minimal 15 Kasus Bernama)
1. `KASUS-87-01 (Normal Elective Inpatient)`: Pasien bedah terjadwal masuk ranjang Kelas 1, alokasi sukses, deposit tertangkap, discharge normal hari ke-4.
2. `KASUS-87-02 (ICU Boundary Allocation)`: Dua pasien gawat darurat dengan skor triase identik memperebutkan 1 ranjang ICU tersisa; pemenang ditentukan secara deterministik oleh waktu tunggu terlama.
3. `KASUS-87-03 (Bed Upgrade Zero Surcharge)`: Pasien hak Kelas 3 ditempatkan di Kelas 2 karena Kelas 3 penuh 100%; sistem memastikan tagihan pasien tetap pada tarif Kelas 3 dan selisih ditanggung RS.
4. `KASUS-87-04 (NEWS2 Boundary Score 7)`: Pasien bangsal mengalami laju napas 24, SpO2 92%, sistolik 100, nadi 95; skor terhitung tepat 7 (Kategori Risiko TINGGI) memicu notifikasi dokter jaga.
5. `KASUS-87-05 (NEWS2 Single 3 Parameter Alert)`: Pasien dengan tanda vital normal kecuali tekanan darah sistolik 88 mmHg (skor 3); total skor 3 diklasifikasikan ke RENDAH_SEDANG.
6. `KASUS-87-06 (Code Blue Dual-Breach Trigger)`: Telemetri mendeteksi SpO2 83% pada menit ke-1 dan SpO2 82% pada menit ke-2; alarm Code Blue dinaikkan pada menit ke-2 secara idempoten.
7. `KASUS-87-07 (Code Blue Escalation Timeout)`: Alarm Code Blue tidak di-acknowledge perawat dalam 2 menit; sistem eskalasi otomatis mengirim pemicu ke dokter jaga dan tim medis gawat darurat.
8. `KASUS-87-08 (Clinical Pathway First-Delay Alert)`: Order laboratorium terlambat 25 menit (> toleransi 15 menit); alert keterlambatan dikirim tepat sekali, order turunan radiologi dijadwalkan ulang tanpa alert ganda.
9. `KASUS-87-09 (Anti-Double Booking Concurrency)`: Dua proses admisi paralel mencoba mengunci ranjang yang sama dengan `lockForUpdate`; proses kedua ditolak dengan exception `BedAlreadyReservedException`.
10. `KASUS-87-10 (Passport Tamper Detection)`: Perubahan liar langsung pada kolom database hash paspor pasien terdeteksi saat verifikasi berkala; sistem membekukan paspor dengan status `COMPROMISED`.
11. `KASUS-87-11 (Escrow Insufficient Funds Compensation)`: Pasien dialokasikan ranjang, tetapi proses escrow deposit gagal karena saldo tidak mencukupi; saga secara otomatis membatalkan reservasi ranjang dan mengembalikannya ke status `AVAILABLE`.
12. `KASUS-87-12 (Midnight Admission Cross-Shift)`: Pasien di-admit pada pukul 23:55 WIB; perhitungan bed-day harian pertama dihitung tepat saat pergantian tanggal buku besar.
13. `KASUS-87-13 (Barber-Johnson Efficiency Benchmark)`: Rumah sakit 200 ranjang dengan 4.800 hari-rawat dalam 30 hari menghasilkan BOR 8.000 bps, ALOS 4.800 mili, TOI 1.200 mili, BTO 5.000 mili.
14. `KASUS-87-14 (Bed Discharge to Housekeeping)`: Pasien keluar memindahkan ranjang ke status `cleaning`; ranjang tidak dapat dipesan sebelum perawat mengonfirmasi inspeksi sanitasi.
15. `KASUS-87-15 (Epidemic Surge Contingency)`: Pemicu lonjakan indeks DBD mengalihkan 15 ranjang reguler menjadi ranjang isolasi darurat dengan persetujuan administratif `rs_admin`.


## 8. Pemetaan Invarian Bisnis ke File & Baris Kode
Berikut adalah tabel audit kepatuhan invarian sistem terhadap berkas implementasi dan posisi baris kode aktual:

| Kode Invarian | Pernyataan Invarian | Berkas Kode Implementasi | Lokasi Metode / Baris Kunci | Mekanisme Pencegahan Pelanggaran |
| :--- | :--- | :--- | :--- | :--- |
| **INV-87-01** | Satu ranjang hanya boleh ditempati oleh tepat 1 pasien aktif (`active_patients <= 1`) | `modules/Hospital/Application/Sagas/InpatientAdmissionSaga.php` | `execute()`, baris 50-80 | Kuncian pesimistik `HospitalBed::where(...)->lockForUpdate()` dalam transaksi DB |
| **INV-87-02** | Pengakuan hari rawat inap (bed-day) wajib tercatat pada debit `hsp:patient_receivable:IDR` dan kredit `hsp:hospital_revenue:IDR` tanpa angka negatif | `modules/Hospital/Domain/Services/HospitalLedgerPostingService.php` | `postDailyBedAccrual()`, baris 45-80 | Pengecekan `amount > 0` dan pemanggilan `LedgerService::post()` multi-entry balance |
| **INV-87-03** | Admisi aktif wajib memiliki identitas pasien terdaftar dan paspor kesehatan terverifikasi rantai hash | `modules/Hospital/Domain/Services/PatientPassportService.php` | `appendEvent()`, `detectTampering()` | Kalkulasi `CanonicalHashChain` SHA-256 berurutan dengan penolakan data jika hash rantai tidak sinkron |
| **INV-87-04** | Eskalasi Code Blue wajib memiliki jejak audit waktu deterministik menit 0, menit 2, dan menit 5 | `modules/Hospital/Domain/Algorithms/CodeBlueEscalationPlanner.php` | `plan()`, baris 35-75 | Komparasi deret waktu telemetri dan pembuatan sertifikat `CodeBlueEscalationCertificate` HMAC-SHA256 |
| **INV-87-05** | Larangan penyimpanan data rekam medis plaintext dan kebocoran NIK (UU 27/2022) | `modules/Hospital/Domain/Models/Patient.php` | `$casts`, enkripsi kolom medis | Enkripsi simetris at-rest dan blind-index HMAC untuk query pencarian identitas |
| **INV-87-06** | Status ranjang pasca pemulangan wajib melalui sanitasi housekeeping sebelum tersedia kembali | `modules/Hospital/Application/Sagas/BedDischargeAndCleaningSaga.php` | `execute()`, baris 30-65 | Transisi `OCCUPIED` $\rightarrow$ `CLEANING` $\rightarrow$ `AVAILABLE` via `HospitalBedStateMachine` |
| **INV-87-07** | Toleransi keterlambatan Clinical Pathway maksimal 15 menit dengan notifikasi tepat-sekali | `modules/Hospital/Domain/Algorithms/ClinicalPathwayDelayDetector.php` | `detect()`, baris 30-55 | Evaluasi delay berurutan dan deduplikasi alert ID dalam satu siklus jadwal |
| **INV-87-08** | Hak kelas rawat pasien yang di-upgrade wajib ditanggung RS tanpa beban biaya ke pasien | `modules/Hospital/Domain/Algorithms/BedAssignmentOptimizer.php` | `optimize()`, baris 55-90 | Penandaan atribut `is_upgrade_subsidized = true` dan isolasi tarif ke kelas hak dasar |

## 9. Detail Spesifikasi 15 Kasus Uji Klinis Terperinci
Setiap kasus di bawah ini diuji secara ketat dalam acceptance test dan simulasi sistem:

### KASUS-87-01: Admisi Elektif Terjadwal Rawat Inap Bedah
- **Kondisi Klinis**: Pasien laki-laki 45 tahun diagnosa Cholelithiasis terjadwal Cholecystectomy elektif.
- **Pra-Kondisi**: Pasien memiliki hak rawat Kelas 1. Ranjang Kelas 1 tersedia di bangsal Bedah Angsana.
- **Langkah Eksekusi**:
  1. Loket memindai paspor kesehatan pasien dan memvalidasi NIK via blind-index.
  2. Sistem menjalankan `InpatientAdmissionSaga` dengan deposit awal Rp 5.000.000.
  3. Kuncian pesimistik ranjang berhasil; status ranjang berubah menjadi `OCCUPIED`.
  4. Buku besar mencatat penerimaan kas deposit pada debit `hsp:cash:IDR` dan kredit `hsp:patient_deposit_escrow:IDR`.
- **Hasil yang Diharapkan**: Admisi berstatus `ADMITTED`, paspor kesehatan mencatat riwayat admisi, saga selesai tanpa kompensasi.

### KASUS-87-02: Perebutan Ranjang ICU Berdasarkan Triase & Waktu Tunggu
- **Kondisi Klinis**: Dua pasien di IGD mengalami gagal napas akut. Pasien A (NEWS2 = 9, waktu tunggu 45 menit). Pasien B (NEWS2 = 9, waktu tunggu 120 menit).
- **Pra-Kondisi**: Tersedia hanya 1 ranjang ICU tersisa (`RS-A-ICU-001`).
- **Langkah Eksekusi**:
  1. `BedAssignmentOptimizer` dipanggil untuk mengevaluasi kedua kandidat.
  2. Algoritma menyortir berdasarkan prioritas klinis (keduanya prioritas 0) lalu waktu tunggu terlama (*longest waiting time*).
- **Hasil yang Diharapkan**: Ranjang ICU dialokasikan kepada Pasien B. Pasien A tetap dalam status prioritas tinggi pada antrean observasi IGD dengan sertifikat B1 terverifikasi.

### KASUS-87-03: Peningkatan Kelas Ranjang (Upgrade) Bebas Tambahan Biaya
- **Kondisi Klinis**: Pasien ibu melahirkan dengan hak kelas rawat Kelas 3 BPJS.
- **Pra-Kondisi**: Seluruh ranjang Kelas 3 di RS penuh 100%. Ranjang Kelas 2 tersedia di Bangsal Flamboyan.
- **Langkah Eksekusi**:
  1. `BedAssignmentOptimizer` mendeteksi kepenuhan Kelas 3 dan mencari ranjang satu tingkat di atasnya (Kelas 2).
  2. Sistem menetapkan flag `upgrade_absorbed_by_hospital = true`.
  3. Saat akrual harian dijalankan oleh `AccrueDailyBedRevenueAction`, tarif yang ditagihkan ke piutang pasien adalah tarif hak Kelas 3 (Rp 300.000/hari), sementara selisih tarif Kelas 2 (Rp 200.000/hari) dialokasikan ke akun subsidi rumah sakit.
- **Hasil yang Diharapkan**: Pasien tidak dibebani selisih biaya upgrade (kepatuhan regulasi BPJS / Permenkes).

### KASUS-87-04: Deteksi Skor NEWS2 Kritis ($\ge 7$) pada Bangsal Medikal
- **Kondisi Klinis**: Pasien demam pasca operasi mengalami takikardia 115 bpm, takipnea 26 x/menit, SpO2 91% udara bebas, dan tensi 90/60 mmHg.
- **Pra-Kondisi**: Pasien berada di bangsal perawatan umum Kelas 2.
- **Langkah Eksekusi**:
  1. Perawat memasukkan tanda vital telemetri melalui terminal bangsal 375 px.
  2. `EarlyWarningScoreCalculator` menghitung agregat skor fisiologis: laju napas (3), SpO2 (3), nadi (2), tensi (3) = Total NEWS2 11.
  3. Sistem mengklasifikasikan ke kategori `CRITICAL` dan memicu event `VitalSignDeterioratedEvent`.
- **Hasil yang Diharapkan**: Notifikasi instan terkirim ke ponsel dokter jaga dan *Rapid Response Team* (RRT) bangsal.

### KASUS-87-05: Parameter Tunggal Skor Ekstrem NEWS2 (Skor 3 pada 1 Komponen)
- **Kondisi Klinis**: Pasien pasca sedasi memiliki frekuensi pernapasan 7 kali per menit (bradipnea berat), namun parameter lain normal.
- **Pra-Kondisi**: Total skor kumulatif hanya 3 poin.
- **Langkah Eksekusi**:
  1. `EarlyWarningScoreCalculator` mengevaluasi vektor vital.
  2. Meskipun agregat $\le 4$, algoritma mendeteksi adanya *Single Parameter Score 3* pada respirasi.
- **Hasil yang Diharapkan**: Status peringatan dinaikkan dari `LOW` menjadi `LOW_MEDIUM` dengan rekomendasi eskalasi observasi berkala tiap 1 jam.

### KASUS-87-06: Aktivasi Beruntun Alarm Code Blue & Pencegahan Pemicu Ganda
- **Kondisi Klinis**: Pasien henti napas di ruang HCU.
- **Pra-Kondisi**: Telemetri membaca SpO2 83% pada menit ke-1 dan SpO2 81% pada menit ke-2.
- **Langkah Eksekusi**:
  1. `CodeBlueEscalationPlanner` menganalisis rentet waktu telemetri.
  2. Pelanggaran kedua berturut-turut pada menit ke-2 memicu pembentukan `CodeBlueEvent` pertama.
  3. Telemetri berikutnya pada menit ke-3 masih membaca SpO2 rendah.
- **Hasil yang Diharapkan**: Sistem tidak menerbitkan record Code Blue baru untuk insiden yang sama, melainkan memperbarui status eskalasi insiden yang sedang berjalan (idempoten).

### KASUS-87-07: Batas Waktu Eskalasi Code Blue Tanpa Respons Perawat
- **Kondisi Klinis**: Alarm Code Blue berbunyi di Bangsal Isolasi Penyakit Menular.
- **Pra-Kondisi**: Event diaktifkan pada menit 0, namun perawat belum melakukan konfirmasi (`acknowledge`) di papan perawat.
- **Langkah Eksekusi**:
  1. Scheduler `TelemetryScanCommand` memeriksa status insiden aktif.
  2. Waktu berlalu melewati menit ke-2 tanpa ack.
  3. Sistem mengeksekusi transisi status ke `DISPATCHED_TO_DUTY_DOCTOR`.
  4. Melewati menit ke-5 tanpa resolusi, sistem menyiarkan panggilan darurat ke Tim Medis Resusitasi Rumah Sakit (`DISPATCHED_TO_CODE_BLUE_TEAM`).
- **Hasil yang Diharapkan**: Rekaman log eskalasi waktu tepat `['PERAWAT@0', 'DOKTER@2', 'TIM_CODE_BLUE@5']`.

### KASUS-87-08: Peringatan Keterlambatan Clinical Pathway Tepat-Sekali
- **Kondisi Klinis**: Pasien Acute Myocardial Infarction (STEMI) rawat inap hari ke-1 dengan jalur klinis wajib: EKG serial jam ke-2, Enzim Jantung jam ke-3.
- **Pra-Kondisi**: Order Enzim Jantung terlambat 20 menit dari jadwal toleransi.
- **Langkah Eksekusi**:
  1. `ClinicalPathwayDelayDetector` memindai jadwal order.
  2. Keterlambatan terdeteksi; sistem menerbitkan 1 notifikasi ke perawat penanggung jawab.
  3. Pemindaian pada menit berikutnya tidak mengirimkan notifikasi duplikat.
- **Hasil yang Diharapkan**: Notifikasi hanya dikirim sekali (`single_alert = true`), ringkasan varians tercatat di catatan perjalanan klinis.

### KASUS-87-09: Pencegahan Double-Booking Ranjang Konkurensi Tinggi
- **Kondisi Klinis**: Dua dokter IGD secara bersamaan memilih ranjang isolasi terakhir (`RS-A-ISO-001`) untuk dua pasien berbeda pada milidetik yang sama.
- **Pra-Kondisi**: Ranjang berstatus `AVAILABLE`.
- **Langkah Eksekusi**:
  1. Request A dan Request B masuk ke `InpatientAdmissionSaga`.
  2. Request A memperoleh database row lock (`lockForUpdate`).
  3. Request B tertahan hingga transaksi A selesai me-reserve ranjang.
  4. Saat giliran transaksi B berjalan, ranjang sudah berstatus `RESERVED`.
- **Hasil yang Diharapkan**: Transaksi B membatalkan alokasi ranjang tersebut dan melempar `BedAlreadyReservedException`, mencegah bentrok ranjang ganda.

### KASUS-87-10: Deteksi Perusakan Hash Paspor Kesehatan (Anti-Tampering)
- **Kondisi Klinis**: Upaya penyusupan basis data mengubah catatan alergi penisilin pasien dari `PENICILLIN_ANAPHYLAXIS` menjadi kosong.
- **Pra-Kondisi**: Kolom database `payload` diubah secara langsung di luar aplikasi.
- **Langkah Eksekusi**:
  1. Pemeriksaan integritas berkala dijalankan oleh `PatientPassportService::detectTampering()`.
  2. Sistem menghitung ulang hash SHA-256 rantai peristiwa dari event awal hingga akhir.
  3. Hash komputasi berbeda dengan nilai `current_hash` yang tersimpan.
- **Hasil yang Diharapkan**: Paspor pasien ditandai `INTEGRITY_COMPROMISED`, rute resep farmasi memblokir order antibiotik hingga rekonsiliasi manual dilakukan.

### KASUS-87-11: Kompensasi Otomatis Pembatalan Admisi Gagal Deposit
- **Kondisi Klinis**: Pasien dialokasikan ranjang, tetapi transaksi pembayaran deposit uang muka gagal/ditolak perbankan.
- **Pra-Kondisi**: Ranjang sempat terkunci ke status `RESERVED`.
- **Langkah Eksekusi**:
  1. `InpatientAdmissionSaga` menangkap exception kegagalan posting ledger.
  2. Blok catch mengeksekusi kompensasi: membatalkan admisi ke `CANCELLED` dan mengembalikan ranjang ke `AVAILABLE`.
- **Hasil yang Diharapkan**: Basis data kembali konsisten, ranjang dapat dipilih oleh pasien lain seketika.

### KASUS-87-12: Akrual Sewa Kamar Melintasi Tengah Malam (Midnight Cut-off)
- **Kondisi Klinis**: Pasien masuk kamar rawat inap pada pukul 23:45 WIB tanggal 8 November 2026.
- **Pra-Kondisi**: Pergantian buku besar harian otomatis terjadi pada pukul 00:00:01 WIB.
- **Langkah Eksekusi**:
  1. Scheduler menjalankan `ReconcileDailyInpatientBillingAction` untuk tanggal 8 November.
  2. Pasien yang telah terdaftar aktif sebelum pukul 24:00 diakrualkan 1 hari rawat kamar.
- **Hasil yang Diharapkan**: Jurnal buku besar tercatat rapi pada tanggal pelayanan yang bersangkutan tanpa posting ganda saat pagi harinya.

### KASUS-87-13: Benchmark Efisiensi Tempat Tidur Barber-Johnson
- **Kondisi Klinis**: Evaluasi triwulan efisiensi 200 ranjang RSUD Tipe A dengan 4.800 hari rawat pasien dan 1.000 pasien keluar.
- **Pra-Kondisi**: Periode evaluasi 30 hari.
- **Langkah Eksekusi**:
  1. `BedEfficiencyIndicators` menghitung:
     - $\text{BOR} = \frac{4800}{200 \times 30} \times 100\% = 80\%$ (ideal 75-85%).
     - $\text{ALOS} = \frac{4800}{1000} = 4.8$ hari (ideal 3-12 hari).
     - $\text{TOI} = \frac{(200 \times 30) - 4800}{1000} = 1.2$ hari (ideal 1-3 hari).
     - $\text{BTO} = \frac{1000}{200} = 5.0$ kali.
- **Hasil yang Diharapkan**: Status efisiensi Barber-Johnson dinilai `EFFICIENT` dengan sertifikat B4 terverifikasi.

### KASUS-87-14: Alur Housekeeping Sanitasi Pasca Pasien Pulang
- **Kondisi Klinis**: Pasien ruang isolasi dinyatakan sembuh dan menyelesaikan administrasi pemulangan.
- **Pra-Kondisi**: Ranjang berstatus `OCCUPIED`.
- **Langkah Eksekusi**:
  1. `BedDischargeAndCleaningSaga` dijalankan.
  2. Ranjang bertransisi ke status `CLEANING`.
  3. Pilihan ranjang tidak muncul dalam daftar ranjang tersedia pada dashboard admisi.
  4. Petugas sanitasi menyelesaikan disinfeksi ultraviolet dan menekan konfirmasi sanitasi.
- **Hasil yang Diharapkan**: Ranjang bertransisi ke `AVAILABLE` dan siap menerima pasien baru.

### KASUS-87-15: Kontingensi Lonjakan Wabah Demam Berdarah Dengue (DBD)
- **Kondisi Klinis**: Dinas Kesehatan mengumumkan kenaikan indeks penularan dengue di atas 150.
- **Pra-Kondisi**: Indeks faktor eksternal terbaca di `hsp_external_factors`.
- **Langkah Eksekusi**:
  1. Direktur operasional mengeksekusi `AdjustCapacityForEpidemicAction`.
  2. Sistem mencadangkan 20% kapasitas ranjang rawat umum menjadi ranjang penanganan DBD anak dan dewasa.
  3. Penjadwalan operasi elektif non-urgen Kelas 3 ditangguhkan secara otomatis.
- **Hasil yang Diharapkan**: Kapasitas ranjang darurat tersedia tanpa mengorbankan pasien rawat inap yang sedang kritis.

## 10. Prosedur Uji Sabotase Klinis & Pemulihan (L.5)
Untuk membuktikan ketahanan dan keandalan sistem terhadap anomali kritis:
1. **Sabotase Pembalikan Tanda Ledger**:
   - Skrip pengujian membalik saldo debit/kredit pada posting deposit ranjang.
   - Hasil yang Diharapkan: `LedgerService` menolak posting karena saldo negatif pada akun aset atau ketidakseimbangan neraca. Perintah audit `php artisan hosp:audit` keluar dengan kode status non-zero (gagal).
   - Pemulihan: Memulihkan urutan posting debit kas dan kredit liabilitas escrow.
2. **Sabotase Perusakan Rantai Paspor**:
   - Skrip pengujian mengubah isi event di tabel `hsp_patient_passport_events`.
   - Hasil yang Diharapkan: Verifikasi integritas mendeteksi `is_tampered = true` dan memblokir pencetakan resume medis.
   - Pemulihan: Memulihkan rekam jejak dari backup canonical log dan menandai peristiwa forensik.
3. **Sabotase Sertifikat Algoritma**:
   - Memodifikasi payload sertifikat hasil optimasi alokasi ranjang atau kalkulasi skor NEWS2.
   - Hasil yang Diharapkan: `News2CertificateVerifier` melempar `RuntimeException` integritas sertifikat tidak valid.
   - Pemulihan: Mengembalikan proses komputasi melalui fungsi kanonikal domain.

## 11. Arsitektur Interoperabilitas SatuSehat Kemenkes RI (FHIR R4)
Modul Hospital mengimplementasikan standar FHIR (*Fast Healthcare Interoperability Resources*) R4 untuk pertukaran data medis nasional:
- `Encounter`: Merekam riwayat admisi rawat inap, status kedatangan, waktu masuk/keluar, dan DPJP.
- `Observation`: Merekam pemantauan parameter tanda vital dan skor NEWS2 berkala.
- `Condition`: Merekam diagnosis utama dan sekunder berbasis kodefikasi ICD-10 resmi.
- `Procedure`: Merekam tindakan operasi dan intervensi klinis berbasis ICD-9-CM.
- Seluruh data dikemas dalam `FHIRBundlePayloadDTO` bertipe `transaction` dengan validasi kepatuhan sintaks sebelum transmisi.


## 13. Glosarium Terminologi Medis & Singkatan Standar
Berikut adalah daftar definisi singkatan dan terminologi operasional klinis yang digunakan dalam modul Hospital:
- **ACLS (*Advanced Cardiovascular Life Support*)**: Protokol bantuan hidup jantung lanjut berbasis bukti yang diterbitkan oleh American Heart Association (AHA).
- **ALOS (*Average Length of Stay*)**: Rata-rata lama waktu perawatan pasien rawat inap yang dihitung dalam hari atau perseribu hari (mili-hari).
- **AVPU (*Alert, Voice, Pain, Unresponsive*)**: Skala cepat penilaian kesadaran neurologis pasien gawat darurat.
- **BHD (*Bantuan Hidup Dasar*) / BLS (*Basic Life Support*)**: Tindakan awal penyelamatan jiwa tanpa alat medis invasif yang mencakup kompresi dada dan ventilasi dasar.
- **BOR (*Bed Occupancy Rate*)**: Angka persentase penggunaan tempat tidur rumah sakit yang dinyatakan dalam basis points ($1\% = 100\text{ bps}$).
- **BTO (*Bed Turn Over*)**: Frekuensi perputaran pemakaian sebuah tempat tidur dalam satuan periode waktu kalender.
- **CP (*Clinical Pathway*)**: Alur rencana pelayanan klinis terpadu multidisiplin yang memetakan tahapan asuhan harian pasien sesuai diagnosis spesifik.
- **CVC (*Central Venous Catheter*)**: Kateter pembuluh darah vena sentral untuk pemberian infus cairan hipertonik atau obat vasopresor.
- **DPJP (*Dokter Penanggung Jawab Pelayanan*)**: Dokter spesialis yang memimpin dan bertanggung jawab penuh atas seluruh asuhan medis seorang pasien rawat inap.
- **ETT (*Endotracheal Tube*)**: Pipa napas buatan yang dimasukkan ke trakea untuk ventilasi mekanik paru definitif.
- **HAIs (*Healthcare-Associated Infections*)**: Infeksi yang diperoleh pasien selama menjalani perawatan di fasilitas kesehatan setelah $\ge 48$ jam rawat inap.
- **HCU (*High Care Unit*)**: Unit perawatan antara ruang rawat inap umum dengan ruang rawat intensif (ICU) untuk pasien yang memerlukan pemantauan ketat tanda vital.
- **IHS (*Indonesia Health Services*)**: Nomor identitas tunggal pasien dan tenaga kesehatan yang diterbitkan oleh platform SatuSehat Kemenkes RI.
- **KMKP (*Komite Mutu dan Keselamatan Pasien*)**: Komite rumah sakit yang bertugas menginvestigasi insiden keselamatan pasien dan menganalisis akar masalah (*Root Cause Analysis*).
- **KRS (*Keluar Rumah Sakit*)**: Status pelepasan pasien dari rawat inap (baik dinyatakan sembuh, membaik, dirujuk, pulang paksa, atau meninggal dunia).
- **MDRO (*Multidrug-Resistant Organism*)**: Mikroorganisme bakteri yang resistan terhadap lebih dari satu golongan antibiotik utama (misal: MRSA, VRE, ESBL).
- **MRN (*Medical Record Number*) / No. RM**: Nomor rekam medis unik pasien yang berlaku seumur hidup di rumah sakit.
- **NEWS2 (*National Early Warning Score 2*)**: Sistem pembobotan fisiologis terstandar untuk deteksi dini perburukan kondisi klinis pasien di bangsal perawatan.
- **ROSC (*Return of Spontaneous Circulation*)**: Kembalinya denyut nadi dan sirkulasi darah spontan pasien setelah tindakan resusitasi henti jantung.
- **RRT (*Rapid Response Team*) / Tim Reaksi Cepat**: Tim medis tanggap darurat antardisiplin yang dipanggil saat skor NEWS2 pasien meningkat drastis.
- **SBAR (*Situation, Background, Assessment, Recommendation*)**: Kerangka kerja komunikasi klinis terstandar untuk pelaporan kondisi pasien dan serah terima tugas jaga keperawatan.
- **TOI (*Turn Over Interval*)**: Rata-rata hari sebuah tempat tidur dibiarkan kosong antara pasien yang baru pulang dengan pasien berikutnya yang masuk.

- **VAS (*Visual Analogue Scale*)**: Skala pengukuran intensitas nyeri subjektif pasien dari rentang 0 (tanpa nyeri) hingga 10 (nyeri sangat hebat tidak tertahankan).
- **VAP (*Ventilator-Associated Pneumonia*)**: Infeksi paru nosokomial yang timbul setelah $\ge 48$ jam pemakaian ventilator mekanik invasif.
- **CAUTI (*Catheter-Associated Urinary Tract Infection*)**: Infeksi saluran kemih nosokomial yang timbul akibat pemasangan kateter urin menetap (*foley catheter*).
- **CLABSI (*Central Line-Associated Bloodstream Infection*)**: Infeksi aliran darah primer yang berhubungan dengan pemasangan kateter vena sentral.
- **SSI (*Surgical Site Infection*) / IDO (*Infeksi Daerah Operasi*)**: Infeksi pada sayatan bedah yang terjadi dalam tempo 30 hari pasca pembedahan.

- **Triase ESI (*Emergency Severity Index*)**: Algoritma stratifikasi kegawatan pasien IGD menjadi 5 level: Level 1 (Resusitasi/Mengancam Jiwa), Level 2 (Emergency/Tinggi), Level 3 (Urgent/Dua Sumber Daya), Level 4 (Semi-urgent/Satu Sumber Daya), dan Level 5 (Non-urgent/Tanpa Sumber Daya).
- **KNC (*Kejadian Nyaris Cedera*)**: Terjadinya insiden yang belum sampai terpapar ke pasien karena dicegah lebih awal (*near miss*).
- **KTD (*Kejadian Tidak Diharapkan*)**: Insiden yang mengakibatkan cedera pada pasien yang tidak disengaja akibat tindakan medis atau non-medis.
- **KTC (*Kejadian Tidak Cedera*)**: Insiden yang sudah terpapar ke pasien, tetapi tidak menimbulkan cedera klinis yang nyata.
- **Sentinel Event**: Kejadian tidak diharapkan yang mengakibatkan kematian, cedera permanen, atau kehilangan fungsi organ tubuh pasien secara mendadak.

- **KPC (*Kondisi Potensial Cedera*)**: Kondisi yang sangat berpotensi untuk menimbulkan cedera, tetapi belum terjadi insiden.
- **RCA (*Root Cause Analysis*)**: Metode evaluasi terstruktur untuk mengidentifikasi akar penyebab mendasar dari insiden keselamatan pasien.
