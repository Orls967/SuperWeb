# Kompilasi Regulasi Penerbangan Nasional: Hak Penumpang, Tanggung Jawab Pengangkut & Standar Penanganan Keterlambatan (PM 89/2015 & PM 77/2011)

## 1. Landasan Yuridis Penerbangan Niaga Berjadwal Indonesia

Operasional angkutan udara niaga berjadwal di wilayah kedaulatan Negara Kesatuan Republik Indonesia tunduk pada kerangka hukum yang ketat:
1. **Undang-Undang Republik Indonesia Nomor 1 Tahun 2009 tentang Penerbangan**.
2. **Peraturan Menteri Perhubungan Republik Indonesia Nomor PM 89 Tahun 2015 tentang Penanganan Keterlambatan Penerbangan (*Delay Management*) pada Badan Usaha Angkutan Udara Niaga Berjadwal di Indonesia**.
3. **Peraturan Menteri Perhubungan Republik Indonesia Nomor PM 77 Tahun 2011 tentang Tanggung Jawab Pengangkut Udara**.

Regulasi ini menegaskan bahwa keselamatan, keamanan, kenyamanan, dan kepastian hak-hak konsumen penerbangan merupakan kewajiban mutlak yang tidak dapat dikesampingkan melalui klausul baku sepihak dalam perjanjian tiket.

---

## 2. Analisis Pasal-Pasal Kunci PM 89 Tahun 2015

### 2.1. Klasifikasi Keterlambatan Berdasarkan Rentang Waktu (Pasal 2 & Pasal 3)
Keterlambatan dihitung dari perbedaan waktu keberangkatan aktual (*actual off-block time*) terhadap jadwal waktu keberangkatan yang tertera pada tiket (*scheduled departure time*).
Kategori keterlambatan dibagi menjadi 6 tingkat:
- **Kategori 1 (Keterlambatan 30 s.d. 60 menit)**:
  Pengangkut wajib memberikan minuman ringan (*refreshment*).
- **Kategori 2 (Keterlambatan 61 s.d. 120 menit)**:
  Pengangkut wajib memberikan makanan ringan (*heavy snack*) dan minuman.
- **Kategori 3 (Keterlambatan 121 s.d. 180 menit)**:
  Pengangkut wajib memberikan makanan berat (*heavy meal*) dan minuman.
- **Kategori 4 (Keterlambatan 181 s.d. 240 menit)**:
  Pengangkut wajib memberikan makanan berat dan minuman serta memberikan pilihan kepada penumpang untuk dialihkan ke penerbangan berikutnya atau maskapai lain tanpa biaya tambahan.
- **Kategori 5 (Keterlambatan lebih dari 240 menit)**:
  Pengangkut wajib memberikan ganti rugi berupa uang tunai sebesar Rp300.000,- (tiga ratus ribu rupiah) per penumpang.
- **Kategori 6 (Pembatalan Penerbangan)**:
  Badan usaha angkutan udara wajib mengalihkan penumpang ke penerbangan berikutnya dan apabila penumpang tidak dapat dipindahkan ke penerbangan lain pada hari yang sama, maka wajib diberikan akomodasi hotel bintang 3 atau setara serta transportasi bandara-hotel pergi-pulang, ATAU mengembalikan seluruh biaya tiket (*full refund*) secara utuh tanpa potongan biaya administrasi.

### 2.2. Pengecualian Tanggung Jawab Finansial (Pasal 10)
Pengangkut dibebaskan dari kewajiban pembayaran ganti kerugian kompensasi uang tunai Rp300.000,- (Kategori 5) apabila keterlambatan disebabkan oleh:
1. **Faktor Cuaca**:
   Hujan lebat, petir, kabut tebal, asap kebakaran hutan, angin kencang melebihi standar operasional, atau abu vulkanik dari letusan gunung berapi yang didukung oleh informasi resmi BMKG atau NOTAM otoritas bandara.
2. **Faktor Operasional Bandar Udara**:
   Landasan pacu terhalang (*runway blocked*), kerusakan sistem lampu landasan (*runway lights*), atau gangguan navigasi bandara yang bukan kelalaian maskapai.
3. **Faktor Keamanan**:
   Pemeriksaan keamanan khusus atas perintah otoritas keamanan negara, ancaman bom (*bomb threat*), atau huru-hara.

Meskipun demikian, **kewajiban kepedulian pelayanan (*duty of care*)** seperti penyediaan konsumsi makanan, minuman, dan fasilitasi penginapan hotel (Kategori 1 s.d. 4 dan 6) **tetap wajib dipenuhi** oleh maskapai demi asas perlakuan manusiawi kepada konsumen yang terlantar di bandara.

---

## 3. Tanggung Jawab Pengangkut Menurut PM 77 Tahun 2011

PM 77/2011 mengatur ganti kerugian terhadap kerugian yang diderita penumpang:
1. **Kematian atau Cacat Tetap**:
   Santunan kecelakaan penerbangan sebesar Rp1.250.000.000,- (satu miliar dua ratus lima puluh juta rupiah) per penumpang.
2. **Kehilangan atau Kerusakan Bagasi Tercatat**:
   Ganti kerugian sebesar Rp200.000,- per kilogram bagasi yang hilang/rusak dengan batas maksimum Rp4.000.000,- per penumpang.
3. **Keterlambatan Bagasi**:
   Kompensasi uang tunggu (*first aid kit expenses*) sebesar Rp200.000,- untuk pembelian perlengkapan darurat dasar penumpang saat bagasi terlambat tiba.

---

## 4. Implementasi Teknis Otomasi pada Sistem SuperWeb

Dalam arsitektur modul `Aviation`:
1. Algoritma A9 `PassengerCareObligationCalculator` menerjemahkan ketentuan hukum di atas menjadi fungsi matematika deterministik murni.
2. Setiap keterlambatan yang teridentifikasi secara otomatis mencocokkan alasan insiden terhadap tabel pengecualian (apakah `FORCE_MAJEURE_WEATHER`, `NOTAM_RESTRICTION`, atau `AIRLINE_AOG_TECHNICAL`).
3. State machine `DisruptionCaseStateMachine` memandu tahapan penyelesaian dari deteksi awal hingga pencairan klaim santunan atau penyediaan hotel, memastikan rekam jejak kepatuhan hukum (*regulatory compliance trail*) tersimpan rapi untuk inspeksi Direktorat Kelaikudaraan dan Pengoperasian Pesawat Udara (DKPPU).


---

## 5. Prosedur Operasional Pengalihan Penerbangan (Diversion Protocol)

Ketika penerbangan tidak dapat mendarat di bandara tujuan akibat cuaca buruk atau alasan operasional lainnya dan harus dialihkan (*diverted*) ke bandara alternatif (*alternate aerodrome*):
1. **Pemberitahuan Segera**:
   Awak penerbangan dan petugas layanan darat wajib memberikan penjelasan resmi kepada penumpang dalam waktu paling lambat 30 menit setelah mendarat di bandara alternatif.
2. **Kewajiban Pengangkutan Lanjutan**:
   Maskapai wajib mengangkut penumpang ke bandara tujuan semula sesegera mungkin setelah kondisi memungkinkan, baik menggunakan pesawat yang sama maupun moda transportasi pengganti (pesawat lain, bus eksekutif, atau kapal penyeberangan cepat).
3. **Penyediaan Konsumsi & Kompensasi**:
   Selama masa tunggu di bandara alternatif, seluruh fasilitas konsumsi (Kategori 1 s.d. 3) dan penginapan hotel wajib disediakan tanpa memungut biaya apapun dari penumpang.
4. **Pilihan Pembatalan & Refund Sepihak**:
   Bila penumpang menolak melanjutkan perjalanan ke bandara tujuan dan memilih mengakhiri perjalanan di bandara alternatif, maskapai wajib mengembalikan sisa biaya tiket segmen yang belum diterbangkan (*pro-rated unused segment refund*).


---

## 6. Sanksi Administratif & Pengawasan Ditjen Perhubungan Udara

Direktorat Jenderal Perhubungan Udara melalui Kantor Otoritas Bandar Udara melakukan audit dan inspeksi mendadak secara berkala terhadap kepatuhan penanganan keterlambatan:
1. **Teguran Tertulis**: Diberikan kepada maskapai yang terbukti tidak membagikan kompensasi makanan/minuman tepat waktu sebanyak 3 kali berturut-turut.
2. **Pembekuan Izin Rute Penerbangan**: Diberikan jika maskapai menolak membayarkan ganti kerugian tunai Kategori 5 atau menelantarkan penumpang tanpa fasilitas penginapan.
3. **Pencabutan Izin Usaha Angkutan Udara (AOC)**: Merupakan sanksi terberat bila pelanggaran hak konsumen dilakukan secara terstruktur dan membahayakan ketertiban umum di bandara.

Dengan integrasi sistem pencatatan otomatis di modul Aviation ini, setiap kasus disrupsi terekam dengan integritas tinggi dan dapat diaudit secara transparan oleh regulator setiap saat.
