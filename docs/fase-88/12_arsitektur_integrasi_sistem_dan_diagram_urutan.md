# Arsitektur Integrasi Sistem & Diagram Urutan Interaksi (Sequence Diagrams)

## 1. Arsitektur Komponen Terintegrasi Fase 88

Sistem informasi rumah sakit Fase 88 menghubungkan enam domain sub-sistem utama dalam satu kesatuan arsitektur yang modular:
1. **Modul Pelayanan Klinis (Clinical Care Core)**: Pendaftaran pasien, triase IGD (ESI), rekam medis elektronik, kamar bedah, dan farmasi.
2. **Modul Koding Medis & E-Klaim (Grouper Engine)**: Validasi diagnosis ICD-10, prosedur ICD-9-CM, pengelompokan MDC, dan estimasi tarif prospektif INA-CBG.
3. **Modul Interoperabilitas Penjaminan (Insurance Gateway / G7-K31)**: Komunikasi terenkripsi mTLS ke TPA asuransi swasta untuk penerbitan surat jaminan cashless.
4. **Modul Siklus Pendapatan & Waterfall (O2C & CoB Engine)**: Penyusunan tagihan folio item, alokasi bertingkat koordinasi manfaat, dan penanganan sengketa.
5. **Modul Pengawasan IoT Fisik (Cold Chain & Medical Gas Telemetry)**: Pemantauan sensor suhu pendingin vaksin, kalkulasi MKT kinetik, dan tekanan pipa gas medis sentral.
6. **Modul Kepatuhan Lingkungan & Kustodi Kriptografis (Festronik KLHK)**: Pencatatan penimbangan limbah B3, rantai hash SHA-256 anti-tamper, dan sertifikat insinerasi.

---

## 2. Diagram Urutan 1: Admisi Pasien & Otorisasi Jaminan Asuransi Swasta (G7-K31)

Diagram urutan berikut menggambarkan alur interaksi saat pasien asuransi swasta mendaftar rawat inap:

```
Pasien / Keluarga        Petugas Admisi RS            Modul Hospital (Billing)        Modul Insurance (K31)          Server TPA Asuransi
       |                        |                                |                              |                            |
       |--- 1. Serahkan Kartu ->|                                |                              |                            |
       |    Asuransi & KTP      |--- 2. Daftarkan Pasien ------->|                              |                            |
       |                        |    & Input Estimasi Biaya      |--- 3. requestGuarantee() --->|                            |
       |                        |                                |       (GuaranteeRequestDTO)  |--- 4. Enkripsi API HTTPS ->|
       |                        |                                |                              |       Kirim Data Polis     |
       |                        |                                |                              |<-- 5. Setujui Jaminan -----|
       |                        |                                |<-- 6. GuaranteeResponseDTO --|       (Approved Limit)     |
       |                        |                                |       Status: APPROVED       |                            |
       |<-- 8. Tanda Tangan ----|<-- 7. Cetak Initial GL --------|                              |                            |
       |    Formulir Admisi     |                                |                              |                            |
       |                        |                                |                              |                            |
```

Keterangan Tahapan:
1. Pasien menyerahkan kartu kepesertaan asuransi dan surat pengantar rawat inap dari dokter spesialis.
2. Petugas admisi mendaftarkan pasien ke dalam sistem SIRS dan memilih penjamin asuransi komersial rekanan.
3. Modul `Hospital` memanggil method `requestGuarantee()` pada kontrak `CashlessGuaranteeRequester` di modul `Insurance`.
4. Modul `Insurance` meneruskan permohonan ke API gateway TPA dengan tanda tangan digital HMAC-SHA256.
5. Server TPA memvalidasi keaktifan polis dan menyetujui penjaminan awal (*Initial Guarantee Letter*).
6. Modul `Insurance` mengembalikan `GuaranteeResponseDTO` yang memuat nomor referensi GL dan batas limit yang disetujui.
7. Petugas mencetak surat jaminan awal dan pasien diarahkan langsung ke kamar rawat inap tanpa uang muka.

---

## 3. Diagram Urutan 2: Koding Medis, Pengelompokan INA-CBG & Eksekusi Waterfall

Diagram urutan berikut menggambarkan proses saat pasien dinyatakan sembuh dan tagihan diselesaikan:

```
Dokter DPJP             Perawat Bangsal             Koder Medis                Billing / CoB Service        Sistem Akuntansi RS
     |                         |                         |                               |                          |
     |-- 1. Discharge Order -->|                         |                               |                          |
     |                         |-- 2. Kunci Episode ---->|                               |                          |
     |                         |      (Status: CLOSED)   |-- 3. Input Koding ICD-10 ---->|                          |
     |                         |                         |      & Prosedur ICD-9-CM      |-- 4. Hitung INA-CBG ---->|
     |                         |                         |                               |      (Status: VERIFIED)  |
     |                         |                         |                               |-- 5. Eksekusi Waterfall->|
     |                         |                         |                               |      Layer 1: BPJS       |
     |                         |                         |                               |      Layer 2: Asuransi   |
     |                         |                         |                               |      Layer 3: OOP        |
     |                         |                         |                               |<-- 6. Waterfall Balance -|
     |                         |                         |                               |                          |
     |                         |                         |                               |-- 7. Post Jurnal Buku -->|
     |                         |                         |                               |      (Status: SETTLED)   |
     |                         |                         |                               |                          |
```

Keterangan Tahapan:
1. Dokter Penanggung Jawab Pelayanan (DPJP) menandatangani resume medis pemulangan (*discharge summary*).
2. Perawat bangsal menutup transaksi harian, mengunci `BillingEpisode` menjadi status `CLOSED`.
3. Koder medis mereview rekam medis elektronik dan memasukkan diagnosis utama, diagnosis sekunder, serta prosedur operasi.
4. Mesin `InaCbgClinicalGrouperEngine` mengelompokkan data klinis ke dalam kode INA-CBG spesifik beserta tarif regionalnya.
5. Layanan `CoordinationOfBenefitsWaterfallService` mengeksekusi urutan penyerapan pembiayaan: paket dasar diserap BPJS, ekses kenaikan kelas diserap asuransi swasta K31, dan sisa ditagihkan ke kasir.
6. Sistem memverifikasi bahwa total alokasi penjaminan sama persis dengan total rincian tagihan (Invarian INV-88-02).
7. Transaksi diposting ke 27 akun `HospitalAccounts` dan episode tagihan berganti status menjadi `SETTLED`.

---

## 4. Diagram Urutan 3: Pemantauan Rantai Dingin IoT & Karantina Obat Otomatis

Diagram urutan berikut menggambarkan respon otomatis sistem terhadap insiden kenaikan suhu lemari es obat:

```
Sensor Suhu IoT PT100       ColdChainMonitoringService       Tabel hsp_branch_inventory_lots      Instalasi Farmasi RS
         |                               |                                      |                           |
         |--- 1. Telemetri (14.8 °C) --->|                                      |                           |
         |       Interval 5 Menit        |--- 2. Evaluasi Ambang Kritis ------->|                           |
         |                               |       (Excursion Detected!)          |                           |
         |                               |--- 3. Kunci Lot Inventaris --------->|                           |
         |                               |       SET is_quarantined = true      |                           |
         |                               |                                      |                           |
         |                               |--- 4. Kirim Alarm Notifikasi K3RS ------------------------------>|
         |                               |                                      |                           |--- 5. Blokir Peresepan Obat
         |                               |                                      |                           |       Dari Lemari Tersebut
         |                               |                                      |                           |
         |                               |<-- 6. Uji Laboratorium Selesai ----------------------------------|
         |                               |       releaseFromQuarantine()        |                           |
         |                               |--- 7. Buka Karantina --------------->|                           |
         |                               |       SET is_quarantined = false     |                           |
         |                               |                                      |                           |<-- 8. Stok Aktif Kembali
```

---

## 5. Diagram Urutan 4: Kustodi Manifes Festronik Limbah Medis B3

Diagram urutan berikut menggambarkan pelacakan limbah medis B3 menggunakan rantai hash SHA-256 yang saling mengunci:

```
Perawat Ruangan ICU          Petugas TPS Internal RS          Transporter Truk B3          Fasilitas Insinerator KLHK
         |                               |                               |                             |
         |--- 1. Timbang di Ruangan ---->|                               |                             |
         |    (W_ward, Buat Hash_0)      |                               |                             |
         |                               |--- 2. Timbang Ulang di TPS -->|                             |
         |                               |    (W_tps, Verifikasi Hash_0, |                             |
         |                               |     Buat Hash_1)              |                             |
         |                               |                               |--- 3. Serah Terima Truk --->|
         |                               |                               |    (W_truck, Verif Hash_1,  |
         |                               |                               |     Buat Hash_2)            |
         |                               |                               |                             |--- 4. Insinerasi 1000 °C
         |                               |                               |                             |    (Terbitkan Sertifikat,
         |                               |                               |                             |     Buat Hash_3 Final)
         |                               |<-- 5. Manifes Terkunci Permanen di Sistem Festronik --------|
```
Rantai hash bertingkat ini menjamin bahwa seluruh data limbah medis tercatat secara abadi (*immutable*) dan tidak dapat dimanipulasi oleh pihak mana pun.

