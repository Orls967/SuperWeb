# Panduan Operasional Lengkap Petugas Front Desk (Fase 91: PMS & Layanan Tamu)

Dokumen ini memuat panduan langkah demi langkah bagi staf Front Desk, Receptionist, dan Duty Manager dalam menjalankan kegiatan operasional harian hotel.

---

## 1. Prosedur Penyambutan & Check-In Tamu Datang (Arrival Guest)

1. **Penyambutan Tamu**:
   - Berikan salam standar perhotelan (*warm greeting*) dan tanyakan nama tamu atau nomor konfirmasi reservasi.
2. **Pemeriksaan Status Reservasi di Sistem**:
   - Akses menu `/hotel/front-desk`.
   - Cari data reservasi berdasarkan nama atau nomor reservasi.
   - Pastikan status reservasi berada dalam status `confirmed` atau `reconfirmed`.
3. **Pemeriksaan Dokumen Identitas Tamu (UU PDP & UU Keimigrasian)**:
   - **Tamu Warga Negara Indonesia (WNI)**: Mintakan KTP fisik. Input nomor NIK dan pastikan sistem mengenkripsi data secara otomatis.
   - **Tamu Warga Negara Asing (WNA)**: Mintakan paspor asli. Periksa masa berlaku paspor (minimal 6 bulan), input nomor paspor, kebangsaan, dan verifikasi status pembayaran Pungutan Wisatawan Asing Bali (Tourist Levy PWA) jika properti berada di Bali.
4. **Penerimaan Deposit Jaminan (Security Deposit)**:
   - Gesek kartu kredit tamu untuk pra-otorisasi jaminan kamar atau terima uang tunai deposit.
   - Masukkan nominal deposit ke folio tamu dengan Idempotency-Key unik.
5. **Penetapan Kamar Fisik (Room Allocation)**:
   - Pilih kamar yang telah berstatus `inspected` atau `available`. Dilarang keras menempatkan tamu pada kamar yang masih berstatus `dirty` atau `cleaning`.
6. **Penerbitan Kunci Digital Kamar (Smart Lock IoT)**:
   - Klik tombol **Terbitkan Kunci Digital**. Sistem akan meng-generate sesi token QR yang dapat dipindai tamu melalui ponsel pintar mereka di Portal Tamu.
   - Jika tamu meminta kartu fisik, letakkan kartu RFID pada alat encoder meja dan klik **Encode Kartu Kunci**.

---

## 2. Prosedur Penanganan Tamu Overbooking (Walk Guest Relocation)

Apabila terjadi situasi overbooking pada saat seluruh kapasitas fisik terisi:
1. Tetap tenang, bawa tamu ke ruang tunggu VIP atau Executive Lounge, dan berikan minuman selamat datang.
2. Buka menu **Overbooking Simulator & Walk Relocation** di sistem PMS.
3. Sistem secara otomatis mencari ketersediaan kamar setara atau kategori lebih tinggi di properti jaringan hotel mitra dalam radius terdekat.
4. Klik **Jalankan Saga Relokasi**:
   - Sistem melakukan pemesanan kamar di hotel mitra penerima.
   - Sistem memanggil armada transportasi shuttle VIP ber-AC via integrasi logistik untuk mengantar tamu dan barang bawaannya.
   - Sistem menerbitkan voucher kompensasi pemulihan layanan (H5) senilai tarif kamar ditambah biaya akomodasi malam pertama gratis.
5. Berikan penjelasan dengan penuh empati dan serahkan paket kompensasi kepada tamu.

---

## 3. Prosedur Check-Out & Penyelesaian Folio Tamu (Departure Guest)

1. Tanyakan nomor kamar tamu dan mintakan kembali kartu kunci fisik jika tamu menggunakan kartu RFID.
2. Periksa rincian baris folio tagihan tamu (sewa kamar harian, layanan F&B resto, konsumsi minibar, dan binatu laundry).
3. **Penyelesaian Saldo Folio (Zero Balance Rule)**:
   - Jika saldo akhir bernilai positif (masih ada tagihan): Mintakan pelunasan pembayaran melalui kartu kredit, debit, QRIS, atau transfer penjamin korporat.
   - Jika ada kelebihan deposit tamu: Lakukan proses pengembalian deposit (*refund*) ke rekening atau tunai tamu.
4. Pastikan saldo folio menunjukkan nilai **Rp 0 (NOL)**.
5. Klik **Konfirmasi Check-Out**:
   - Status reservasi otomatis berubah ke `checked_out`.
   - Sesi kunci kamar digital otomatis dicabut dan dinonaktifkan di sistem pintu kamar.
   - Status kamar fisik seketika berubah menjadi `dirty` dan muncul di antrean tim Tata Graha.
6. Ucapkan terima kasih dan doakan perjalanan tamu menyenangkan.
