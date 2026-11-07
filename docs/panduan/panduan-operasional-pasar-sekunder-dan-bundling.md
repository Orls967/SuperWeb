# Panduan Operasional Pasar Sekunder & Dynamic Bundling

## 1. Pendahuluan & Cakupan Panduan
Panduan ini ditujukan bagi manajer operasional venue (*venue manager*), tim layanan pelanggan (*box office staff*), dan auditor kepatuhan perpajakan (*tax compliance officer*) untuk mengoperasikan modul pasar sekunder tiket (*secondary ticket resale*), alokasi paket bundling terpadu (*dynamic bundling*), dan tata kelola daftar tunggu (*waitlist governance*).

Sistem Superweb Venue Fase 116 dirancang untuk memastikan pengalaman perdagangan tiket sekunder yang aman, adil, transparan, dan patuh terhadap regulasi perlindungan konsumen di Indonesia.

---

## 2. Alur Operasional Penjualan Tiket Sekunder (C2C Resale)
### 2.1. Pendaftaran Tiket oleh Pemilik (Seller)
1. Pemilik tiket asli masuk ke menu **Pasar Sekunder > Jual Tiket**.
2. Sistem secara otomatis menampilkan daftar tiket valid yang dimiliki pengguna dengan status `ISSUED`.
3. Pengguna memasukkan harga penawaran yang diinginkan.
4. Mesin validasi `AntiScalpingCapEngine` mengevaluasi harga secara real-time:
   - Jika harga $\le 120\%$ dari harga beli asli: tombol *Daftarkan Tiket* aktif.
   - Jika harga $> 120\%$ dari harga beli asli: formulir menampilkan peringatan merah dan menolak pendaftaran.
5. Setelah disetujui, status listing menjadi `LISTED` dan tiket tampil di bursa pasar sekunder.

### 2.2. Pembelian oleh Pengguna Baru (Buyer)
1. Calon pembeli meninjau daftar tiket pada menu **Jelajah Pasar Sekunder**.
2. Calon pembeli memilih tiket dan mengonfirmasi pembayaran.
3. Transaksi dialihkan ke status `ESCROW_HOLD`:
   - Dana pembeli ditahan secara aman di rekening penampungan platform.
   - Hak tiket dikunci sementara sehingga tidak dapat dibeli oleh pengguna lain.
4. Sistem memverifikasi validitas kepemilikan dan melakukan pemotongan terdistribusi (*tax waterfall*):
   - **Pajak Daerah PBJT (10%):** dialokasikan ke pos kas daerah.
   - **Komisi Platform (8%):** dialokasikan ke pendapatan operasional Superweb.
   - **Royalti Promotor/Artis (2%):** dialokasikan ke akun royalti promotor.
   - **Pencairan Bersih Penjual (80%):** ditransfer ke dompet saldo penjual.
5. Barcode lama dibatalkan permanen dan sistem menerbitkan barcode kriptografis baru untuk pembeli.
6. Status listing berubah menjadi `SETTLED`.

---

## 3. Tata Kelola Antrean Daftar Tunggu (Waitlist Management)
### 3.1. Penentuan Prioritas Otomatis
Ketika kategori tiket tertentu habis terjual, pengunjung dapat bergabung ke antrean daftar tunggu:
1. Peringkat antrean dihitung secara otomatis oleh algoritma `WaitlistPriorityGovernor` berdasarkan:
   - Poin loyalitas akun (bobot maks 400 poin),
   - Durasi waktu menunggu / *anti-starvation aging* (bobot maks 250 poin),
   - Status keanggotaan fan club resmi (bonus 200 poin),
   - Frekuensi kehadiran di acara sebelumnya (bobot maks 150 poin).
2. Peringkat antrean bersifat dinamis dan diperbarui setiap kali terjadi perubahan data pengguna.

### 3.2. Jendela Waktu Penawaran (15-Minute Countdown Window)
1. Bila ada tiket yang tersedia (akibat pembatalan atau pelepasan kuota promotor), sistem otomatis memilih antrean peringkat 1.
2. Pengguna menerima notifikasi SMS/Push instan berisi tautan klaim eksklusif.
3. Pengguna memiliki batas waktu **15 menit (900 detik)** untuk menyelesaikan pembayaran.
4. Jika waktu habis sebelum pembayaran selesai:
   - Status penawaran berubah menjadi `EXPIRED`.
   - Tiket secara otomatis ditawarkan ke pengguna pada antrean berikutnya.

---

## 4. Pengelolaan Paket Dynamic Bundling
### 4.1. Pembuatan Paket Baru
1. Operator venue membuka menu **Dynamic Bundles > Buat Paket Baru**.
2. Pilih acara hiburan target dan masukkan nama serta deskripsi paket.
3. Tambahkan komponen paket:
   - Tiket Acara Konser (VIP / Festival),
   - Kamar Hotel Mitra via integrasi modul Hotel (G7-K20),
   - Tiket Shuttle Transportasi via modul Logistik (G7-K21),
   - Voucher F&B Bar & Lounge.
4. Masukkan target persentase diskon promosi dan tentukan batas bawah harga (*floor price*).
5. Sistem memverifikasi margin kotor melalui `DynamicBundleMarginCalculator`. Bila marjin sehat ($\ge 15\%$), paket dapat diaktifkan menjadi `PUBLISHED`.

