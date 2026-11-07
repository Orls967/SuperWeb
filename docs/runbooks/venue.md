# Runbook Operasional Modul Venue & Entertainment

Dokumen panduan operasional teknis dan mitigasi insiden untuk operator sistem, Site Reliability Engineer (SRE), dan pengelola tempat hiburan (*Venue Manager*).

---

## 1. Daftar Perintah Artisan Terjadwal & Rutin

### 1.1 `php artisan ven:audit` (atau `venue:audit`)
- **Tujuan Bisnis**: Memverifikasi kesetimbangan dua sumber (*two-source reconciliation*) antara status buku besar perbankan (`Modules\Banking`) dan data faktual operasional tiket serta meja VIP (`Modules\Venue`).
- **Pemeriksaan yang Dilakukan**:
  1. **Rekonsiliasi Unearned Revenue**: Total nilai nominal tiket berstatus `issued` pada acara yang belum `closed` wajib sama persis dengan saldo akun kewajiban `ven:ticket_unearned:IDR`.
  2. **Integritas Hash-Chain**: Seluruh baris tiket per event diverifikasi hash SHA-256 dan tautan `prev_hash`-nya untuk memastikan tidak ada pemalsuan atau injeksi data manual pada basis data.
  3. **Penomoran Gapless**: Nomor urut tiket (`ticket_number`) per event diverifikasi berurutan tanpa ada nomor yang hilang (*zero gap*).
  4. **Pematuhan Batas Kapasitas Fisik**: Nilai okupansi aktif ($\sum IN - \sum OUT$) pada seluruh zona diverifikasi tidak melebihi kapasitas efektif ($\lfloor \text{legal} \times 0,95 \rfloor$).
  5. **Rekonsiliasi Escrow Meja VIP**: Saldo akun `ven:vip_escrow:IDR` dan `ven:vip_unearned_rev:IDR` diverifikasi terhadap total booking aktif berstatus `deposit_held` dan `confirmed`.
- **Jadwal Eksekusi**: Terjadwal setiap 30 menit melalui cron job sistem, serta wajib dijalankan sebelum penutupan buku harian.
- **Idempotensi**: Idempoten penuh (hanya membaca data secara read-only tanpa memutasi state atau memicu posting baru).
- **Penanganan Kegagalan (Exit Code 1)**:
  - Periksa ringkasan galat pada tabel keluaran terminal.
  - Jika terjadi diskrepansi unearned revenue, cari tiket berstatus `issued` yang tidak memiliki pasangan transaksi di `PostingDTO` ledger.
  - Jika terjadi kerusakan hash-chain, isolasi record tiket yang memiliki hash tidak cocok dan laporkan indikasi manipulasi basis data.
  - Jika kapasitas zona terlampaui, periksa pintu putar gerbang dan pastikan flag lockdown otomatis aktif.

---

### 1.2 `php artisan venue:close-event {event}`
- **Tujuan Bisnis**: Menutup acara hiburan secara definitif, mengubah status state machine dari `live` menjadi `closed`, dan memindahkan seluruh saldo kewajiban tiket belum diakui (`ven:ticket_unearned:IDR`) menjadi laba pendapatan riil (`ven:ticket_revenue:IDR`).
- **Argumen Perintah**:
  - `event`: ID atau kode unik acara (contoh: `EVT-2026-SUNSET-01`).
- **Jadwal Eksekusi**: Dijalankan secara otomatis pada jam tutup resmi venue (pukul 04:00 WITA) atau dieksekusi secara manual oleh Venue Manager setelah seluruh penonton meninggalkan lokasi.
- **Idempotensi**: Idempoten penuh. Jika event telah berstatus `closed` atau `settled`, perintah tidak akan memicu pemindahan saldo ganda dan keluar dengan pesan informasi sukses.
- **Langkah Pemulihan Jika Gagal**:
  - Periksa apakah ada transaksi pemindaian tiket yang masih menggantung atau belum tersinkronisasi.
  - Pastikan modul perbankan (`Modules\Banking`) dalam status operasional normal dan tidak terkunci oleh proses penutupan periode fiskal.

---

### 1.3 `php artisan venue:release-expired-holds`
- **Tujuan Bisnis**: Melepaskan reservasi sementara (*temporary quota hold*) tiket atau alokasi kursi meja yang tidak diselesaikan pembayarannya dalam batas jendela waktu tunggu (15 menit).
- **Jadwal Eksekusi**: Dijalankan setiap 1 menit melalui cron (`* * * * *`).
- **Idempotensi**: Idempoten; hanya memutasi record dengan kriteria `status = 'HELD'` dan `held_until < NOW()`.
- **Dampak Basis Data**: Mengembalikan kuota yang terkunci pada `ven_inventory_counters` sehingga dapat dibeli kembali oleh penonton lain yang mengantre.

---

### 1.4 `php artisan venue:permit-expiry-scan`
- **Tujuan Bisnis**: Memindai masa berlaku izin keramaian dari instansi kepolisian dan pemda. Memberikan peringatan dini (*warning advisory*) jika izin akan kedaluwarsa dalam 48 jam dan membekukan penjualan tiket jika izin telah kedaluwarsa.
- **Jadwal Eksekusi**: Dijalankan setiap hari pada pukul 06:00 WITA.
- **Idempotensi**: Idempoten; memperbarui status record pada `ven_permits`.

---

### 1.5 `php artisan venue:weather-check`
- **Tujuan Bisnis**: Mengambil data prakiraan cuaca maritim pesisir (kecepatan angin, curah hujan, tinggi gelombang) dari stasiun BMKG dan memperbarui tabel faktor eksternal `ven_external_factors` secara berversi.
- **Jadwal Eksekusi**: Dijalankan setiap 15 menit.
- **Idempotensi**: Idempoten; jika data parameter versi terbaru sudah tercatat untuk tanggal/jam tersebut, sistem tidak akan mencatat duplikasi.

---

## 2. Prosedur Tanggap Darurat & Penanganan Bencana (Disaster Recovery)

### Skenario 1: Cuaca Badai Pesisir Ekstrem Mendadak (Evakuasi & Pembatalan)
1. BMKG atau sensor lapangan melaporkan curah hujan ekstrem $\ge 80\text{ mm/jam}$ atau angin kencang $\ge 40\text{ knot}$.
2. Petugas `crowd_safety` mengaktifkan protokol penguncian zona luar ruangan via `/venue/zones`.
3. Venue Manager mengeksekusi perintah pembatalan darurat atau memanggil action `CancelEventWithCompensationSagaAction`.
4. Sistem menjalankan langkah-langkah kompensasi saga otomatis:
   - Status event beralih ke `cancelled`.
   - Seluruh tiket penonton otomatis berstatus `refunded`, memicu pembalikan jurnal akrual unearned revenue dan pajak PBJT.
   - Seluruh deposit meja VIP dikembalikan ke rekening/dompet pelanggan.
   - Polis asuransi pembatalan acara di modul Insurance (G7-K15) otomatis mengajukan klaim kerugian biaya operasional.

### Skenario 2: Terputusnya Jaringan Internet di Pintu Masuk Gerbang (Offline Scanner Fallback)
1. Pemindai gerbang mobile 375 px beralih otomatis ke mode penyimpanan lokal sementara (*Local Storage Buffer*).
2. Tanda tangan digital tiket diverifikasi secara lokal menggunakan kunci publik HMAC venue yang telah disimpan di memori perangkat.
3. Log pemindaian dan nonce disimpan secara append-only di perangkat.
4. Ketika konektivitas pulih, antrean pindaian disinkronkan secara otomatis ke server melalui endpoint idempoten `/venue/gate/sync-batch`.
