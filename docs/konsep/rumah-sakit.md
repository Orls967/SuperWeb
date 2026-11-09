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
