# Panduan Pengguna: Event Sales Manager (Fase 114)

## 1. Peran & Tanggung Jawab
Event Sales Manager bertugas mengelola proses akuisisi klien korporat, asosiasi industri, wedding organizer, dan pameran perdagangan dari tahap prospek (*lead*) hingga penandatanganan kontrak (*contract signing*).

## 2. Fitur Utama & Alur Kerja
### 2.1 Pembuatan Proposal MICE Multi-Komponen
1. Masuk ke menu **MICE Sales Hub -> Pipeline Board**.
2. Klik tombol **Buat Proposal Baru**.
3. Masukkan komponen penawaran:
   - Ballroom / Ruang Rapat (durasi sewa, susunan meja: U-shape, classroom, banquet).
   - Paket Jamuan Makan (Coffee break, Lunch buffet, Gala dinner).
   - Kamar Blok untuk Delegasi (jumlah kamar, tipe kamar, tanggal check-in/out).
   - Paket Audio Visual & Panggung.
   - Stan Booth Pameran & Add-on Listrik.
4. Tentukan masa berlaku harga penawaran (*Quotation Timelock*). Penawaran standar berlaku selama 14 hari kalender.

### 2.2 Validasi Margin Kotor & Guardrail 40%
1. Sistem akan secara otomatis menjalankan kalkulasi margin kotor menggunakan mesin `EventMarginCalculator`.
2. Jika margin kotor $\ge 40\%$, proposal berstatus `GUARDRAIL_COMPLIANT_APPROVED` dan tombol **Cetak Penawaran Resmi** aktif.
3. Jika margin kotor $< 40\%$, proposal otomatis ditandai `GUARDRAIL_BREACH_REJECTED`. Sistem menampilkan jumlah kekurangan harga minimum yang wajib dinaikkan. Jika klien menolak kenaikan harga, Sales Manager wajib mengajukan form eskalasi diskon khusus ke General Manager.

### 2.3 Konversi Proposal Menjadi Kontrak & SPK
1. Setelah klien menyetujui penawaran, ubah status proposal menjadi `ACCEPTED`.
2. Klik **Generate SPK / Kontrak MICE**.
3. Sistem secara otomatis membuat jadwal termin pembayaran (*Milestone Schedule*) melalui mesin `MilestoneScheduleBuilder`.
4. Berikan tautan pembayaran termin DP 20% kepada bendahara/keuangan klien.
