# Konsep Domain Perhotelan Terintegrasi (Enterprise Hospitality Management System)

Dokumen ini mendokumentasikan spesifikasi konsep, analisis domain bisnis, invarian akuntansi berpasangan, katalog kasus operasional, dan matriks kepekaan eksternal untuk domain Perhotelan.

---

## 1. Analisis Bisnis & Kompleksitas Operasional Perhotelan (Fase 91: PMS & CRS)

Operasional hotel modern mengelola inventaris yang sangat mudah rusak (*perishable inventory*). Kamar hotel yang tidak terjual pada satu malam tidak dapat disimpan untuk dijual kembali pada malam berikutnya. Oleh karena itu, arsitektur PMS (Property Management System) dan CRS (Central Reservation System) dirancang dengan prinsip-prinsip berikut:

### 1.1 Optimasi Kapasitas & Algoritma Overbooking Limit Optimizer (H1)
No-show dan pembatalan menit terakhir merupakan realitas harian di industri perhotelan. Apabila hotel hanya menjual tepat 100% dari kapasitas fisik kamar, tingkat okupansi riil hampir pasti berada di bawah kapasitas maksimal akibat tamu yang tidak datang tanpa pemberitahuan.
- **Optimasi Risiko Terukur**: Algoritma H1 menghitung ekspektasi pendapatan marjinal dari penjualan kamar tambahan ($k$) terhadap ekspektasi biaya penalti relokasi tamu (*walk cost*).
- **Perlindungan Tamu & Saga Relokasi**: Bila seluruh tamu hadir dan terjadi kelebihan kapasitas fisik, sistem mengeksekusi saga relokasi terdistribusi: memesan kamar di properti tetangga setara/lebih tinggi, memesan transportasi VIP, dan memberikan voucher kompensasi pemulihan layanan (H5) ke folio tamu.

### 1.2 Penetapan Harga Dinamis & Kekebalan Kontrak Korporat (H2 BAR)
Tarif harian kamar (*Best Available Rate / BAR*) berfluktuasi berdasarkan:
1. **Pita Okupansi Properti**: Permintaan tinggi (> 90%) menaikkan pengali tarif hingga 1.30x untuk memaksimalkan RevPAR.
2. **Jendela Pemesanan (Lead Time)**: Pemesanan last-minute (< 3 hari) dikenakan penalti tarif pendek (+5%), sedangkan pemesanan jauh hari (> 30 hari) diberikan insentif potongan harga (-5%).
3. **Kalender Acara Kota (Event Factor)**: Festival budaya, konser, atau pameran internasional memicu kenaikan faktor acara (+15%).
4. **Kekebalan Kontrak Korporat (Contract Rate Immunity)**: Klien B2B korporat dengan perjanjian tarif flat tahunan dijamin terlindungi dari kenaikan tarif dinamis musiman.

### 1.3 Pengaturan Rute Tata Graha & Produktivitas Pembersihan (H3)
Kesiapan fisik kamar untuk tamu baru ditentukan oleh kecepatan dan urutan kerja staf tata graha (*housekeeping*).
- Algoritma H3 membagi beban kerja secara seimbang antar petugas dengan batasan kredit pembersihan harian.
- Tamu VIP dan permintaan check-in lebih awal (*early check-in*) secara otomatis diposisikan pada urutan rute terdepan di lantai yang sama untuk meminimalkan waktu tempuh lift petugas.

### 1.4 Smart Room, Kembar Digital, & Efisiensi Energi HVAC Setback (H4)
Sistem pendingin udara (AC) menyumbang porsi biaya utilitas terbesar di hotel tropis.
- Integrasi sensor gerak IoT mendeteksi kekosongan kamar terisi tamu.
- Algoritma H4 menaikkan setpoint temperatur AC secara bertahap (+3 °C) selama tamu berada di luar kamar, menghemat daya hingga 3.000 bps tanpa mengorbankan kenyamanan saat tamu kembali.
- Seluruh penghematan kilowatt-hour (kWh) dikonversi menjadi reduksi emisi karbon (kg CO2) dan dicatat pada buku besar ESG perusahaan.

---

## 2. Invarian Domain & Aturan Akuntansi Berpasangan (Double-Entry Ledger)

Seluruh transaksi keuangan hotel wajib mematuhi persamaan akuntansi dasar dan dicatat secara append-only di general ledger modul Banking:

### Invarian 1: Pengakuan Pendapatan Sewa Kamar Saat Night Audit (D91-8)
Setiap malam pada saat penutupan hari (Night Audit), kamar dengan tamu berstatus `checked_in` dibebankan sewa harian:
$$	ext{Debit } 	exttt{htl:guest\_ledger:IDR} = 	ext{Kredit } 	exttt{htl:room\_revenue:IDR} + 	ext{Kredit } 	exttt{htl:tax\_payable:IDR} + 	ext{Kredit } 	exttt{htl:service\_charge\_payable:IDR}$$
- Dasar Hukum PBJT Jasa Perhotelan: UU No. 1 Tahun 2022 tentang HKPD (tarif maksimal 10% dari sewa kamar ditambah service charge).
- Service charge standar industri perhotelan: 5% - 10% dialokasikan untuk kesejahteraan karyawan.

### Invarian 2: Kompensasi Beban Relokasi Overbooking (Walk Guest)
Beban relokasi diakui sebagai biaya operasional langsung hotel:
$$	ext{Debit } 	exttt{htl:overbooking\_expense:IDR} = 	ext{Kredit } 	exttt{clearing:external:IDR}$$

### Invarian 3: Voucher Pemulihan Layanan Kontra-Pendapatan (H5)
Voucher kompensasi atas gangguan fasilitas kamar dicatat sebagai pengurang pendapatan:
$$	ext{Debit } 	exttt{htl:service\_recovery\_contra\_revenue:IDR} = 	ext{Kredit } 	exttt{htl:guest\_ledger:IDR}$$

### Invarian 4: Penutupan Folio Tamu Wajib Bersaldo Nol (Zero Balance Checkout)
Sistem menolak proses check-out tamu apabila saldo bersih folio belum bernilai Rp 0:
$$\sum 	ext{Debit Folio} - \sum 	ext{Kredit Folio} = 0$$

---

## 3. Matriks Kepekaan Eksternal & Respons Kebijakan (T-5)

| Faktor Eksternal | Parameter Sistem | Elastisitas / Aturan Respons | Batas Toleransi | Sumber Regulasi / Rujukan |
| :--- | :--- | :--- | :--- | :--- |
| **Bencana Erupsi / Penutupan Bandara** | `travel_disruption_active` | Overbooking di-reset ke 0; kebijakan pembatalan bebas denda (*waiver*) aktif otomatis. | Level Siaga III / Notam Bandara | PVMBG & Kemenhub Ditjen Hubud |
| **Kenaikan Okupansi Kota Pesaing** | `market_occupancy_index` | Tarif dinamis BAR dinaikkan mengikuti kenaikan permintaan agregat pasar. | Indeks > 85% | STR Global Benchmark |
| **Penyelenggaraan Festival Kota** | `city_event_active` | Pengali tarif acara (+15%) aktif pada seluruh inventaris kamar umum yang belum terikat kontrak. | Kalender Event Resmi | Dinas Pariwisata Daerah |
| **Pungutan Wisatawan Asing (PWA)** | `bali_pwa_rate_idr` | Menampilkan status kepatuhan bukti bayar PWA Rp 150.000 saat registrasi tamu WNA di Bali. | Berlaku sejak 14 Feb 2024 | Perda Provinsi Bali No. 6/2023 |
| **Kewajiban Pelaporan APOA** | `apoa_sla_hours` | Data tamu WNA wajib dilaporkan ke Kantor Imigrasi dalam kurun waktu 1x24 jam sejak check-in. | Tenggat 24 Jam | UU No. 6 Tahun 2011 Keimigrasian |

---

## 4. Katalog Kasus Operasional & Edge Cases (T-6)

1. **Kasus 01: Reservasi Single Room Mandiri Web**: Tamu memesan 1 kamar via portal web resmi, sistem mengunci room night secara deterministik dan menerbitkan nomor konfirmasi sekuensial.
2. **Kasus 02: Dua Pemesan Bersaing pada Kamar Terakhir**: Dua tamu secara simultan menekan tombol booking pada sisa 1 kamar; transaksi dengan pessimistic lock pertama berhasil mengunci kamar, reservasi kedua dialokasikan overbooked dalam batas 3%.
3. **Kasus 03: Penolakan Overbooking Melebihi Batas Risiko (Hard Limit 3%)**: Permintaan reservasi ke-4 di atas kapasitas 100 kamar ditolak sistem dengan pesan *422 Overbooking Capacity Exceeded*.
4. **Kasus 04: Perlindungan Tarif Kontrak Korporat (Immunity Test)**: Tamu korporat memesan kamar saat musim liburan ketika BAR melonjak ke Rp 1.883.000; sistem tetap membebankan tarif kontrak flat Rp 950.000.
5. **Kasus 05: Batas Lantai Tarif Dinamis (Floor Rate Guardrail)**: Pada saat low season dengan okupansi 10%, formula tarif yang menghasilkan Rp 300.000 dijepit ke batas lantai biaya pokok Rp 450.000.
6. **Kasus 06: Check-In Tamu Asing dengan Validasi Paspor & APOA**: Tamu WNA asal Australia melakukan check-in; sistem mencatat paspor, menerbitkan kunci kamar digital, dan mendaftarkan laporan APOA berstatus pending.
7. **Kasus 07: Penolakan Check-In ke Kamar Kotor (Status Guard)**: Resepsionis mencoba check-in tamu ke kamar yang masih berstatus `dirty`; sistem memblokir tindakan dan menginstruksikan pemilihan kamar berstatus `inspected`.
8. **Kasus 08: Transisi Otomatis Kamar Kotor Saat Tamu Check-Out**: Tamu menyelesaikan checkout; sistem seketika mengubah status fisik kamar menjadi `dirty` dan menerbitkan tugas pembersihan ke antrean housekeeping.
9. **Kasus 09: Pengurutan Rute Tata Graha Antar Lantai (H3 Routing)**: Algoritma H3 mengelompokkan tugas pembersihan 10 kamar, menempatkan kamar VIP di urutan terdepan dan menyusun kamar checkout per koridor lantai.
10. **Kasus 10: Aktivasi Mode Hemat Energi Kamar Kosong Terisi (H4 Setback)**: Tamu keluar kamar meninggalkan kunci aktif; setelah 15 menit tanpa deteksi gerak, AC masuk ke mode setback (+3 °C) dan menghemat 990 Wh energi.
11. **Kasus 11: Deteksi Tamu Kembali Masuk Kamar**: Sensor mendeteksi pintu dibuka dan ada pergerakan; mode setback seketika nonaktif dan suhu kembali ke kenyamanan normal tamu.
12. **Kasus 12: Resolusi Kompensasi Service Recovery Kerusakan Pendingin Ruangan (H5)**: Tamu melaporkan AC rusak selama 10 jam; sistem menerbitkan kompensasi 50% tarif kamar (Rp 750.000) dan mencatat hak menginap gratis (*Free Night Voucher*).
13. **Kasus 13: Eksekusi Night Audit Harian Berbasis Tanggal Bisnis**: Sistem membebankan pendapatan kamar seluruh tamu in-house ke folio, memvalidasi keseimbangan ledger, dan memajukan business date 1 hari ke depan.
14. **Kasus 14: Idempotensi Night Audit**: Operator menjalankan kembali proses audit malam pada tanggal bisnis yang sama; sistem mengembalikan data snapshot yang sudah ada tanpa menduplikasi pencatatan pendapatan.
15. **Kasus 15: Penanganan Tamu No-Show Saat Audit Malam**: Reservasi terkonfirmasi tanpa kabar hingga penutupan audit malam ditandai sebagai `no_show`; uang deposit dipotong sebagai denda dan sisa hari dilepaskan.
16. **Kasus 16: Penggantian Kunci Fisik RFID Hilang**: Tamu kehilangan kunci kartu kamar; front desk mencabut token kunci lama dan menerbitkan kartu RFID baru yang terenkripsi.
17. **Kasus 17: Pembebanan Biaya Insidental Minibar & Binatu ke Folio**: Staf tata graha menginput konsumsi 2 kaleng minuman ringan; sistem membebankan tagihan pokok ditambah service charge 5% dan pajak PBJT 10% ke folio tamu.
18. **Kasus 18: Audit Dua Sumber Keseimbangan Pendapatan (Dual-Source Clean State)**: Perintah `hotel:audit` membandingkan total room charge pada tabel folio fisik dengan snapshot ledger perbankan dan melaporkan selisih Rp 0 (Exit code 0).
19. **Kasus 19: Deteksi Korupsi Data Kamar Liar oleh Audit Command**: Data reservasi sengaja dirusak tanpa catatan transaksi ledger yang sah; perintah audit mendeteksi anomali dan keluar dengan exit code non-zero.
20. **Kasus 20: Proteksi Anti-IDOR pada Portal Akses Tamu**: Tamu A tidak dapat melihat nomor reservasi, folio tagihan, atau kunci kamar digital milik Tamu B.
