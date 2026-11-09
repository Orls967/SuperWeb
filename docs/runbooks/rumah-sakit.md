# Runbook Operasional Rumah Sakit (Hospital)

Dokumen ini memuat panduan eksekusi perintah terjadwal (CLI Commands), pemulihan bencana (disaster recovery), serta penanganan kegagalan sistem pada domain Rumah Sakit.

---

## 1. Perintah Terjadwal (Console Commands)

### 1.1 `hospital:bed-sweep`
- **Tujuan**: Memeriksa status kebersihan dan reservasi ranjang kedaluwarsa. Ranjang berstatus `cleaning` yang telah melebihi 120 menit tanpa konfirmasi sanitasi akan dinaikkan tiket eskalasinya ke tim pengawas kebersihan.
- **Jadwal Eksekusi**: Setiap 30 menit (`*/30 * * * *`).
- **Idempotensi**: Idempoten penuh. Menjalankan ulang perintah pada jam yang sama tidak menimbulkan efek samping ganda.
- **Cara Menjalankan Manual**:
  ```bash
  php artisan hospital:bed-sweep
  ```
- **Langkah Pemulihan Galat**: Jika perintah gagal karena timeout database, periksa kuncian ranjang aktif via query transaksi MySQL/PostgreSQL dan jalankan ulang dengan opsi `--chunk=100`.

### 1.2 `hospital:pathway-scan`
- **Tujuan**: Memindai seluruh Clinical Pathway Orders yang berstatus `ordered` atau `scheduled`. Mengevaluasi keterlambatan dengan algoritma B3 `ClinicalPathwayDelayDetector`. Mengirim alert perawat tepat 1 kali jika keterlambatan > 15 menit.
- **Jadwal Eksekusi**: Setiap 10 menit (`*/10 * * * *`).
- **Idempotensi**: Idempoten. Peringatan hanya dikirim jika flag `delay_alert_sent` masih bernilai `false`.
- **Cara Menjalankan Manual**:
  ```bash
  php artisan hospital:pathway-scan
  ```

### 1.3 `hospital:efficiency-report {date}`
- **Tujuan**: Menghitung indikator efisiensi tempat tidur Barber-Johnson (BOR, ALOS, TOI, BTO) untuk tanggal referensi tertentu, mencatat ringkasan ke tabel agregat audit, serta memperbarui cache dashboard.
- **Jadwal Eksekusi**: Harian pukul 01:00 WIB (`0 18 * * *` UTC).
- **Idempotensi**: Idempoten. Perhitungan ulang menggantikan catatan tanggal yang bersangkutan tanpa mengubah saldo historis.
- **Cara Menjalankan Manual**:
  ```bash
  php artisan hospital:efficiency-report 2026-11-09
  ```

### 1.4 `hospital:telemetry-scan`
- **Tujuan**: Memindai anomali telemetri vital pasien bangsal intensif, mengevaluasi pelanggaran SpO2/nadi via algoritma B5, dan memicu eskalasi alarm Code Blue jika terjadi pelanggaran ganda dalam 3 menit.
- **Jadwal Eksekusi**: Setiap 1 menit (`* * * * *`).
- **Idempotensi**: Idempoten. Pemicuan alarm Code Blue dilindungi constraint unik per episode perawatan aktif.
- **Cara Menjalankan Manual**:
  ```bash
  php artisan hospital:telemetry-scan
  ```

### 1.5 `hsp:audit` (Command Audit Dua Sumber)
- **Tujuan**: Memverifikasi rekonsiliasi antara catatan operasional modul Hospital dengan sistem Buku Besar Akuntansi (General Ledger), integritas hash chain paspor kesehatan, status okupansi ranjang anti-bentrok, dan konsistensi data medis terenkripsi.
- **Cara Menjalankan**:
  ```bash
  php artisan hsp:audit
  ```
- **Kriteria Lulus**: Keluar dengan exit code 0 (`HEALTHY`). Jika terdapat selisih pembukuan atau anomali data, keluar dengan exit code 1 dan mencantumkan rincian entitas rusak.


## 2. Prosedur Penanganan Kejadian Luar Biasa (KLB) & Lonjakan Kapasitas (Surge Capacity)

### 2.1 Skenario Lonjakan Kasus Demam Berdarah Dengue (DBD)
1. **Pemicu**: Indeks penularan dengue eksternal `epidemic_dengue_index` pada tabel `hsp_external_factors` melampaui ambang batas 150.
2. **Dampak Operasional**: Kebutuhan mendesak terhadap ranjang rawat inap anak dan dewasa berfasilitas pemantauan trombosit serial.
3. **Langkah Penanganan Terpadu**:
   - Administrator rumah sakit (`rs_admin`) mengeksekusi penyesuaian kapasitas:
     ```bash
     php artisan hospital:adjust-capacity --factor=dengue --reserve-pct=20
     ```
   - Sistem menandai 20% ranjang Kelas 3 dan Kelas 2 yang kosong menjadi `EPIDEMIC_ISOLATION_HOLD`.
   - Modul penjadwalan kamar operasi otomatis menunda jadwal tindakan elektif non-onkologis selama 7 hari kalender.
   - Bank darah rumah sakit menerima instruksi otomatis penyiapan stok Thrombocyte Concentrate (TC) minimal 50 kantong.
4. **Verifikasi Keberhasilan**:
   - Dashboard ranjang `/hospital/beds` menampilkan alokasi ranjang cadangan DBD dengan kode warna kuning.
   - Tidak ada admisi darurat DBD yang ditolak di IGD karena kehabisan tempat tidur.

### 2.2 Skenario Bencana Banjir & Akses Putus (Flood Contingency)
1. **Pemicu**: Akses logistik menuju rumah sakit tergenang air $> 50$ cm selama lebih dari 2 jam (`flood_access_blocked_hours > 2`).
2. **Langkah Respons Kedaruratan**:
   - Sistem mengaktifkan status `DISASTER_STANDBY`.
   - Seluruh rujukan keluar (transfer out) dialihkan ke koordinasi helipad evakuasi medis udara.
   - Konsumsi oksigen cair sentral dan bahan bakar genset darurat diaudit otomatis setiap 60 menit.
   - Pasien rawat inap dengan kesiapan pulang $> 80\%$ dievaluasi untuk percepatan pemulangan terkoordinasi BPBD.

---

## 3. Prosedur Pemulihan Insiden Kegawatan Code Blue & Rekonsiliasi Forensik

### 3.1 Alur Tanggap Darurat Henti Jantung
1. **Menit 0: Deteksi & Alarm**:
   - Perawat bangsal atau sensor telemetri mendeteksi henti napas/jantung.
   - Panggilan Code Blue diaktifkan; sirine visual dan audio di koridor menyala.
   - Perawat pertama memulai kompresi dada (CPR berkualitas tinggi, kedalaman 5-6 cm, kecepatan 100-120 cpm).
2. **Menit 2: Eskalasi Dokter Jaga & Defibrilator**:
   - Jika tombol konfirmasi belum ditekan dalam 120 detik, sistem melakukan panggilan cito ke dokter jaga bangsal.
   - Crash cart dan defibrilator tiba di lokasi. Analisis irama jantung dilakukan (*Shockable: VF/VT* vs *Non-Shockable: Asystole/PEA*).
3. **Menit 5: Kehadiran Tim Resusitasi Lanjutan (ACLS)**:
   - Dokter anestesi/intensivis dan tim resusitasi mengambil alih airway definitif (intubasi ETT) dan akses vaskular/intraosseous.
   - Pemberian epinefrin 1 mg IV diulang setiap 3-5 menit sesuai algoritma ACLS.
4. **Pasca Resusitasi (ROSC) & Audit Trail**:
   - Setelah sirkulasi spontan kembali (ROSC), pasien segera dipindahkan ke ICU.
   - Seluruh intervensi, durasi kompresi, dan dosis obat dimasukkan ke dalam `CodeBlueIncidentDTO`.
   - Sistem mengeksekusi `CriticalIncidentReplayEngine` untuk memverifikasi rantai integritas audit forensik.

---

## 4. Runbook Kegagalan Integrasi SatuSehat FHIR Kemenkes

### 4.1 Gejala Masalah
- Log aplikasi mencatat kegagalan kirim ke gateway SatuSehat (`HTTP 401 Unauthorized` atau `HTTP 422 Unprocessable Entity`).
- Antrean sinkronisasi bundle FHIR menumpuk di antrean `hsp_fhir_outbox`.

### 4.2 Prosedur Diagnostik & Perbaikan
1. Periksa masa berlaku OAuth2 Bearer Token Kemenkes:
   ```bash
   php artisan satusehat:token-refresh
   ```
2. Validasi kesesuaian payload resource terhadap Profil Standar Kemenkes RI:
   - Pastikan NIK pasien terdaftar dan memiliki IHS Number valid.
   - Pastikan kode diagnosis ICD-10 sesuai dengan kamus SatuSehat (tidak menggunakan kode blok umum).
   - Jalankan aksi verifikasi kepatuhan:
     ```php
     $validator = app(ValidateSatuSehatFHIRComplianceAction::class);
     $result = $validator->execute($fhirPayload);
     ```
3. Kirim ulang antrean tertunda secara batch:
   ```bash
   php artisan satusehat:retry-outbox --limit=50
   ```

---

## 5. Pemeliharaan Integritas Paspor Medis Append-Only (UU 27/2022)

### 5.1 Pemeriksaan Berkala Tamper Detection
- Setiap hari pukul 02:00 WIB, sistem menjalankan verifikasi integritas rantai hash pada seluruh paspor aktif:
  ```bash
  php artisan passport:verify-integrity
  ```
- Jika terdeteksi anomali pada paspor pasien tertentu:
  1. Status paspor seketika diubah menjadi `COMPROMISED`.
  2. Sistem mengirimkan alert keamanan prioritas tinggi ke Pejabat Pelindung Data Pribadi (DPO) rumah sakit.
  3. Akses penulisan ke berkas medis dibekukan sementara hingga verifikasi arsip fisik rekam medis manual selesai dilakukan.
