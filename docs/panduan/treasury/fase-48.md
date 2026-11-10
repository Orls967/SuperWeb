# Panduan Operasional Peran: Treasury (Fase 48)

Dokumen ini memandu staf, dealer, dan manajer **Treasury** dalam menjalankan operasional harian, mingguan, dan akhir periode di platform perbendaharaan enterprise multi-mata uang dan multi-entitas grup.

---

## 1. Pemantauan Posisi Kas Global & Multi-Mata Uang

### Tugas
Memeriksa likuiditas kas operasional, saldo rekening bank per entitas, saldo valuta asing, dan kesesuaian nilai fungsional IDR buku besar.

### Langkah
1. Buka menu navigasi utama **Perbendaharaan** $\rightarrow$ **Posisi Kas** (`/treasury/positions`).
2. Gunakan filter dropdown untuk memilih **Entitas**, **Mata Uang**, atau **Nama Bank**.
3. Periksa ringkasan kartu posisi kas:
   - Total Likuiditas IDR Fungsional
   - Saldo Valas Terbuka (USD, EUR, SGD, JPY, GBP, CNY, AUD, HKD)
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
   - Masukkan Mata Uang Asal (contoh `USD`), Mata Uang Tujuan (`IDR`), Tanggal Berlaku, dan Tipe Kurs (`SPOT`, `CLOSING`, `TAX`, `HISTORICAL`).
   - Masukkan Pembilang Rasional (contoh `15650`) dan Penyebut (`1`).
   - Klik **Simpan Versi Kurs**.

### Hasil
Versi kurs baru tersimpan dengan status tak dapat diubah (*immutable*). Seluruh konversi valas berikutnya menggunakan rate ID berversi ini tanpa pembagian floating-point.

---

## 3. Eksekusi Revaluasi Valas Akhir Periode & Pembalik Otomatis

### Tugas
Mengakui laba/rugi selisih kurs belum terealisasi (*unrealized gain/loss*) atas pos moneter valas pada penutupan bulan buku sesuai PSAK 10 / IAS 21.

### Langkah
1. Buka menu **Perbendaharaan** $\rightarrow$ **Revaluasi Kurs** (`/treasury/revaluation`).
2. Pilih Periode Pelaporan (format `YYYY-MM`) dan pilih Mata Uang atau seluruh valas.
3. Klik **Hitung Pratinjau Revaluasi** (Langkah 1/2).
   - Tinjau daftar lot valas yang akan disesuaikan nilainya dengan metode FIFO.
   - Periksa ringkasan estimasi jurnal: Akun Debit, Akun Kredit, dan Nominal IDR.
   - Periksa sertifikat SHA-256 yang diterbitkan oleh Algoritma D1.
4. Klik **Konfirmasi & Posting Ledger** (Langkah 2/2). Sistem menerapkan *Idempotency-Key* otomatis.
5. Pada hari pertama periode kalender berikutnya, jalankan perintah terjadwal pembalik:
   ```bash
   php artisan treasury:reverse-revaluations --period=YYYY-MM
   ```

### Hasil
Jurnal revaluasi tercatat di buku besar, saldo subledger `trs_fx_lots` disesuaikan sementara, dan jurnal pembalik memulihkan kurs perolehan awal subledger secara idempoten.

---

## 4. Rekonsiliasi Rekening Koran Bank & Penanganan Antrean Mismatch

### Tugas
Memadankan mutasi rekening bank impor dengan buku kas dan menyelesaikan mutasi yang belum cocok melalui algoritma 3-tahap (D2).

### Langkah
1. Buka menu **Perbendaharaan** $\rightarrow$ **Rekonsiliasi Bank** (`/treasury/reconciliation`).
2. Pilih rekening bank yang akan direkonsiliasi.
3. Unggah berkas mutasi bank (format CSV atau SWIFT MT940 simulasi).
4. Klik **Jalankan Rekonsiliasi Otomatis (3-Tahap)**:
   - Tahap 1: *Exact Match* (nominal, tanggal, dan nomor referensi unik cocok 100%).
   - Tahap 2: *Fuzzy Match* (nominal cocok, tanggal dalam batas toleransi $\pm 3$ hari, kesamaan referensi $\ge 80\%$).
   - Tahap 3: *Window & Amount Match* (nominal cocok dalam jendela tanggal tanpa referensi).
5. Buka tab **Antrean Belum Terpadankan** (*Unmatched Queue*):
   - Untuk biaya administrasi bank atau bunga jasa giro: catat voucher kas dan sesuaikan.
   - Untuk setoran dalam perjalanan (*deposit-in-transit*): biarkan sebagai pos penyesuaian rekonsiliasi hingga rekening koran periode berikutnya.

### Hasil
Laporan rekonsiliasi bank seimbang, status mutasi bank terbarui (`MATCHED`), pos tak cocok tercatat di `trs_bank_recon_unmatched` dengan sertifikat D2 ber-hash SHA-256.

---

## 5. Lindung Nilai Forward Contract, MTM Harian & Penyelesaian

### Tugas
Mengamankan eksposur mata uang asing dengan kontrak forward, memantau Mark-to-Market harian, dan mengeksekusi settlement saat jatuh tempo.

### Siklus Hidup Status Kontrak (State Machine):
1. **DRAFT**: Kontrak baru diinput oleh dealer treasury.
2. **DESIGNATED**: Kontrak ditautkan dengan underlying exposure resmi (PO impor / faktur valas).
3. **ACTIVE**: Konfirmasi deal diterima dari bank mitra, kontrak berjalan.
4. **MATURED**: Tanggal jatuh tempo tercapai, siap untuk penyelesaian.
5. **SETTLED**: Settlement kas dieksekusi dengan kurs spot aktual; gain/loss terealisasi diposting ke ledger.
6. **TERMINATED**: Pembatalan dini dengan persetujuan komite risiko.
7. **DEFAULTED**: Wanprestasi pihak lawan counterparty.

### Prosedur Penyelesaian (Settlement):
1. Masukkan kurs spot penutupan pada tanggal jatuh tempo.
2. Eksekusi `POST /api/treasury/hedges/{id}/transition` dengan aksi `settle`.
3. Verifikasi pemenuhan single-sign ledger: selisih kurs spot vs forward diposting ke akun realized gain/loss.

---

## 6. Pengawasan Fasilitas Kredit & Pengendalian Covenant Bank

### Tugas
Mengajukan penarikan fasilitas kredit bank dengan kepatuhan mutlak terhadap rasio utang (*Debt-to-Equity Ratio* / DER).

### Langkah & Aturan Kepatuhan:
1. Buka menu **Perbendaharaan** $\rightarrow$ **Fasilitas Kredit Bank** (`/treasury/facilities`).
2. Pilih fasilitas bank aktif dan periksa plafon, sisa limit, dan ambang batas `max_der_permil`.
3. Klik **Ajukan Penarikan Dana (Drawdown)**:
   - Masukkan nominal penarikan IDR.
   - Masukkan rasio DER saat ini dalam satuan permil integer (misal: DER 2,50x diinput sebagai `2500`).
4. **Guardrail Covenant**:
   - Jika rasio DER $\ge$ ambang batas maksimal: transaksi **DITOLAK KERAS** (`CovenantBreachException`).
   - Jika rasio DER $\ge 90\%$ dari ambang batas: sistem otomatis menerbitkan task peringatan prioritas tinggi via `TreasuryTaskInboxNotifier` ke inbox pengawas treasury.
5. Jika transaksi sah, jurnal single-sign dibukukan:
   - Bank Operasional: Debit (`-amount`)
   - Kewajiban Pinjaman Bank: Kredit (`+amount`)

---

## 7. Eksekusi Cash Pooling & Zero-Balancing Grup

### Tugas
Mengoptimalkan likuiditas kas entitas anak dengan penyapuan saldo surplus ke rekening induk holding pada akhir hari kerja.

### Prosedur Operasional:
1. Buka menu **Perbendaharaan** $\rightarrow$ **Cash Pooling** (`/treasury/pools`).
2. Periksa saldo rekening anak terdaftar vs target balance minimum operasional.
3. Eksekusi sweep harian:
   - Rekening anak dengan saldo di atas target disapu ke rekening konsentrasi induk.
   - Dibuat jurnal berpasangan: mutasi kas bank dan rekening koran pinjaman antar-entitas (Intercompany Loan clearing).
   - Jika rekening anak defisit: sweep balik dilakukan untuk menyuntik likuiditas operasional.
4. Netto arus dana pooling dilaporkan dalam laporan berkala penyapuan kas pool.

---

## 8. Prosedur Ekspor 8 Laporan Resmi Treasury (CSV)

Sistem menyediakan 8 exporter CSV terstandardisasi untuk kebutuhan audit, kepatuhan fiskal, dan pelaporan manajemen:

```bash
# Mengekspor seluruh laporan sekaligus ke storage/app/treasury_reports:
php artisan treasury:export-reports --type=all

# Mengekspor laporan spesifik:
php artisan treasury:export-reports --type=fx-positions --currency=USD
php artisan treasury:export-reports --type=fx-lots --currency=EUR
php artisan treasury:export-reports --type=revaluations --period=2026-11
php artisan treasury:export-reports --type=hedges
php artisan treasury:export-reports --type=facilities
php artisan treasury:export-reports --type=cash-pools
php artisan treasury:export-reports --type=reconciliation
php artisan treasury:export-reports --type=forecast --currency=IDR
```

Setiap berkas CSV memuat header kolom resmi, nilai moneter bilangan bulat (minor units), kode mata uang ISO 4217, dan stempel waktu ekspor.

---

## 9. Prosedur Audit Dua Sumber (Dual-Source Audit)

Untuk memastikan tidak terjadi penyimpangan satu sen pun antara buku besar dan subledger:

1. Jalankan audit treasury harian:
   ```bash
   php artisan treasury:audit
   ```
2. Parameter yang diverifikasi secara matematis:
   - **Keseimbangan Single-Sign Ledger**: $\sum \text{Debit} + \sum \text{Kredit} = 0$ untuk setiap akun treasury.
   - **Rekonsiliasi Lot Valas vs Ledger**: $\sum \text{FxLot.remaining\_amount} = \text{Saldo Buku Besar Valas}$.
   - **Rekonsiliasi Fasilitas Kredit**: $\sum \text{Facility.drawn\_amount} = \text{Saldo Utang Bank di Ledger}$.
   - **Konsistensi Saldo Rekonsiliasi Bank**: Mutasi matched + unmatched = mutasi impor rekening koran.
3. Bila ditemukan selisih:
   - Perintah keluar dengan kode non-nol (`exit 1`).
   - Sistem mencatat rincian anomali ke tabel audit dan log security.
   - Dealer dilarang melakukan penutupan buku (*period freeze*) sebelum selisih diselesaikan.
