# Spesifikasi Perencanaan Jadwal Jaringan Penerbangan & Manajemen Slot IATA (Network Scheduling & Fleet Assignment)

## 1. Pendahuluan: Perencanaan Jaringan Maskapai Penerbangan Berjadwal

Perencanaan jaringan (*Network Planning*) adalah proses penentuan rute, frekuensi, jadwal waktu keberangkatan (*departure time window*), dan jenis tipe pesawat (*aircraft type*) yang akan dioperasikan untuk memaksimalkan profitabilitas jaringan maskapai secara terpadu.

Penerbangan berjadwal beroperasi dalam siklus musim IATA:
- **Musim Panas IATA (Northern Summer Season - S)**: Dimulai pada hari Minggu terakhir bulan Maret hingga hari Sabtu terakhir bulan Oktober.
- **Musim Dingin IATA (Northern Winter Season - W)**: Dimulai pada hari Minggu terakhir bulan Oktober hingga hari Sabtu terakhir bulan Maret tahun berikutnya.

Pengelolaan jadwal melibatkan manajemen versi formal (`avi_schedule_versions`) dengan transisi status yang ketat untuk menjamin kepatuhan terhadap slot bandara yang dialokasikan oleh koordinator slot internasional.

---

## 2. Struktur Versi Jadwal & State Machine (ScheduleVersionStateMachine)

Siklus hidup rilis jadwal penerbangan melalui tahapan sebagai berikut:

### 2.1. Tahapan Status
1. `draft`:
   Perancang jaringan menyusun usulan frekuensi baru, pasangan rute (*origin-destination pairs*), dan perkiraan waktu blok (*block time estimation*).
2. `review`:
   Jadwal ditinjau oleh departemen operasional (kelaikudaraan armada, rotasi kru, dan ketersediaan slot bandara).
3. `published`:
   Jadwal resmi diumumkan ke sistem distribusi global (*Global Distribution Systems* / GDS seperti Amadeus, Sabre, Travelport) untuk dibuka bagi reservasi tiket publik.
4. `active`:
   Jadwal mulai berlaku secara operasional pada tanggal efektif musim IATA terkait.
5. `superseded`:
   Jadwal digantikan oleh revisi versi baru (*schedule change notice*) yang diterbitkan kemudian.
6. `suspended`:
   Operasi jadwal dihentikan sementara karena alasan darurat, pembatasan ruang udara, atau krisis geopolitik.
7. `withdrawn`:
   Jadwal ditarik kembali sebelum sempat diaktifkan secara operasional.

### 2.2. Matriks Transisi yang Sah
```
+---------------+----------------------+------------------+
| Status Asal   | Event Pemicu         | Status Tujuan    |
+---------------+----------------------+------------------+
| draft         | submit_for_review    | review           |
| review        | approve_and_publish  | published        |
| review        | reject_to_draft      | draft            |
| review        | withdraw             | withdrawn        |
| published     | activate_season      | active           |
| published     | withdraw             | withdrawn        |
| active        | supersede_by_new     | superseded       |
| active        | suspend_operations   | suspended        |
| suspended     | resume_operations    | active           |
| suspended     | supersede_by_new     | superseded       |
+---------------+----------------------+------------------+
```

---

## 3. Optimasi Penugasan Armada (Fleet Assignment Model / FAM)

### 3.1. Masalah Penugasan Armada Dasar
Diberikan himpunan penerbangan $F$ dan himpunan jenis sub-armada $K$ (misalnya Airbus A320-200 berkapasitas 180 kursi, Boeing 737-800 berkapasitas 189 kursi, dan Airbus A330-300 berkapasitas 300 kursi).
Variabel keputusan biner:
$$x_{f, k} = \begin{cases} 1, & \text{jika penerbangan } f \text{ ditugaskan ke tipe pesawat } k \\ 0, & \text{lainnya} \end{cases}$$

Fungsi tujuan memaksimalkan kontribusi laba bersih:
$$\max \sum_{f \in F} \sum_{k \in K} \left( \text{Revenue}_{f, k} - \text{OperatingCost}_{f, k} \right) x_{f, k}$$

Dengan kendala:
1. Setiap penerbangan harus ditugaskan tepat ke satu tipe pesawat:
   $$\sum_{k \in K} x_{f, k} = 1, \quad \forall f \in F$$
2. Keseimbangan aliran pesawat pada setiap simpul bandara dan waktu (*aircraft flow conservation*).
3. Batas ketersediaan jumlah fisik pesawat dalam sub-armada $k$.

### 3.2. Penyesuaian Spill & Recapture
Ketika kapasitas pesawat $C_k$ lebih kecil daripada permintaan potensial $D_f$, sebagian calon penumpang akan tertolak (*spilled passengers*). Sebagian dari penumpang tersebut mungkin beralih ke penerbangan lain milik maskapai yang sama (*recaptured passengers*) dengan faktor konversi $\kappa \in [0, 1]$. Algoritma A6 memperhitungkan efek spill ini dalam menghitung estimasi pendapatan marjinal penerbangan.

---

## 4. Pertukaran Data Standar Industri (IATA SSIM Standards)

Sistem pertukaran jadwal menggunakan format standar IATA SSIM (Standard Schedules Information Manual):
- **Chapter 7 - Action Notifications**: Format pesan teleks SITA / EDIFACT untuk memberitahukan perubahan jadwal mendadak (*Schedule Change - ASM / SSM message*).
- **Chapter 2 - Minimum Connecting Times (MCT)**: Matriks waktu minimum transfer bagasi dan penumpang antar-terminal di bandara hub utama (CGK T3 ke T2: 90 menit; DPS Domestik ke Internasional: 75 menit).
- Data ini disinkronkan ke dalam modul `Aviation` guna memvalidasi legalitas pemesanan tiket multi-segmen.


---

## 5. Pedoman Koordinasi Slot Bandara Dunia (Worldwide Airport Slot Guidelines / WASG)

### 5.1. Klasifikasi Tingkat Kepadatan Bandara IATA
IATA membagi bandara di seluruh dunia ke dalam tiga kategori kapasitas:
1. **Level 1 (Non-Coordinated Airport)**:
   Kapasitas fasilitas bandara (landasan, apron, terminal) umumnya memadai untuk memenuhi seluruh permintaan maskapai. Maskapai hanya perlu memberitahukan jadwal penerbangan untuk alokasi tempat parkir (*basic flight notification*).
2. **Level 2 (Schedules Facilitated Airport)**:
   Terdapat potensi terjadinya periode kemacetan pada jam-jam sibuk tertentu (*peak hours*). Diperlukan seorang fasilitator jadwal (*schedules facilitator*) untuk mendorong penyesuaian jadwal sukarela antar-maskapai guna menghindari keterlambatan berantai.
3. **Level 3 (Coordinated Airport)**:
   Permintaan operator penerbangan melebihi kapasitas operasional bandara secara signifikan dan permanen. Maskapai diwajibkan secara hukum memegang **slot bandara resmi** yang dialokasikan oleh koordinator slot independen sebelum dapat mengoperasikan penerbangan komersial terjadwal. Contoh bandara Level 3 di Indonesia adalah Bandara Internasional Soekarno-Hatta (CGK) dan Bandara Internasional I Gusti Ngurah Rai (DPS).

### 5.2. Prinsip Aturan Kakek (Grandfather Rights) & Aturan Penggunaan 80/20 (Use-it-or-Lose-it)
Aturan paling fundamental dalam manajemen slot internasional adalah prinsip *historics* atau *grandfather rights*:
- Jika maskapai penerbangan berhasil mengoperasikan minimal 80% dari rangkaian slot yang dialokasikan selama periode musim IATA terkait (Musim Panas atau Musim Dingin), maskapai tersebut memiliki hak prioritas utama untuk mempertahankan slot yang sama pada musim yang ekuivalen di tahun berikutnya.
- Jika tingkat utilisasi aktual berada di bawah ambang batas 80% tanpa justifikasi yang sah (*alleviating circumstances* seperti penutupan bandara akibat bencana alam atau perang), slot tersebut hangus secara otomatis (*slot forfeiture*) dan dikembalikan ke dalam kolam slot umum (*slot pool*) untuk diperebutkan oleh maskapai lain.

Modul `Aviation` menyediakan command berkala `avi:slot-usage-evaluate` untuk melacak rasio utilisasi slot secara real-time dan menerbitkan peringatan dini jika rute tertentu berisiko kehilangan hak historis slot IATA.
