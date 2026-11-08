# Buku Panduan Otomasi IRROPS (Irregular Operations) & Perlindungan Penumpang

## 1. Definisi Operasional & Skala Kejadian Disrupsi

Disrupsi operasional dalam dunia penerbangan (*Irregular Operations* atau disingkat IRROPS) adalah penyimpangan signifikan dari jadwal penerbangan terpublikasi yang disebabkan oleh faktor internal maskapai, faktor eksternal tak terduga, atau force majeure.

### 1.1. Taksonomi Sumber Penyebab Disrupsi
1. **Faktor Teknis Operasional (Airline Controllable)**:
   - Kerusakan mekanis mendadak pada pesawat (*Aircraft on Ground* / AOG).
   - Keterlambatan kru akibat rotasi tugas atau batas maksimum jam terbang (*Flight Time Limitations* / FTL).
   - Gangguan sistem pemesanan atau sistem penanganan bagasi internal.
   *Dampak Hukum*: Maskapai bertanggung jawab penuh memberikan ganti rugi, kompensasi tunai PM 89/2015, makanan, dan akomodasi.
2. **Faktor Cuaca & Kondisi Alam (Force Majeure / Non-Controllable)**:
   - Jarak pandang rendah di bawah batas minimum pendaratan akibat kabut tebal (*below CAT I/II/III minimums*).
   - Siklon tropis, angin samping kencang melebihi batas batas operasional (*crosswind limit*).
   - Letusan gunung berapi yang menyemburkan abu vulkanik ke jalur lintasan udara (VONA - Volcano Observatory Notice for Aviation status RED).
   - Gempa bumi atau tsunami yang melumpuhkan fasilitas bandara.
   *Dampak Hukum*: Maskapai wajib memberikan kepedulian (*duty of care*) berupa konsumsi, opsi pengalihan penerbangan tanpa biaya, dan fasilitasi hotel, namun dibebaskan dari kewajiban ganti rugi tunai Rp300.000,-.
3. **Faktor Pengelolaan Lalu Lintas Udara (Air Traffic Management / Third Party)**:
   - Penutupan sementara landasan pacu (*runway closure*) oleh otoritas bandara karena perbaikan darurat atau insiden pesawat lain.
   - Pembatasan slot udara oleh AirNav Indonesia / ATC (*flow control restrictions*).
   - Serangan siber atau pemadaman listrik total pada infrastruktur navigasi nasional.

---

## 2. Siklus Hidup Kasus Disrupsi (Disruption Case Life-Cycle)

Setiap peristiwa disrupsi dikelola melalui State Machine formal berstandar enterprise dengan 8 status:
1. `detected`: Peristiwa delay atau pembatalan terdeteksi oleh sensor telemetri, input ATC, atau rilis NOTAM resmi.
2. `classified`: Kategori insiden ditetapkan (PM 89 Kategori 1 s.d. 6) dan penentuan tanggung jawab (controllable vs force majeure).
3. `care_dispatched`: Penerbitan paket kepedulian digital (e-voucher makanan/minuman) ke perangkat penumpang.
4. `reaccommodating`: Algoritma A7 mengeksekusi pencarian rute alternatif untuk seluruh penumpang terdampak.
5. `rebooked`: Penumpang berhasil dialokasikan pada penerbangan pengganti atau dikonfirmasi pindah hari dengan akomodasi hotel terpesan.
6. `compensation_settled`: Seluruh klaim kompensasi tunai dan pembayaran voucher telah selesai dikreditkan atau dibayarkan.
7. `closed`: Seluruh kewajiban hukum terpenuhi dan seluruh manifes penumpang telah ditutup.
8. `disputed`: Penumpang mengajukan sanggahan formal atas klasifikasi delay atau penolakan santunan.

---

## 3. Protokol Penanganan Disrupsi Skala Masif: Skenario Erupsi DPS (Gunung Agung VONA Red)

### 3.1. Kronologi Kejadian & Rantai Komando
Ketika Pusat Vulkanologi dan Mitigasi Bencana Geologi (PVMBG) menaikkan status Gunung Agung ke Level IV (Awas) dan menerbitkan VONA Red dengan semburan abu vulkanik mencapai ketinggian Flight Level 320:
1. **T+00 Menit**: NOTAM Bandara Internasional I Gusti Ngurah Rai (DPS/WADD) diterbitkan: *AIRPORT CLOSED FOR ALL OPERATIONS*.
2. **T+05 Menit**: Dispatcher maskapai mengunggah status NOTAM ke dalam sistem. Action `ProcessDisruptionCaseAction` secara otomatis membatalkan seluruh 12 penerbangan terjadwal yang melibatkan DPS dalam 18 jam ke depan.
3. **T+10 Menit**: Sistem mengidentifikasi 1.800 penumpang terdampak pada 12 manifes penerbangan.
4. **T+15 Menit**: Action `DispatchPassengerCareAction` menerbitkan 1.800 voucer makan malam dan sarapan digital serta mengirimkan notifikasi instan.
5. **T+30 Menit**: Algoritma A7 `DisruptedPassengerReaccommodator` menyortir 1.800 penumpang berdasarkan matriks prioritas.
6. **T+45 Menit**: Panggilan integrasi ke modul Hotel dilakukan melalui kontrak `RoomBlockReserver`:
   - 900 kamar hotel (asumsi rata-rata 2 penumpang per kamar untuk rombongan keluarga atau 1 kamar untuk solo business passenger) dibooking secara terpusat pada 5 mitra hotel di Kuta, Nusa Dua, dan Sanur.
   - Panggilan transportasi bus penjemputan bandara-ke-hotel dikoordinasikan.
7. **T+60 Menit**: Sistem asuransi parametrik memicu eksekusi kontrak G7-K29 `ParametricTriggerRecorder`. Klaim asuransi perlindungan penundaan perjalanan dibayarkan langsung ke rekening virtual penumpang tanpa formulir klaim fisik.

---

## 4. Mekanisme Kompensasi & Rekonsiliasi Finansial

### 4.1. Arsitektur E-Voucher Digital
Voucer kompensasi diterbitkan dengan kode QR unik berstandar kriptografis:
- Format Kode: `VCR-CAT{KATEGORI}-{TIMESTAMP}-{RANDOM6}` (e.g. `VCR-CAT3-20261108-8X9P2Q`).
- Masa Berlaku: 24 jam sejak penerbitan.
- Validasi Gerai: Merchant rekanan di bandara memindai QR code via portal mitra yang terhubung langsung ke endpoint `POST /api/v1/aviation/care-vouchers/redeem`.
- Batas Nominal:
  - Kategori 1 (Refreshment): Maksimal Rp25.000,-
  - Kategori 2 (Heavy Snack): Maksimal Rp60.000,-
  - Kategori 3 (Heavy Meal): Maksimal Rp125.000,-
  - Kategori 5 (Tunai PM 89): Tepat Rp300.000,- per penumpang tunai / transfer instan.

### 4.2. Pencatatan Akuntansi Buku Besar Aviasi
Setiap pengeluaran akibat IRROPS dicatat secara otomatis ke dalam pos buku besar:
1. **Beban Kompensasi Delay Kategori 1 s.d. 4 (Konsumsi)**:
   - Debit: `6100-AVI-EXP-CATERING-IRROPS` (Beban Konsumsi Disrupsi Penerbangan)
   - Kredit: `2100-AVI-LIAB-VOUCHER-PAYABLE` (Hutang Voucher Mitra Merchant)
2. **Beban Kompensasi Tunai Kategori 5**:
   - Debit: `6200-AVI-EXP-COMPENSATION-PM89` (Beban Ganti Rugi Keterlambatan Regulasi)
   - Kredit: `1010-AVI-CASH-DISBURSEMENT` (Kas Keluar Operasional / Bank)
3. **Beban Akomodasi Hotel Kategori 6**:
   - Debit: `6300-AVI-EXP-HOTEL-IRROPS` (Beban Akomodasi Penumpang Disrupsi)
   - Kredit: `2200-AVI-LIAB-HOTEL-PAYABLE` (Hutang Mitra Hotel)

---

## 5. Audit Kepatuhan & Pencegahan Fraud

Untuk memastikan tidak terjadi penyalahgunaan klaim kompensasi atau penerbitan ganda:
1. **Aturan 1 Tiket 1 Paket Care**:
   Setiap tiket penerbangan hanya berhak menerima maksimal satu paket care per tingkatan durasi penundaan.
2. **Pencocokan Identitas Boarding Pass**:
   Klaim kompensasi tunai Kategori 5 mewajibkan pencocokan nomor paspor / NIK dengan data manifes penumpang awal (`avi_tickets`).
3. **Audit Otomatis Terjadwal**:
   Command `avi:disruption-audit` memeriksa secara berkala konsistensi total voucher yang diterbitkan dengan jumlah penumpang pada kasus disrupsi terkait, memastikan tidak ada voucher mengambang yang tidak terhubung dengan manifes sah.


---

## 6. Skenario Uji Penerimaan & Simulasi Stres Operasional (Edge Cases)

### 6.1. Kasus Batas 1: Disrupsi Multi-Leg dengan Koneksi Internasional Kritis
- **Deskripsi**: Penumpang memegang tiket rute Denpasar (DPS) - Jakarta (CGK) - Tokyo (NRT). Segmen DPS-CGK mengalami keterlambatan 140 menit akibat kendala hidrolik flap pada pesawat B737-800. Selisih waktu transit di CGK yang semula 120 menit terpangkas menjadi -20 menit (pasti tertinggal penerbangan internasional CGK-NRT).
- **Penanganan Sistem**:
  1. Deteksi otomatis *Minimum Connecting Time* (MCT) di CGK (standar MCT T2 ke T3 = 90 menit).
  2. Evaluasi pinalti koneksi oleh A7 `DisruptedPassengerReaccommodator`: Penumpang dinaikkan ke bobot prioritas tertinggi karena membawa kupon penerbangan internasional bernilai tinggi.
  3. Rebooking otomatis ke opsi penerbangan non-stop DPS-NRT milik maskapai mitra codeshare atau penerbangan alternatif berikutnya dengan persetujuan otorisasi interline endorsement.
  4. Penyediaan kamar hotel transit di bandara transit CGK via `RoomBlockReserver` bila jadwal baru berangkat keesokan harinya.

### 6.2. Kasus Batas 2: Disrupsi Massal Serentak di 3 Hub Utama
- **Deskripsi**: Badai tropis siklon melanda pesisir barat Sumatra dan Selat Sunda, berdampak simultan pada Bandara CGK, HLP, dan BDO. 45 penerbangan tertunda dalam rentang 3 jam.
- **Strategi Beban Sistem**:
  1. Antrean asinkronus menggunakan Laravel Queue (`AviationIrropsProcessJob`).
  2. Transaksi terisolasi pada tingkat kasus per nomor penerbangan untuk menghindari *database deadlock*.
  3. Pemanggilan `RoomBlockReserver` dilakukan secara agregat (*batch room reservation*) per klaster hotel terdekat untuk meminimalkan beban I/O jaringan mitra.

---

## 7. Referensi Kode Kesalahan & Penanganan Eksepsi (Exception Mapping)

| Kode Kesalahan | Kelas Eksepsi | Tindakan Perbaikan Otomatis |
|---|---|---|
| `ERR_IRROPS_VOUCHER_EXPIRED` | `VoucherExpiredException` | Tolak klaim di kasir merchant, sarankan penumpang memperbarui via app |
| `ERR_REACCOMMODATION_NO_SEATS` | `NoAvailableSeatsException` | Eskalasi ke antrean cadangan hotel & jadwalkan ke hari $D+1$ |
| `ERR_PARAMETRIC_DUPLICATE_CLAIM` | `DuplicateParametricClaimException` | Abaikan klaim duplikat, pertahankan status klaim aktif awal |
| `ERR_CODESHARE_SPLIT_MISMATCH` | `InvalidRevenueSplitException` | Bekukan proses settlement hingga parameter basis-poin diverifikasi |
| `ERR_LOYALTY_INSUFFICIENT_MILES` | `InsufficientMilesBalanceException` | Gagalkan transaksi penukaran tiket penghargaan |
