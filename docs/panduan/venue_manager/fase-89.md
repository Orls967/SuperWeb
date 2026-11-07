# Panduan Pengguna: Venue Manager (Fase 89)

Dokumen ini merupakan panduan operasional lengkap bagi peran `venue_manager` dalam mengelola operasional harian tempat hiburan, kalender acara, kuota tier tiket dinamis, reservasi meja VIP, perizinan kepolisian, dan penutupan acara.

---

## 1. Peran, Akses & Batas Tanggung Jawab
Peran `venue_manager` memiliki hak akses penuh terhadap menu:
- `/venue/events`: Daftar dan manajemen seluruh acara hiburan.
- `/venue/tables`: Pengaturan denah meja VIP, deposit, dan minimum spend.
- `/venue/permits`: Monitoring surat izin keramaian gapless kepolisian.
- `/venue/reports/export`: Ekspor berkas CSV resmi untuk audit akuntansi.

---

## 2. Alur Kerja Operasional Harian

### 2.1 Pendaftaran Acara Baru & Penetapan Batas Harga Tiket
1. Akses menu `/venue/events` pada bilah navigasi utama.
2. Klik tombol **+ Buat Acara Baru**.
3. Formulir pendaftaran meminta input berikut:
   - **Nama Acara**: Judul komersial acara (contoh: *Bali Sunset Electronic Festival 2026*).
   - **Pilihan Venue**: Pilih lokasi venue dari master venue terdaftar.
   - **Waktu Pintu Dibuka (*Doors Open*)**: Jadwal pembukaan gerbang masuk penonton.
   - **Harga Dasar Tier (*Base Tier Prices*)**:
     - Early Bird: Batas awal penjualan (H-30).
     - General Admission (GA): Penjualan reguler (H-7 hingga H-1).
     - Door Price: Penjualan langsung di loket pada hari-H.
   - **Batas Floor & Ceiling**:
     - *Ticket Floor Price*: Batas terendah jaminan artis (contoh: Rp 280.000).
     - *Ticket Ceiling Price*: Batas tertinggi kewajaran pasar (contoh: Rp 500.000).
4. Klik **Simpan Acara**. Acara tersimpan dalam status `draft`.
5. **Dampak Sistem**: Acara belum muncul di portal publik tamu hingga izin keramaian disetujui.

---

### 2.2 Penautan Surat Izin Keramaian (Permit Linking)
1. Buka tab **Perizinan & Kepatuhan** pada detail acara atau akses `/venue/permits`.
2. Klik tombol **Tautkan Izin Kepolisian**.
3. Masukkan nomor seri surat izin yang diterbitkan Ditintelkam Kepolisian Daerah setempat. Nomor izin diverifikasi keasliannya dan dicatat secara gapless via `DocumentNumberingInterface`.
4. Masukkan kapasitas maksimum legal yang disetujui instansi kepolisian (contoh: $2.000\text{ orang}$).
5. Klik **Konfirmasi Izin**.
6. Status acara otomatis beralih dari `draft` $\to$ `permit_pending` $\to$ `on_sale`.
7. **Dampak Sistem**: Tiket mulai dapat dibeli oleh tamu melalui portal `/guest/tickets`.

---

### 2.3 Pemantauan Denah Meja VIP & Penanganan Minimum Spend
1. Buka menu `/venue/tables`.
2. Halaman menampilkan grid visual denah meja (*table layout*):
   - **Tabel Hijau**: Meja tersedia (*AVAILABLE*).
   - **Tabel Kuning**: Meja dalam proses pemesanan/hold deposit (*HELD*).
   - **Tabel Biru**: Meja terkonfirmasi dan tamu telah tiba (*SEATED*).
   - **Tabel Abu-Abu**: Meja selesai (*SETTLED*).
3. Klik salah satu meja VIP untuk melihat detail reservasi:
   - Nama Tamu dan Nomor Meja.
   - Komitmen *Minimum Spend* (contoh: Rp 5.000.000).
   - Uang Muka Deposit Terkunci di Escrow (contoh: Rp 2.000.000).
   - Akumulasi Pesanan Konsumsi POS Resto Aktual (terhubung via kontrak G7-K17).
4. **Penyelesaian Tagihan Meja**:
   - Jika konsumsi tamu $\ge$ minimum spend, kasir membebankan tagihan riil dan deposit escrow di-capture.
   - Jika konsumsi tamu $<$ minimum spend, kasir menekan tombol **Selesaikan Tagihan Minimum Spend**. Sistem secara otomatis membukukan selisih kekurangan sebagai pendapatan *shortfall* (`ven:table_revenue:IDR`).

---

### 2.4 Penanganan Kasus Tamu Meja Tidak Hadir (No-Show Forfeiture)
1. Batas toleransi kehadiran tamu meja VIP adalah pukul 23:00 WITA pada hari acara.
2. Jika tamu tidak hadir dan tidak ada konfirmasi keterlambatan, Venue Manager membuka reservasi meja terkait dan menekan tombol **Eksekusi No-Show Forfeiture**.
3. Sistem secara otomatis menjalankan algoritma V4 `MinimumSpendSettlement`:
   - Uang muka deposit (contoh: Rp 2.000.000) dipotong $50\%$ sebagai denda pembatalan sepihak (Rp 1.000.000).
   - Jurnal buku besar mendebit `ven:vip_unearned_rev:IDR` dan mengkredit `ven:vip_noshow_rev:IDR` sebesar Rp 1.000.000.
   - Sisa $50\%$ (Rp 1.000.000) dirilis kembali ke rekening/kartu kredit pelanggan melalui modul pembayaran.
   - Status meja beralih ke `no_show_forfeited` dan meja dapat dialokasikan ke tamu lain (*walk-in VIP*).

---

### 2.5 Penutupan Acara & Pengakuan Pendapatan Definitif (Event Closing)
1. Setelah pertunjukan musik usai dan seluruh tamu meninggalkan venue (okupansi zona $= 0$), buka detail acara pada `/venue/events/{event}`.
2. Pastikan tabel rekonsiliasi menampilkan:
   - Seluruh tiket penonton berstatus `used` atau `scanned_out`.
   - Seluruh meja VIP berstatus `settled` atau `no_show_forfeited`.
3. Klik tombol **Tutup Acara (Close Entertainment Event)**.
4. Sistem memproses transaksi penutupan:
   - Status acara beralih dari `live` $\to$ `closed`.
   - Mengubah status tiket dari `scanned_in`/`scanned_out` menjadi `used`.
   - Menghitung total saldo tiket belum diakui pada `ven:ticket_unearned:IDR`.
   - Melakukan pemindahan jurnal buku besar:
     - **Debit**: `ven:ticket_unearned:IDR`
     - **Kredit**: `ven:ticket_revenue:IDR`
5. **Dampak Finansial**: Pendapatan tiket secara resmi diakui dalam laporan laba rugi perusahaan (*Income Statement*).

---

### 2.6 Eksekusi Pembatalan Acara Darurat (Force Majeure Saga)
1. Dalam hal terjadi cuaca badai ekstrem atau surat pencabutan izin mendadak dari kepolisian, Venue Manager membuka acara terkait dan menekan tombol **Batalkan Acara Darurat**.
2. Masukkan alasan resmi pembatalan (contoh: *Peringatan Dini Cuaca BMKG - Gelombang Pasang 4 Meter*).
3. Konfirmasi eksekusi Saga Pembatalan:
   - Status acara berubah menjadi `cancelled`.
   - Sistem membalik seluruh tiket berstatus `issued` menjadi `refunded`. Kas dikembalikan ke rekening pembeli, saldo `ven:ticket_unearned:IDR` dan kewajiban pajak `ven:entertainment_tax_payable:IDR` didebit kembali.
   - Seluruh deposit meja VIP dikembalikan utuh $100\%$ tanpa denda no-show.
   - Kontrak asuransi G7-K15 `EventCoverBinder` otomatis mengklaim biaya kerugian persiapan acara kepada penjamin asuransi.
