# Panduan Pengguna: Penumpang & Portal Layanan Mandiri (Fase 177)

Panduan ini ditujukan bagi penumpang maskapai (*passenger*) dan staf layanan pelanggan (*customer_care*) dalam menggunakan portal layanan mandiri pemesanan tiket penerbangan dan pengembalian dana (*refund*).

---

## 1. Pemilihan Penerbangan & Reservasi Kursi

Calon penumpang dapat mencari jadwal penerbangan yang tersedia melalui antarmuka web `/aviation/passenger`:
1. Pilih rute asal dan tujuan (misal: `CGK` ke `DPS`).
2. Pilih jadwal penerbangan dan kelas kabin yang diinginkan:
   - **Kabin Ekonomi (Y):** Pilihan tarif `PROMO`, `STD`, atau `FLEX`.
   - **Kabin Bisnis (J):** Kursi premium dengan jatah bagasi lebih besar dan fleksibilitas jadwal penuh.
3. Saat kursi dipilih, sistem secara otomatis menerbitkan kunci penahanan sementara (*Seat Hold Token*) yang berlaku selama 15 menit.
4. Selesaikan pembayaran sebelum waktu hitung mundur habis untuk mengonfirmasi penerbitan tiket resmi (*e-ticket*).

---

## 2. Akses Tiket Elektronik & Pencegahan IDOR

Untuk alasan privasi dan keamanan data (UU PDP):
- Penumpang hanya dapat melihat rincian tiket yang diterbitkan atas namanya sendiri melalui rute `/aviation/passenger/ticket/{ticket_id}`.
- Upaya mengakses ID tiket milik orang lain akan ditolak dengan galat `403 Forbidden` oleh sistem otorisasi berbasis kepemilikan (*IDOR Guard*).
- Pada dokumen tiket elektronik, nama lengkap penumpang didekripsi secara aman untuk ditampilkan di layar pengguna terverifikasi.

---

## 3. Pengajuan Pengembalian Dana Tiket (Refund Desk)

Penumpang berhak mengajukan pengembalian dana tiket melalui portal atau meja layanan pelanggan:
1. Buka tiket aktif yang belum terbang.
2. Tekan tombol **Ajukan Pembatalan & Refund**.
3. Sistem secara otomatis menerapkan algoritma perhitungan berjenjang (A4 `FareRefundCalculator`):
   - **Pembatalan > 72 Jam Sebelum Terbang:** Pengembalian 75% tarif dasar untuk kelas ekonomi standar.
   - **Pembatalan 24 s.d. 72 Jam:** Pengembalian 50% tarif dasar.
   - **Pembatalan < 24 Jam:** Pengembalian 25% tarif dasar (atau hangus untuk kelas PROMO non-refundable).
   - **Pembatalan Karena Force Majeure / Maskapai (PM 89/2015):** Pengembalian 100% penuh tarif dasar ditambah seluruh pajak bandara.
4. Dana refund akan dibukukan ke dalam akun kewajiban pengembalian dana dan diproses ke rekening pemesan.

