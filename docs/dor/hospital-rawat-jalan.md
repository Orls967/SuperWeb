# Definition of Ready (DoR) — Vertical Slice Rawat Jalan (Outpatient) Modul Hospital

Dokumen ini mendefinisikan kontrak fungsional, finansial, arsitektur, dan pengujian untuk vertical slice nyata rawat jalan umum rumah sakit dari pendaftaran pasien hingga laporan keuangan harian dan audit rekonsiliasi dua-sumber.

---

## 1. Salinan Keputusan Pemilik (D-1 s.d D-8)

- **D-1 Lini & alur**: Rumah Sakit (modul `Hospital`) — rawat jalan umum dengan dua cara bayar: tunai/non-tunai pribadi (`CASH`) dan penjamin JKN/BPJS (`BPJS`), dengan klaim tarif paket INA-CBG disimulasikan secara deterministik tanpa koneksi ke server BPJS eksternal asli.
- **D-2 Role**: `admin`, `hospital_registration` (pendaftaran & antrian), `doctor` (pemeriksaan, diagnosis ICD-10, resep), `nurse` (triage vital sign, panggil antrian), `pharmacist` (dispensing obat FEFO, verifikasi resep), `cashier` (kasir & pelunasan tagihan), `hospital_finance` (rekap keuangan harian & laporan ledger), `auditor` (baca saja seluruh data dan laporan), `patient` (portal pasien: melihat riwayat kunjungan & tagihan miliknya sendiri). Role yang belum ada di database ditambahkan lewat seeder RBAC idempoten.
- **D-3 Uang**: Integer rupiah (minor unit = 1 Rupiah penuh), tanpa float; tarif bersumber dari master tarif berversi (`berlaku_mulai` / `effective_from`); tidak ada pembulatan mengambang (semua tarif dan perhitungan nominal bertipe integer).
- **D-4 Ledger**: Akun double-entry mencakup pendapatan jasa dokter, pendapatan farmasi, HPP obat, persediaan farmasi, piutang pasien, piutang BPJS, selisih tarif paket klaim BPJS, beban klaim BPJS ditolak, dan kas; seluruh akun didefinisikan secara resmi di `modules/Hospital/Ledger/HospitalAccounts.php`.
- **D-5 Stok obat**: Berkurang hanya saat obat diserahkan/dispensing oleh apoteker (bukan saat diresepkan oleh dokter); stok fisik tidak boleh negatif; alokasi batch/lot menggunakan metode FEFO (*First Expired First Out*); lot kedaluwarsa atau dikarantina tidak boleh diserahkan.
- **D-6 Data medis**: Diagnosis ICD-10 dan catatan dokter disimpan terenkripsi (cast encrypted AES-256); hanya dokter/perawat yang menangani kunjungan tersebut, admin, dan pasien bersangkutan yang memiliki wewenang membaca data medis tersebut.
- **D-7 Antrian**: Nomor antrian berurutan per poli per tanggal (reset ke 1 setiap hari baru), tidak boleh lompat dan tidak boleh ganda meskipun dua petugas pendaftaran melakukan pendaftaran secara bersamaan (*concurrency-safe via database atomic lock*).
- **D-8 Laporan & Audit**: Rekap harian per poli (jumlah kunjungan pasien, total pendapatan per cara bayar) yang nilainya terbukti seimbang dan sama dengan saldo mutasi ledger modul Banking pada hari tersebut; dilengkapi artisan command `hospital:outpatient-audit` yang lolos bersih (*exit code 0*) pada data valid dan gagal (*exit code != 0*) bila ditemukan manipulasi/korupsi data satu baris di database.

---

## 2. Tujuan Bisnis

Menghadirkan alur layanan rawat jalan fasilitas kesehatan modern yang terpadu dan tanpa friksi, mencegah kebocoran pendapatan rumah sakit, memastikan akuntabilitas medis dan finansial sesuai kaidah *double-entry bookkeeping*, menjamin kerahasiaan rekam medis pasien sesuai regulasi perlindungan data pribadi (UU PDP / HIPAA), serta menyediakan rekonsiliasi otomatis harian antara pencatatan transaksi operasional dan pembukuan buku besar (general ledger).

---

## 3. Modul Pemilik & Prefiks Tabel

- **Modul Pemilik**: `Hospital` (`modules/Hospital`)
- **Prefiks Tabel Resmi**: `hsp_` (terdaftar di `config/modules.php`)
- **Tabel Utama Alur Rawat Jalan**:
  - `hsp_polyclinics`: Master data poliklinik / unit rawat jalan.
  - `hsp_doctors`: Master dokter dan penugasan poliklinik.
  - `hsp_tariff_versions`: Master tarif tindakan dan jasa berversi.
  - `hsp_queues`: Manajemen tiket antrian harian per poli.
  - `hsp_outpatient_encounters`: Data kunjungan rawat jalan, triage, pemeriksaan, dan catatan medis terenkripsi.
  - `hsp_outpatient_prescriptions`: Resep obat elektronik rawat jalan.
  - `hsp_outpatient_dispensings`: Penyerahan fisik obat, alokasi FEFO lot, dan pencatatan HPP.
  - `hsp_outpatient_invoices`: Faktur tagihan jasa dokter dan farmasi, status cara bayar (CASH / BPJS).
  - `hsp_outpatient_payments`: Bukti pembayaran kasir tunai dengan *idempotency protection*.
  - `hsp_bpjs_claims`: Berkas klaim INA-CBG dan adjudikasi penjamin BPJS Kesehatan.

---

## 4. Entitas & State Machine Kunjungan (Encounter)

Siklus hidup kunjungan rawat jalan (`hsp_outpatient_encounters`):

```
[terdaftar] ──(Panggil)──> [dipanggil] ──(Triage & Periksa)──> [diperiksa]
     │                                                                │
     └──(Batal)──> [batal]                                            ├──(Buat Resep)──> [resep_dibuat]
                                                                      │                       │
                                                                      │                       └──(Serahkan Obat)──> [obat_diserahkan]
                                                                      │                                                    │
                                                                      └───────────────┬────────────────────────────────────┘
                                                                                      │
                                                                                 (Terbitkan Tagihan)
                                                                                      │
                                                                                      ▼
                                                                                  [ditagih]
                                                                                      │
                                                                    ┌─────────────────┴─────────────────┐
                                                                    ▼ (Bayar Tunai)                     ▼ (Klaim BPJS)
                                                                 [lunas]                            [diklaim]
                                                                    │                                   │
                                                                    └─────────────────┬─────────────────┘
                                                                                      │ (Selesai Kunjungan)
                                                                                      ▼
                                                                                  [selesai]
```

### Aturan Transisi Status
1. `terdaftar`: Pasien berhasil didaftarkan di poli tujuan dan memperoleh nomor antrian harian.
2. `batal`: Hanya dapat dibatalkan saat status masih `terdaftar` (sebelum dipanggil/diperiksa). Kunjungan yang sudah diperiksa tidak dapat dibatalkan.
3. `dipanggil`: Pasien dipanggil oleh perawat poli ke ruang pemeriksaan.
4. `diperiksa`: Dokter memasukkan tanda vital triage, diagnosis ICD-10 terenkripsi, dan catatan medis.
5. `resep_dibuat`: Dokter menuliskan instruksi resep obat elektronik (stok obat gudang belum berkurang).
6. `obat_diserahkan`: Petugas farmasi menyerahkan obat ke pasien berbasis FEFO, mengurangi stok lot fisik, dan mencatat HPP.
7. `ditagih`: Faktur tagihan dihitung dari tarif konsultasi dokter berlaku + biaya obat farmasi.
8. `lunas` (Pasien Umum/Tunai): Pembayaran kasir telah diterima penuh dan tercatat di kas.
9. `diklaim` (Pasien BPJS): Berkas klaim INA-CBG telah terbit dan dicatat sebagai piutang BPJS.
10. `selesai`: Seluruh rangkaian layanan medis, kefarmasian, dan administratif selesai secara paripurna.

---

## 5. Posting Ledger Per Kejadian

Setiap transaksi finansial memicu pencatatan jurnal berpasangan (*balanced double-entry*) ke modul `Banking` melalui helper `Modules\Banking\Support\Posting::lines()`.

### Contoh Kasus Transaksi Nyata
- **Pasien**: Budi Santoso (Rawat Jalan Poli Penyakit Dalam)
- **Dokter**: dr. Bambang, Sp.PD
- **Tarif Jasa Dokter**: Rp 150.000 (sesuai `hsp_tariff_versions` aktif)
- **Obat**: Paracetamol 500mg 10 tablet @ Rp 2.000 = Rp 20.000 (HPP per tablet Rp 1.200 = Rp 12.000)
- **Total Nilai Riil RS**: Rp 170.000

---

### Skenario A: Pasien Tunai / Pembayaran Kasir Pribadi

#### Kejadian 1: Penyerahan Obat (Dispensing FEFO oleh Farmasi)
Stok obat keluar dari rak inventaris apotek, diakui sebagai Beban HPP Farmasi:
| No | Akun Buku Besar | Posisi | Nominal (IDR) | Keterangan |
|---|---|---|---|---|
| 1 | `hsp:cogs_pharmacy:IDR` | DEBIT | 12.000 | Pengakuan Beban Pokok Penjualan Obat |
| 2 | `hsp:inventory_pharmacy:IDR` | KREDIT | 12.000 | Pengurangan Nilai Aset Persediaan Obat |

#### Kejadian 2: Penerbitan Tagihan Rawat Jalan (Billing)
Pengakuan piutang pasien terhadap pendapatan jasa dokter dan farmasi:
| No | Akun Buku Besar | Posisi | Nominal (IDR) | Keterangan |
|---|---|---|---|---|
| 1 | `hsp:patient_receivable:IDR` | DEBIT | 170.000 | Piutang Pelayanan Pasien Rawat Jalan |
| 2 | `hsp:doctor_fee_revenue:IDR` | KREDIT | 150.000 | Pendapatan Operasional Jasa Dokter |
| 3 | `hsp:pharmacy_revenue:IDR` | KREDIT | 20.000 | Pendapatan Operasional Farmasi |

#### Kejadian 3: Pelunasan di Kasir (Pembayaran Kas)
Kasir menerima pembayaran tunai sebesar Rp 170.000 (atau uang diterima Rp 200.000 dengan kembalian Rp 30.000):
| No | Akun Buku Besar | Posisi | Nominal (IDR) | Keterangan |
|---|---|---|---|---|
| 1 | `bank:cash:IDR` | DEBIT | 170.000 | Kas Fisik Kasir Rumah Sakit Bertambah |
| 2 | `hsp:patient_receivable:IDR` | KREDIT | 170.000 | Piutang Pasien Lunas Terbayar |

---

### Skenario B: Pasien JKN / Penjamin BPJS (Simulasi Tarif Paket INA-CBG)

Berdasarkan regulasi JKN, rumah sakit menagihkan tarif paket INA-CBG ke BPJS Kesehatan. Pasien rawat jalan tidak dikenakan iur biaya (*zero co-pay*).

#### Kejadian 1: Penyerahan Obat (Dispensing FEFO)
Sama dengan tunai:
| No | Akun Buku Besar | Posisi | Nominal (IDR) | Keterangan |
|---|---|---|---|---|
| 1 | `hsp:cogs_pharmacy:IDR` | DEBIT | 12.000 | Beban HPP Obat Farmasi |
| 2 | `hsp:inventory_pharmacy:IDR` | KREDIT | 12.000 | Pengurangan Persediaan Farmasi |

#### Kejadian 2: Pengajuan Klaim Paket INA-CBG (Misal Paket = Rp 180.000)
Tarif paket INA-CBG disetujui sebesar Rp 180.000, terdapat surplus selisih tarif Rp 10.000 terhadap tarif RS Rp 170.000:
| No | Akun Buku Besar | Posisi | Nominal (IDR) | Keterangan |
|---|---|---|---|---|
| 1 | `hsp:bpjs_receivable:IDR` | DEBIT | 180.000 | Piutang Klaim JKN ke BPJS Kesehatan |
| 2 | `hsp:doctor_fee_revenue:IDR` | KREDIT | 150.000 | Alokasi Jasa Medis Dokter |
| 3 | `hsp:pharmacy_revenue:IDR` | KREDIT | 20.000 | Alokasi Pendapatan Farmasi |
| 4 | `hsp:bpjs_claim_variance:IDR` | KREDIT | 10.000 | Pendapatan Surplus Selisih Tarif INA-CBG |

*(Jika paket INA-CBG lebih rendah dari tarif RS, misal Rp 160.000, akun `hsp:bpjs_claim_variance:IDR` didebit Rp 10.000 sebagai beban defisit paket klaim).*

#### Kejadian 3: Klaim Ditolak / Tidak Layak Klaim oleh BPJS (Dispute/Rejection)
Jika verifikator BPJS menolak klaim tersebut, dilakukan jurnal pembalik dan pengakuan beban klaim ditolak:
| No | Akun Buku Besar | Posisi | Nominal (IDR) | Keterangan |
|---|---|---|---|---|
| 1 | `hsp:bpjs_rejected_claim:IDR` | DEBIT | 180.000 | Beban Kerugian Klaim BPJS Ditolak |
| 2 | `hsp:bpjs_receivable:IDR` | KREDIT | 180.000 | Penghapusan Piutang BPJS Tak Tertagih |

---

## 6. Event Domain yang Dipancarkan

Setiap aksi bisnis penting memancarkan event resmi di namespace `Modules\Hospital\Domain\Events\`:
1. `PatientRegisteredEvent` (`$encounterId`, `$queueNumber`, `$nikBlindIndex`)
2. `QueueTicketCalledEvent` (`$queueId`, `$calledByUserId`)
3. `EncounterTriageRecordedEvent` (`$encounterId`, `$nurseUserId`)
4. `EncounterExaminedEvent` (`$encounterId`, `$doctorId`, `$icd10Encrypted`)
5. `PrescriptionDispensedEvent` (`$prescriptionId`, `$dispensedLots`, `$totalCogsIdr`)
6. `OutpatientInvoiceGeneratedEvent` (`$invoiceId`, `$totalAmountIdr`, `$paymentMethod`)
7. `OutpatientPaymentReceivedEvent` (`$paymentId`, `$amountIdr`, `$idempotencyKey`)
8. `BpjsClaimSubmittedEvent` (`$claimId`, `$inaCbgCode`, `$packageTariffIdr`)
9. `BpjsClaimAdjudicatedEvent` (`$claimId`, `$status`, `$adjudicatedAt`)

---

## 7. Rute, Antarmuka UI, Menu, Role & Policy

### Matriks Otorisasi Rute (RBAC & Policy)
| Rute HTTP | Metode | Nama Rute | Role Berhak | Deskripsi Fungsional |
|---|---|---|---|---|
| `/hospital/outpatient/registration` | GET | `hospital.outpatient.registration.index` | `admin`, `hospital_registration` | Formulir pendaftaran pasien & cetak antrian |
| `/hospital/outpatient/registration` | POST | `hospital.outpatient.registration.store` | `admin`, `hospital_registration` | Simpan pendaftaran, generate tiket antrian |
| `/hospital/outpatient/queue-board` | GET | `hospital.outpatient.queue.index` | `admin`, `nurse`, `doctor`, `hospital_registration` | Layar antrian poli rawat jalan |
| `/hospital/outpatient/queue/{queue}/call` | POST | `hospital.outpatient.queue.call` | `admin`, `nurse`, `doctor` | Panggil antrian pasien ke ruang periksa |
| `/hospital/outpatient/encounters/{encounter}/triage` | POST | `hospital.outpatient.encounters.triage` | `admin`, `nurse` | Input tanda vital (triage) perawat |
| `/hospital/outpatient/encounters/{encounter}/examine` | POST | `hospital.outpatient.encounters.examine` | `admin`, `doctor` | Input diagnosis ICD-10 & rekam medis dokter |
| `/hospital/outpatient/encounters/{encounter}/prescribe` | POST | `hospital.outpatient.encounters.prescribe` | `admin`, `doctor` | Pembuatan resep elektronik rawat jalan |
| `/hospital/outpatient/pharmacy` | GET | `hospital.outpatient.pharmacy.index` | `admin`, `pharmacist` | Antrian resep & dashboard farmasi |
| `/hospital/outpatient/prescriptions/{prescription}/dispense` | POST | `hospital.outpatient.pharmacy.dispense` | `admin`, `pharmacist` | Penyerahan obat, alokasi FEFO, potong stok |
| `/hospital/outpatient/cashier` | GET | `hospital.outpatient.cashier.index` | `admin`, `cashier`, `hospital_finance` | Daftar tagihan aktif rawat jalan |
| `/hospital/outpatient/invoices/{invoice}/pay` | POST | `hospital.outpatient.cashier.pay` | `admin`, `cashier` | Pembayaran tunai kasir (Wajib Idempotency-Key) |
| `/hospital/outpatient/invoices/{invoice}/claim-bpjs` | POST | `hospital.outpatient.bpjs.claim` | `admin`, `cashier`, `hospital_finance` | Pengajuan klaim INA-CBG BPJS |
| `/hospital/outpatient/claims/{claim}/adjudicate` | POST | `hospital.outpatient.bpjs.adjudicate` | `admin`, `hospital_finance` | Simulasi persetujuan/penolakan klaim BPJS |
| `/hospital/outpatient/reports/daily` | GET | `hospital.outpatient.reports.daily` | `admin`, `hospital_finance`, `auditor` | Laporan rekapitulasi harian per poli |
| `/hospital/outpatient/portal` | GET | `hospital.outpatient.portal.index` | `patient` | Portal pasien melihat riwayat & tagihan sendiri |

---

## 8. Command Audit Dua-Sumber & Fixture Korupsi

- **Command**: `php artisan hospital:outpatient-audit [--date=YYYY-MM-DD]`
- **Mekanisme Verifikasi**:
  1. **Sumber 1 (Operasional)**: Menghitung agregat dari tabel `hsp_outpatient_invoices` dan `hsp_outpatient_payments` pada tanggal yang diuji (total penerimaan tunai, total tagihan terbayar, total klaim BPJS).
  2. **Sumber 2 (Buku Besar Banking)**: Menghitung mutasi debit/kredit pada akun `bank:cash:IDR`, `hsp:patient_receivable:IDR`, `hsp:bpjs_receivable:IDR`, `hsp:doctor_fee_revenue:IDR`, `hsp:pharmacy_revenue:IDR`, `hsp:cogs_pharmacy:IDR`, dan `hsp:inventory_pharmacy:IDR` pada tanggal yang sama.
  3. **Pencocokan**: Setiap selisih > 0 Rupiah memicu pencatatan rekonsiliasi galat dan command mengembalikan *exit code 1* (FAILURE). Jika seluruh saldo identik, command mengembalikan *exit code 0* (SUCCESS).
- **Fixture Korupsi (Uji Integritas Audit)**:
  Dalam test acceptance, satu baris tagihan pada `hsp_outpatient_invoices` dimanipulasi nominalnya secara langsung via SQL `DB::table()->update()` tanpa melalui transaksi ledger. Command `hospital:outpatient-audit` wajib mendeteksi diskrepansi tersebut dan keluar dengan status non-zero.

---

## 9. Daftar Acceptance Tests (Minimal 40 Tests)

Diorganisasi ke dalam 8 kelompok file test di `tests/Acceptance/Hospital/`:

### 1. `OutpatientRegistrationAndQueueTest` (Pendaftaran & Antrian)
1. `pasien baru dapat didaftarkan dengan NIK terenkripsi dan blind index unik`
2. `pendaftaran pasien dengan NIK yang sama ditolak agar tidak terjadi duplikasi rekam medis`
3. `pasien lama dapat dicari kembali menggunakan blind index NIK tanpa dekripsi massal`
4. `nomor antrian diberikan secara berurutan mulai dari 1 untuk setiap poli pada hari yang sama`
5. `dua pendaftaran bersamaan di poli yang sama menghasilkan nomor antrian berurutan tanpa bentrok atau lompat`
6. `nomor antrian poli otomatis ter-reset kembali ke 1 pada pergantian tanggal`
7. `pembatalan antrian hanya diizinkan selama pasien masih berstatus terdaftar`
8. `antrian yang sudah dipanggil atau diperiksa tidak dapat dibatalkan`

### 2. `OutpatientExaminationAndTriageTest` (Pemeriksaan Medis & Triage)
9. `perawat poli dapat mencatat tanda-tanda vital pasien pada tahap triage`
10. `perawat poli ditolak saat mencoba memasukkan diagnosis medis atau rekam pemeriksaan dokter`
11. `hanya dokter yang bertugas pada poli dan jadwal kunjungan yang dapat mengisi diagnosis dan catatan medis`
12. `dokter dari poli lain ditolak saat mencoba mengubah rekam medis kunjungan pasien yang bukan tanggung jawabnya`
13. `diagnosis ICD-10 divalidasi format kodenya dan disimpan dalam format terenkripsi pada database`
14. `catatan klinis dokter tersimpan dalam keadaan terenkripsi dan tidak berwujud plaintext di database`
15. `pemeriksaan dokter ditolak jika pasien belum dipanggil dari antrian pendaftaran`

### 3. `OutpatientPharmacyAndFefoTest` (Resep & Dispensing FEFO)
16. `resep obat elektronik rawat jalan hanya dapat diterbitkan oleh dokter pemeriksa`
17. `penerbitan resep obat tidak mengurangi saldo stok fisik di gudang farmasi`
18. `penyerahan obat oleh apoteker memotong stok menggunakan alokasi FEFO kedaluwarsa paling awal`
19. `batch obat yang telah kedaluwarsa dilewati secara otomatis dan tidak diserahkan ke pasien`
20. `penyerahan obat ditolak jika total stok fisik yang tersedia tidak mencukupi permintaan resep`
21. `penyerahan obat memposting jurnal debit beban HPP dan kredit persediaan farmasi secara seimbang`
22. `resep obat yang sudah diserahkan tidak dapat diserahkan kembali untuk mencegah duplikasi pemotongan stok`

### 4. `OutpatientBillingAndCashierTest` (Tagihan & Pembayaran Tunai)
23. `tagihan rawat jalan dihitung secara tepat dari master tarif dokter versi aktif ditambah total biaya obat farmasi`
24. `pembayaran tunai di kasir memposting debit kas dan kredit piutang pasien secara seimbang`
25. `permintaan pembayaran tanpa header Idempotency-Key ditolak dengan status HTTP 422`
26. `pembayaran ulang dengan Idempotency-Key yang sama mengembalikan respons identik tanpa duplikasi posting ledger`
27. `dua transaksi pembayaran berbeda dengan nominal dan waktu yang sama tetap diposting secara terpisah`
28. `pembayaran kasir dengan nominal uang diterima kurang dari jumlah tagihan ditolak`
29. `pembayaran kasir dengan uang lebih menghitung nilai kembalian uang secara presisi`
30. `tagihan rawat jalan yang telah lunas ditolak jika dilakukan proses pembayaran ulang`

### 5. `OutpatientBpjsClaimTest` (Klaim JKN & Adjudikasi BPJS)
31. `pendaftaran pasien jalur BPJS mewajibkan pengisian nomor kartu JKN yang valid`
32. `penerbitan tagihan pasien BPJS menghasilkan pengajuan klaim tarif paket INA-CBG ke akun piutang BPJS`
33. `selisih tarif rumah sakit terhadap tarif paket INA-CBG dibukukan ke akun selisih klaim secara seimbang`
34. `pasien rawat jalan BPJS tidak dikenakan tagihan iur biaya tambahan di kasir`
35. `klaim yang disetujui oleh verifikator BPJS mempertahankan saldo piutang BPJS dan mengubah status menjadi klaim disetujui`
36. `klaim yang ditolak oleh BPJS memicu jurnal pembalik piutang BPJS dan mencatat beban klaim ditolak`

### 6. `OutpatientAccessControlTest` (Kontrol Akses & Keamanan)
37. `pasien yang login ke portal dapat melihat data riwayat kunjungan dan tagihannya sendiri`
38. `pasien A dilarang melihat riwayat kunjungan atau tagihan pasien B dengan respons HTTP 403 atau 404`
39. `petugas kasir dilarang mengakses rekam medis atau diagnosis pasien dan hanya berhak melihat rincian biaya`
40. `auditor memiliki hak baca penuh terhadap laporan dan rekam medis namun ditolak saat melakukan aksi mutasi HTTP POST`
41. `pengguna anonim yang belum terotentikasi otomatis dialihkan ke halaman login`

### 7. `OutpatientReportAndAuditTest` (Laporan Harian & Audit Dua-Sumber)
42. `laporan rekapitulasi harian menyajikan jumlah pasien per poli serta rincian pendapatan tunai dan klaim BPJS`
43. `total angka pendapatan pada laporan rekap harian identik dengan saldo akhir mutasi ledger hari bersangkutan`
44. `artisan command hospital:outpatient-audit menghasilkan exit code 0 saat seluruh data konsisten`
45. `artisan command hospital:outpatient-audit menghasilkan exit code non-zero saat ditemukan baris tagihan termanipulasi`
46. `seluruh transaksi jurnal buku besar rawat jalan memenuhi kaidah double-entry seimbang debit sama dengan kredit`

### 8. `OutpatientUiAndValidationTest` (Antarmuka UI Blade & Penanganan Error)
47. `halaman pendaftaran rawat jalan merespons status HTTP 200 untuk petugas pendaftaran dan admin`
48. `halaman pendaftaran rawat jalan merespons status HTTP 403 untuk role dokter dan apoteker`
49. `kegagalan validasi pada form pendaftaran mengembalikan redirect dengan flash message error bukan HTTP 500`
50. `layar antrian dan form pemeriksaan merespons status HTTP 200 untuk dokter dan perawat`
51. `halaman kasir dan pembayaran merespons status HTTP 200 untuk kasir dan finance`
52. `halaman rekapitulasi harian merespons status HTTP 200 untuk finance dan auditor`

---

## 10. Jalan Pintas yang Dilarang Keras
1. Menggunakan nilai acak / timestamp untuk `Idempotency-Key`.
2. Menyimpan data rekam medis atau NIK dalam format *plaintext*.
3. Mengurangi stok obat pada saat pembuatan resep (stok hanya boleh berkurang saat obat diserahkan/dispensing).
4. Membuat akun buku besar inline di dalam Action class (wajib terpusat di `HospitalAccounts.php`).
5. Membiarkan transaksi kasir tanpa proteksi `Idempotency-Key` (wajib tolak 422).
6. Menggunakan tipe `float` atau desimal untuk kolom moneter IDR (wajib `integer` / `bigInteger`).
7. Memalsukan data laporan dengan angka konstanta statis tanpa query database riil.

---

## 11. Pertanyaan & Asumsi Konservatif (Aturan Klinis & Keuangan)
- **Pertanyaan 1**: Bagaimana penanganan selisih tarif antara tarif riil RS dan tarif paket INA-CBG?
  *Asumsi Konservatif Diterapkan*: Sesuai regulasi BPJS Kesehatan, rumah sakit tidak boleh menarik selisih biaya rawat jalan reguler kepada pasien. Selisih tarif dibukukan ke akun buku besar khusus `hsp:bpjs_claim_variance:IDR` (Kredit jika paket > tarif RS, Debit jika paket < tarif RS), sehingga total jurnal tetap seimbang (*balance*).
- **Pertanyaan 2**: Apakah obat racikan dengan banyak komponen didukung pada tahap ini?
  *Asumsi Konservatif Diterapkan*: Pada vertical slice rawat jalan umum ini, resep berfokus pada obat jadi (non-racikan) terdaftar dalam master obat lot farmasi dengan pemotongan stok FEFO per lot. Dukungan resep racikan multi-komponen dicatat dalam Register Minus untuk fase selanjutnya.

---

## 12. Bukti untuk Dipindahkan ke PROGRESS (M1)

*(Bagian ini akan disalin ke `docs/PROGRESS.md` oleh sesi integrasi berikutnya)*:

```markdown
- [ ] R7.HSP.1 **Vertical Slice Rawat Jalan Terpadu**: Pendaftaran antrian harian per poli (concurrency-safe), triage perawat, pemeriksaan dokter (ICD-10 terenkripsi), resep elektronik, dispensing FEFO farmasi & HPP, billing terpadu tunai/JKN, posting ledger seimbang, portal pasien, rekapitulasi harian, dan artisan command hospital:outpatient-audit dua-sumber.
  Bukti:
    - jenis: fitur
    - commit: (akan diisi commit implementasi)
    - file: modules/Hospital/Application/Actions/RegisterPatientAndIssueQueueAction.php
    - file: modules/Hospital/Application/Actions/ExaminePatientAndDiagnoseAction.php
    - file: modules/Hospital/Application/Actions/DispensePrescriptionFefoAction.php
    - file: modules/Hospital/Application/Actions/ProcessCashPaymentAction.php
    - file: modules/Hospital/Application/Actions/SubmitBpjsClaimAction.php
    - file: modules/Hospital/Console/Commands/AuditOutpatientCommand.php
    - test: tests/Acceptance/Hospital/OutpatientRegistrationAndQueueTest.php
    - test: tests/Acceptance/Hospital/OutpatientExaminationAndTriageTest.php
    - test: tests/Acceptance/Hospital/OutpatientPharmacyAndFefoTest.php
    - test: tests/Acceptance/Hospital/OutpatientBillingAndCashierTest.php
    - test: tests/Acceptance/Hospital/OutpatientBpjsClaimTest.php
    - test: tests/Acceptance/Hospital/OutpatientAccessControlTest.php
    - test: tests/Acceptance/Hospital/OutpatientReportAndAuditTest.php
    - test: tests/Acceptance/Hospital/OutpatientUiAndValidationTest.php
    - akses: route GET /hospital/outpatient/registration [role: admin, hospital_registration]
    - akses: route GET /hospital/outpatient/cashier [role: admin, cashier]
    - akses: route GET /hospital/outpatient/reports/daily [role: admin, hospital_finance, auditor]
    - akses: command hospital:outpatient-audit
```

---

## 13. Register Minus Usulan (6 Kolom)

| ID | Item | Minus | Dampak | Prioritas | Rencana Penyelesaian |
|---|---|---|---|---|---|
| RM-HSP-01 | Integrasi BPJS Eksternal | Klaim INA-CBG menggunakan simulasi perhitungan lokal terstandar, belum menghubungkan webhook VClaim/PCare BPJS eksternal asli | Tidak ada kendala operasional lokal; klaim tetap menghasilkan pembukuan piutang akurat | P3 | Implementasi adapter bridge BPJS TrustMark saat integrasi API eksternal dijadwalkan |
| RM-HSP-02 | Resep Obat Racikan | Resep rawat jalan menangani obat jadi terstandar, belum mendukung formula compounding racikan multi-bahan | Obat yang diresepkan adalah obat jadi dalam kemasan standar | P3 | Penambahan modul formularium racikan di fase pengembangan lanjutan farmasi |
| RM-HSP-03 | Integrasi Bridging SATUSEHAT | Rekam medis terenkripsi lokal, belum mengirim payload FHIR ke Kemenkes SATUSEHAT | Kepatuhan rekam medis lokal terjamin, transmisi data nasional belum aktif | P3 | Penambahan background job pengiriman HL7/FHIR SATUSEHAT |
