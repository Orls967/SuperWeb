# Panduan Operasional & Konseptual: Airline Network, Loyalty & IRROPS Management

## Bab I: Latar Belakang & Landasan Bisnis Aviasi Komersial

Industri penerbangan modern beroperasi pada marjin keuntungan yang tipis dengan tingkat ketidakpastian yang tinggi. Untuk mempertahankan efisiensi armada dan kepuasan pelanggan, maskapai penerbangan memerlukan integrasi mendalam antara empat pilar komersial:
1. Perencanaan Jaringan & Manajemen Kapasitas (*Network Planning & Fleet Assignment*).
2. Optimasi Pendapatan & Manajemen Hasil (*Yield & Revenue Management*).
3. Kerjasama Aliansi, Penerbangan Bersama (*Codeshare*), dan Program Loyalitas (*Frequent Flyer Program*).
4. Penanganan Disrupsi Operasional Tidak Terencana (*Irregular Operations / IRROPS*).

Dokumen ini memuat spesifikasi komprehensif implementasi domain model, logika bisnis, dan kepatuhan regulasi penerbangan internasional (IATA/ICAO) serta regulasi nasional Indonesia (PM 89/2015).

---

## Bab II: Teori & Algoritma Revenue Management (A6)

### 2.1. Konsep Dasar Fare Buckets & Nesting
Kursi pada setiap pesawat fisik dialokasikan ke dalam beberapa kelas reservasi (*booking classes* atau *fare buckets*). Dalam model *nested reservation*, permintaan untuk kelas tarif berharga tinggi memiliki akses terhadap seluruh inventaris kursi yang tersedia untuk kelas tarif berharga rendah, namun tidak sebaliknya.

Misalkan $C$ adalah total kapasitas kursi kabin pesawat, dan terdapat $m$ kelas tarif $1, 2, \dots, m$ dengan urutan tarif menurun:
$$r_1 > r_2 > \dots > r_m$$

Tingkat proteksi $S_j$ menyatakan jumlah kursi yang dicadangkan secara eksklusif untuk kelas tarif $1, \dots, j$. Dengan demikian, batas pemesanan (*booking limit*) $BL_j$ untuk kelas $j$ adalah:
$$BL_1 = C$$
$$BL_j = \max(0, C - S_{j-1}), \quad \forall j \in \{2, \dots, m\}$$

### 2.2. Algoritma EMSR-b Diskret
Metode EMSR-b (Expected Marginal Seat Revenue versi b) dikembangkan oleh Peter Belobaba (1987) untuk memperkirakan kurva pendapatan marjinal agregat. Algoritma ini menggabungkan permintaan kelas tarif $1$ hingga $j$ menjadi satu distribusi gabungan:
- Total rata-rata permintaan: $\mu_{1..j} = \sum_{k=1}^j \mu_k$
- Total variansi permintaan: $\sigma_{1..j}^2 = \sum_{k=1}^j \sigma_k^2$
- Tarif rata-rata tertimbang:
  $$\bar{r}_j = \frac{\sum_{k=1}^j r_k \mu_k}{\mu_{1..j}}$$

Tingkat proteksi optimal diperoleh dengan menyelesaikan persamaan Littlewood:
$$P(D_{1..j} \le S_j) = 1 - \frac{r_{j+1}}{\bar{r}_j}$$

Dengan asumsi distribusi normal, faktor $z$ dihitung menggunakan inversi fungsi distribusi kumulatif normal standar $\Phi^{-1}$:
$$z_j = \Phi^{-1}\left(1 - \frac{r_{j+1}}{\bar{r}_j}\right)$$
$$S_j = \left\lceil \mu_{1..j} + z_j \cdot \sigma_{1..j} \right\rceil$$

Jika nilai $\frac{r_{j+1}}{\bar{r}_j} \ge 1$ atau data historis menunjukkan volatilitas ekstrem, sistem memberlakukan pengaman (*floor safety*) minimal $S_j \ge \sum_{k=1}^j \mu_k$.

---

## Bab III: Otomasi Penanganan IRROPS (A7 & A9)

### 3.1. Klasifikasi Disrupsi & Standar Penanganan PM 89/2015
Peraturan Menteri Perhubungan No. PM 89 Tahun 2015 mengatur hak-hak penumpang dan kewajiban badan usaha angkutan udara niaga berjadwal:

1. **Kategori 1: Keterlambatan 30 s.d. 60 menit**
   - Kewajiban: Penyediaan minuman ringan (*refreshment* / air mineral kemasan, teh/kopi).
   - Bentuk Voucer: E-voucher minuman yang dapat ditukarkan di gerai mitra bandara.
2. **Kategori 2: Keterlambatan 61 s.d. 120 menit**
   - Kewajiban: Penyediaan makanan ringan (*heavy snack*) dan minuman.
   - Bentuk Voucer: Voucer konsumsi senilai Rp50.000,- s.d. Rp75.000,-.
3. **Kategori 3: Keterlambatan 121 s.d. 180 menit**
   - Kewajiban: Penyediaan makanan berat (*heavy meal box*) dan minuman.
   - Bentuk Voucer: E-voucher makanan restoran bandara senilai Rp100.000,- s.d. Rp150.000,-.
4. **Kategori 4: Keterlambatan 181 s.d. 240 menit**
   - Kewajiban: Penyediaan makanan berat, snack tambahan, dan opsi pengalihan (*rerouting*) penerbangan tanpa biaya.
5. **Kategori 5: Keterlambatan lebih dari 240 menit**
   - Kewajiban: Ganti rugi uang tunai sebesar Rp300.000,- per penumpang, transfer bank, atau voucer tiket kelipatan yang disetujui penumpang, ditambah penyediaan konsumsi makanan berat.
6. **Kategori 6: Pembatalan Penerbangan (*Flight Cancellation*)**
   - Kewajiban:
     - Penumpang berhak memilih pengembalian dana tiket penuh (*full 100% refund*) tanpa biaya administrasi, ATAU
     - Pengalihan ke penerbangan berikutnya yang tersedia (*rebooking/rerouting*), DITAMBAH fasilitas akomodasi hotel bintang 3 atau setara bila penerbangan pengganti berangkat pada hari berikutnya.

### 3.2. Penentuan Prioritas Rebooking (A7)
Ketika ratusan penumpang harus dipindahkan ke kapasitas penerbangan alternatif yang terbatas, urutan antrean ditentukan oleh fungsi multivariat berbobot:
1. **Tier Program Loyalitas**:
   - Platinum Member: +10.000 poin prioritas
   - Gold Member: +5.000 poin prioritas
   - Silver Member: +2.500 poin prioritas
   - Blue / Classic: +500 poin prioritas
2. **Kebutuhan Khusus & Kerentanan**:
   - Anak tanpa pendamping (*Unaccompanied Minor / UMNR*): +20.000 poin
   - Penumpang disabilitas / kursi roda (*WCHR / WCHS / WCHC*): +15.000 poin
   - Penumpang lansia (> 65 tahun) atau bepergian bersama bayi: +8.000 poin
3. **Penerbangan Sambungan Internasional**:
   - Penumpang dengan penerbangan interline lanjutan yang berisiko tertinggal (*critical connection*): +12.000 poin
4. **Tarif & Kelas Tiket**:
   - First / Business Class: +4.000 poin
   - Full-fare Economy (Y/B): +2.000 poin
   - Promo / Discount Economy: +500 poin
5. **Penalti Urutan Pemesanan**:
   - Dikurangi berdasarkan waktu pembuatan tiket (FIFO tie-breaker).

---

## Bab IV: Kerjasama Codeshare & Prorata Pendapatan (A8)

### 4.1. Bentuk Kerjasama Codeshare
1. **Free-Sale Codeshare**:
   Marketing Carrier diizinkan menjual kursi pada inventaris Operating Carrier tanpa batasan kuota tetap, selama masih ada kursi terbuka di kelas reservasi terkait.
2. **Block-Space Codeshare**:
   Marketing Carrier membeli sejumlah kursi pasti (*fixed allotment*, e.g., 20 kursi per penerbangan) baik dengan basis *hard block* (dibayar terlepas terisi atau tidak) maupun *soft block* (kursi yang tidak terjual dikembalikan pada batas hari tertentu).

### 4.2. Prorasi Pendapatan Multilateral (IATA MPA)
Ketika tiket mencakup beberapa sektor yang dioperasikan oleh maskapai berbeda, penerimaan tiket dibagi menggunakan rumus prorata jarak tertimbang:
$$\text{Share}_A = \text{Total Fare} \times \frac{\text{Distance}_A \times \text{Weight}_A}{(\text{Distance}_A \times \text{Weight}_A) + (\text{Distance}_B \times \text{Weight}_B)}$$

### 4.3. Konsistensi Program Loyalitas & Anti-Double Dipping
Salah satu celah operasional pada perjanjian codeshare adalah kemungkinan seorang penumpang mengajukan klaim mileage ke dua program frequent flyer berbeda (misalnya GarudaMiles dan KrisFlyer pada penerbangan codeshare GA/SQ).
Sistem mengamankan integritas klaim melalui:
1. Pendaftaran *composite key* `hash(e-ticket-number + coupon-number)`.
2. Validasi status *operating carrier acknowledgment* sebelum poin diakui (*accrued*).
3. Saga terdistribusi dengan kompensasi otomatis jika konfirmasi pertukaran poin antar-sistem mitra gagal.

---

## Bab V: Asuransi Parametrik & Rekonsiliasi Keuangan

### 5.1. Mekanisme Pemicu Parametrik (G7-K29)
Asuransi penerbangan tradisional membutuhkan proses klaim manual yang berbelit-belit. Melalui implementasi kontrak `ParametricTriggerRecorder`, sistem secara otomatis mendeteksi kejadian yang memenuhi syarat pemicu:
- Keterlambatan resmi $\ge 120$ menit dari data telemetri ADS-B atau konfirmasi ATC/NOTAM.
- Pembatalan akibat fenomena alam (abu vulkanik VONA, siklon tropis).

Ketika kondisi terverifikasi, sistem langsung:
1. Memanggil kontrak `ParametricTriggerRecorder::recordTrigger`.
2. Menerbitkan e-wallet payout atau saldo kredit akun instan kepada penumpang pemegang polis.
3. Mencatat jurnal akuntansi ke buku besar asuransi dan beban klaim maskapai.

---

## Bab VI: Ringkasan Tabel & Struktur Database Fase 178

| Nama Tabel | Peran Utama | Relasi Kunci |
|---|---|---|
| `avi_schedule_versions` | Tata kelola jadwal musiman IATA | Milik maskapai penerbangan |
| `avi_codeshare_agreements` | Perjanjian pembagian hak jual & komisi | Antara 2 maskapai |
| `avi_codeshare_flights` | Pemetaan kode penerbangan mitra | Relasi ke `avi_flights` |
| `avi_disruption_cases` | Kasus insiden delay / pembatalan | Relasi ke `avi_flights` |
| `avi_disruption_passengers` | Pelacakan manifest penumpang terdampak | Relasi ke `avi_disruption_cases` & `avi_tickets` |
| `avi_care_vouchers` | E-voucher kompensasi PM 89/2015 | Relasi ke `avi_disruption_passengers` |
| `avi_loyalty_accounts` | Akun poin frequent flyer penumpang | Relasi ke `users` / penumpang |
| `avi_loyalty_transactions` | Buku besar mutasi miles loyalitas | Relasi ke `avi_loyalty_accounts` |
| `avi_yield_buckets` | Alokasi dinamis batas kursi per tarif | Relasi ke `avi_flights` |
| `avi_parametric_claims` | Pencatatan klaim asuransi otomatis | Relasi ke `avi_disruption_cases` & polis |


---

## Bab VII: Studi Kasus Mendalam & Simulasi Krisis Erupsi Bandara Denpasar (DPS VONA Red)

### 7.1. Konteks Geografis & Kerentanan Koridor Udara Bali
Bandara Internasional I Gusti Ngurah Rai (DPS/WADD) merupakan simpul gerbang pariwisata internasional utama Indonesia sekaligus hub penghubung vital untuk rute kawasan Indonesia Timur (Nusa Tenggara, Maluku, dan Papua). Posisi geografis bandara berada di dekat deretan gunung berapi aktif di busur vulkanik Sunda, terutama Gunung Agung (berjarak ±70 km timur laut bandara) dan Gunung Batur.

Ketika letusan eksplosif terjadi, sebaran abu vulkanik halus (*silica ash particles*) terbawa angin muson timur atau barat melintasi jalur pendekatan pendaratan (*approach corridor*) dan jalur lepas landas (*departure climb gradient*). Butiran silika vulkanik memiliki titik leleh sekitar 1.100°C, yang berada di bawah suhu ruang bakar turbin mesin jet modern (mencapai 1.400°C–1.700°C). Abu yang tersedot ke dalam mesin akan meleleh dan melapisi bilah turbin serta nosel bahan bakar, menyebabkan fenomena mati mesin mendadak di udara (*engine flameout*), penyumbatan tabung pitot pembaca kecepatan, serta pengikisan kaca kokpit (*cockpit windshield sandblasting*).

### 7.2. Mekanisme VONA & Penutupan Ruang Udara
Pusat Vulkanologi dan Mitigasi Bencana Geologi (PVMBG) menerbitkan Volcano Observatory Notice for Aviation (VONA). Sesuai ICAO Doc 9766 (*Handbook on the International Airways Volcano Watch*), kode warna status bahaya penerbangan meliputi:
- **GREEN**: Gunung api dalam status normal, tidak ada ancaman abu vulkanik.
- **YELLOW**: Peningkatan aktivitas seismik di atas normal; kepulan asap rendah.
- **ORANGE**: Erupsi signifikan terdeteksi tanpa semburan abu vulkanik ke ketinggian jelajah, atau peningkatan tremor magmatik kritis.
- **RED**: Erupsi besar sedang berlangsung dengan semburan abu vulkanik tebal membumbung ke ketinggian rute jelajah penerbangan (Flight Level 250 ke atas).

Begitu VONA RED diterbitkan dan didukung oleh hasil pemodelan dispersi abu dari Volcanic Ash Advisory Centre (VAAC Darwin) serta hasil uji kertas hisap (*paper test*) di darat yang menunjukkan partikel abu menutupi apron bandara, Otoritas Bandar Udara Wilayah IV dan AirNav Indonesia menerbitkan NOTAM penutupan bandara (*Aerodrome Closed*).

### 7.3. Rantai Eksekusi Sistem Otomasi Terpadu (SuperWeb Aviation Engine)
Dalam modul `Modules\Aviation`, peristiwa darurat di atas ditangani secara terstruktur melalui komponen-komponen berikut:

1. **Konsumsi NOTAM & Inisiasi Kasus Disrupsi**:
   Dispatcher bandara atau integrasi feed otomatis memanggil endpoint penanganan disrupsi:
   `ProcessDisruptionCaseAction::execute([ 'incident_category' => 'FORCE_MAJEURE_VOLCANIC_ERUPTION', 'airport_code' => 'DPS', ... ])`
   Sistem secara instan mengidentifikasi 12 penerbangan yang memiliki jadwal keberangkatan atau kedatangan di DPS dalam jendela waktu 18 jam ke depan.

2. **Pembatalan Massal & Pembekuan Inventaris**:
   Setiap penerbangan dipindahkan statusnya ke `CANCELLED`. Seluruh bucket penjualan (`avi_yield_buckets`) dikunci (*hard lock*) untuk mencegah pemesanan baru.

3. **Eksekusi Saga Kompensasi & Kepedulian**:
   `DisruptionIrropsSaga` dijalankan secara paralel untuk 1.800 manifes penumpang:
   - Evaluasi hak care PM 89/2015 via `PassengerCareObligationCalculator` (A9).
   - Mengingat peristiwa ini merupakan pembatalan penerbangan (*Kategori 6*), hak kepedulian yang diterbitkan meliputi akomodasi hotel darurat, makan malam dan sarapan pagi, serta opsi penataan ulang jadwal atau pengembalian dana penuh (*full refund*).
   - E-voucher diterbitkan secara kriptografis (`avi_care_vouchers`) dan tautan digital dikirimkan ke kontak ponsel penumpang.

4. **Orkestrasi Re-akomodasi Penumpang (A7)**:
   Algoritma `DisruptedPassengerReaccommodator` menyortir 1.800 penumpang ke dalam antrean berbobot:
   - Penumpang Platinum dan keluarga bepergian bersama anak kecil/lansia memperoleh alokasi prioritas pada penerbangan pengganti di hari $D+1$ dan $D+2$.
   - Penumpang yang transit internasional segera dijadwalkan ulang melalui rute evakuasi darat ke Bandara Internasional Juanda Surabaya (SUB) atau rute laut ferry cepat Padangbai-Lembar.

5. **Pemesanan Blok Kamar Hotel Mitra via G7-K16 (`RoomBlockReserver`)**:
   Sistem tidak membiarkan penumpang mencari penginapan sendiri di tengah kepanikan massal. Layanan integrasi `HotelIrropsRoomReservationService` melakukan negosiasi otomatis dengan modul `Hotel`:
   - 900 kamar terbagi di 5 jaringan hotel mitra bintang 3 dan bintang 4 di kawasan Jimbaran, Kuta, dan Sanur dipesan serentak.
   - Konfirmasi kode booking hotel (*hotel reservation reference*) disematkan langsung pada e-voucher penumpang.

6. **Pencairan Asuransi Parametrik Otomatis via G7-K29 (`ParametricTriggerRecorder`)**:
   Untuk penumpang yang membeli polis asuransi perjalanan terintegrasi, `AviationParametricInsuranceService` memicu eksekusi kontrak G7-K29.
   Sistem mencatat pemicu `VOLCANIC_ERUPTION_CLOSURE` dengan durasi 1.080 menit (18 jam) dan menerbitkan santunan tunai darurat sebesar Rp1.000.000,- langsung ke saldo dompet digital penumpang untuk menutup kebutuhan darurat selama evakuasi.

Seluruh proses di atas berlangsung secara nir-sentuh (*zero-touch autonomous orchestration*) dalam hitungan menit, menghindarkan kepanikan massal di konter bandara dan menjaga reputasi kepedulian maskapai.
