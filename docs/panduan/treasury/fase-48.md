# Panduan Operasional Peran: Treasury (Fase 48)

Dokumen ini memandu staf dan manajer **Treasury** dalam menjalankan operasional harian, mingguan, dan akhir periode di platform perbendaharaan enterprise.

---

## 1. Pemantauan Posisi Kas Global & Multi-Mata Uang

### Tugas
Memeriksa likuiditas kas operasional, saldo rekening bank per entitas, saldo valuta asing, dan kesesuaian nilai fungsional IDR buku besar.

### Langkah
1. Buka menu navigasi utama **Perbendaharaan** $\rightarrow$ **Posisi Kas** (`/treasury/positions`).
2. Gunakan filter dropdown untuk memilih **Entitas**, **Mata Uang**, atau **Nama Bank**.
3. Periksa ringkasan kartu posisi kas:
   - Total Likuiditas IDR Fungsional
   - Saldo Valas Terbuka (USD, EUR, SGD, JPY)
   - Rasio Kecukupan Kas Harian
4. Untuk mengunduh data posisi kas untuk analisis spreadsheet, klik tombol **Ekspor CSV**.

### Hasil
Tampilan tabel posisi kas yang mutakhir dari buku besar (`LedgerService`), rincian saldo per rekening bank, dan berkas CSV posisi kas terverifikasi.

---

## 2. Pengelolaan Kurs Harian Berversi & Input Manual

### Tugas
Mendaftarkan atau memperbarui kurs mata uang asing yang berlaku harian dengan integritas berversi (*immutable audit trail*).

### Langkah
1. Buka menu **Perbendaharaan** $\rightarrow$ **Manajemen Kurs Valas** (`/treasury/fx`).
2. Periksa feed kurs otomatis Bank Indonesia berlabel `bi_simulated`.
3. Untuk input kurs manual (misal kurs transaksi khusus atau kurs pajak):
   - Klik **Input Kurs Baru**.
   - Masukkan Mata Uang Asal (contoh `USD`), Mata Uang Tujuan (`IDR`), Tanggal Berlaku, dan Tipe Kurs (`spot`, `middle`, `tax`).
   - Masukkan Pembilang Rasional (contoh `15650`) dan Penyebut (`1`).
   - Klik **Simpan Versi Kurs**.

### Hasil
Versi kurs baru tersimpan dengan status tak dapat diubah (*immutable*). Seluruh konversi valas berikutnya menggunakan rate ID berversi ini.

---

## 3. Eksekusi Revaluasi Valas Akhir Periode & Pembalik Otomatis

### Tugas
Mengakui laba/rugi selisih kurs belum terealisasi (*unrealized gain/loss*) atas pos moneter valas pada penutupan bulan buku sesuai PSAK 10.

### Langkah
1. Buka menu **Perbendaharaan** $\rightarrow$ **Revaluasi Kurs** (`/treasury/revaluation`).
2. Pilih Periode Pelaporan (format `YYYY-MM`) dan pilih Mata Uang atau seluruh valas.
3. Klik **Hitung Pratinjau Revaluasi** (Langkah 1/2).
   - Tinjau daftar lot valas yang akan disesuaikan nilainya.
   - Periksa ringkasan estimasi jurnal: Akun Debit, Akun Kredit, dan Nominal IDR.
4. Klik **Konfirmasi & Posting Ledger** (Langkah 2/2). Sistem menerapkan *Idempotency-Key* otomatis.
5. Pada hari pertama periode kalender berikutnya, sistem (atau eksekusi perintah terjadwal `php artisan treasury:reverse-revaluations`) akan memposting jurnal pembalik otomatis.

### Hasil
Jurnal revaluasi tercatat di buku besar, saldo subledger `trs_fx_lots` terbarukan, dan jurnal pembalik dijadwalkan secara idempoten.

---

## 4. Rekonsiliasi Rekening Koran Bank & Penanganan Antrean Mismatch

### Tugas
Memadankan mutasi rekening bank impor dengan buku kas dan menyelesaikan mutasi yang belum cocok.

### Langkah
1. Buka menu **Perbendaharaan** $\rightarrow$ **Rekonsiliasi Bank** (`/treasury/reconciliation`).
2. Pilih rekening bank yang akan direkonsiliasi.
3. Unggah berkas mutasi bank (format CSV atau SWIFT MT940 simulasi).
4. Klik **Jalankan Rekonsiliasi Otomatis (3-Tahap)**:
   - Tahap 1: Exact Match (nominal dan referensi sama persis).
   - Tahap 2: Tolerance Match (toleransi nominal $\le 500$ minor dan tanggal $\le 3$ hari).
   - Tahap 3: Many-to-One Match (kombinasi 2..4 mutasi buku kas ke 1 mutasi bank).
5. Buka tab **Antrean Belum Terpadankan** (*Unmatched Queue*):
   - Untuk mutasi dengan selisih yang dapat diterima (misal biaya admin bank): klik **Setujui Selisih** dan pilih akun pembebanan.
   - Untuk mutasi yang keliru cocok: klik tombol **Batal Cocok (Unmatch)** dengan memasukkan alasan pembatalan.

### Hasil
Laporan rekonsiliasi bank seimbang, status mutasi bank terbarui, dan selisih rekonsiliasi yang disetujui diposting ke ledger secara akurat.

---

## 5. Lindung Nilai Forward Contract, MTM Harian & Roll-Over

### Tugas
Mengamankan eksposur mata uang asing dengan kontrak forward, memantau Mark-to-Market harian, dan mengeksekusi settlement atau roll-over saat jatuh tempo.

### Langkah
1. Buka menu **Perbendaharaan** $\rightarrow$ **Lindung Nilai Valas** (`/treasury/hedges`).
2. Klik **Kontrak Forward Baru**:
   - Isi Mata Uang, Jumlah Nosional, Kurs Forward Kontrak, dan Tanggal Jatuh Tempo.
   - Klik **Ajukan Persetujuan (Submit Draft)**.
3. Setelah disetujui oleh atasan berwenang via ApprovalEngine, dealer memasukkan nomor deal bank dan mengaktifkan kontrak (`booked` $\rightarrow$ `active`).
4. Pada tanggal jatuh tempo (`matured`):
   - Jika dilunasi kas: klik **Settle Kontrak**, masukkan kas masuk/keluar aktual, dan posting realized gain/loss.
   - Jika diperpanjang: klik **Roll-over Kontrak**, masukkan parameter kontrak forward baru yang tertaut.

### Hasil
Nilai pasar derivatif diakui di neraca secara harian, dan penyelesaian kontrak membukukan untung/rugi terealisasi secara sah.

---

## 6. Pengawasan Fasilitas Kredit & Pengendalian Covenant Bank

### Tugas
Mengajukan penarikan fasilitas kredit bank dengan kepatuhan terhadap rasio utang (DER dan ICR).

### Langkah
1. Buka menu **Perbendaharaan** $\rightarrow$ **Fasilitas Kredit Bank** (`/treasury/facilities`).
2. Pilih fasilitas bank yang aktif.
3. Klik **Ajukan Penarikan Dana (Drawdown)**:
   - Masukkan nominal penarikan IDR.
   - Sistem secara otomatis menghitung proyeksi rasio Debt-to-Equity (DER) integer permil pasca-penarikan.
   - Jika rasio melanggar covenant: tombol penarikan terkunci dan muncul peringatan pelanggaran.
   - Jika ada *Covenant Waiver* resmi dari bank pemberi pinjaman: masukkan nomor izin waiver dan ajukan persetujuan otorisasi ganda.

### Hasil
Penarikan fasilitas dibukukan ke rekening bank perusahaan dan akun kewajiban fasilitas kredit, atau ditolak demi melindungi kepatuhan hukum grup.

---

## 7. Eksekusi Cash Pooling & Zero-Balancing Grup

### Tugas
Mengoptimalkan likuiditas kas entitas anak dengan penyapuan saldo surplus ke rekening induk holding pada akhir hari.

### Langkah
1. Buka menu **Perbendaharaan** $\rightarrow$ **Cash Pooling** (`/treasury/pools`).
2. Tinjau posisi saldo seluruh rekening anak vs target balance operasional minimum.
3. Klik **Eksekusi Sweep Harian**:
   - Rekening anak dengan saldo di atas target disapu ke rekening induk.
   - Sistem mencatat penambahan pinjaman antar-perusahaan (*Intercompany Loan*) berbunga arm's length.
   - Jika rekening anak defisit: sistem mengeksekusi *Reverse Sweep* untuk mengisi kembali likuiditas anak tanpa menghasilkan saldo negatif.

### Hasil
Likuiditas terpusat di rekening induk, kas mengendap diminimalkan, dan seluruh arus dana tercatat sebagai pinjaman antar-entitas yang sah.
