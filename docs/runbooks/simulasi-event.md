# Runbook: Kernel Simulasi Waktu Virtual & Digital Twin (Fase 67)

Runbook ini mendokumentasikan prosedur standar operasi (*Standard Operating Procedures* - SOP), langkah-langkah diagnostik kedaruratan, pemulihan dari kegagalan (*disaster recovery*), dan pemeliharaan rutin kernel simulasi Superweb.

---

## 1. SOP-01: Menjalankan Benchmark Tahunan Resmi (365 Hari)

### Tujuan:
Membuktikan determinisme sistem dan validasi integritas pembukuan lintas modul sebelum perilisan rilis besar platform.

### Langkah-Langkah:
1. Pastikan lingkungan kerja bersih dan koneksi basis data stabil.
2. Jalankan perintah migrasi dan pembersihan cache jika diperlukan:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```
3. Eksekusi simulasi benchmark resmi dengan seed baku $42$:
   ```bash
   php artisan sim:run 365 --seed=42 --name="official-benchmark-365d" --mode=live
   ```
4. Pantau kemajuan di layar terminal. Pastikan seluruh pilar domain (Hospital, Hotel, Logistics, Finance) terlaporkan pada setiap interval 30 hari.
5. Setelah selesai, catat fingerprint akhir 64 karakter hex:
   ```
   [OK] Simulation completed successfully!
   Final Hash Chain: 93a6c2...
   ```
6. Jalankan audit verifikasi kriptografis:
   ```bash
   php artisan sim:audit
   ```
   Pastikan exit code adalah `0` (SUCCESS).

---

## 2. SOP-02: Pemulihan dari Insiden Proses Terhenti (Resume Crash)

### Skenario:
Proses simulasi jangka panjang terhenti di tengah jalan (misal karena restart server atau kehabisan alokasi waktu eksekusi) pada hari ke-$N$ (misal hari 200).

### Langkah Pemulihan:
1. Buka konsol web `/admin/simulation` atau cari ID sesi yang berstatus `running` atau `paused`.
2. Periksa checkpoint terakhir yang berhasil disimpan di tabel `sim_checkpoints`:
   ```bash
   php artisan sim:diff --a="official-benchmark-365d" --b="official-benchmark-365d"
   ```
3. Jalankan perintah resume:
   ```bash
   php artisan sim:run 365 --seed=42 --name="official-benchmark-365d" --mode=live
   ```
   Sistem akan mendeteksi sesi yang ada, memuat checkpoint terakhir hari ke-200, dan melanjutkan iterasi dari hari ke-201 hingga 365.
4. Verifikasi bahwa hash akhir rantai sama persis dengan sesi yang berjalan tanpa interupsi.

---

## 3. SOP-03: Penyelidikan Diskrepansi Saldo Buku Besar (Ledger Probe Failure)

### Gejala:
Simulasi gagal saat mengeksekusi `ProbeLedgerBalanceAction` dengan pesan galat:
`Pelanggaran Invariant Ledger Probe (D35): Diskrepansi saldo terdeteksi: X`

### Tindakan Diagnostik:
1. Buka transaksi database yang bermasalah.
2. Jalankan kueri inspeksi entri ledger:
   ```sql
   SELECT COALESCE(SUM(amount), 0) AS net_bank FROM bank_ledger_entries;
   SELECT SUM(debit) AS total_debit, SUM(credit) AS total_credit FROM general_ledger_entries;
   ```
3. Identifikasi modul yang melakukan posting tidak berimbang (unbalanced entry) pada hari virtual terjadinya kegagalan.
4. Periksa log handler yang berjalan pada hari tersebut untuk melihat Action domain mana yang memposting entri jurnal sepihak tanpa pasangannya.
5. Perbaiki Action domain terkait dan jalankan kembali simulasi dalam mode sandbox untuk mengonfirmasi perbaikan:
   ```bash
   php artisan sim:run 10 --seed=42 --name="test-ledger-fix" --mode=sandbox
   ```

---

## 4. SOP-04: Pemangkasan Checkpoint & Optimasi Disk

### Masalah:
Tabel `sim_checkpoints` dan `sim_twin_snapshots` membengkak seiring bertambahnya jumlah sesi pengujian.

### Prosedur Rutin (Jadwal Mingguan):
1. Jalankan perintah pruning untuk sesi tertentu:
   ```bash
   php artisan sim:checkpoint-prune --run="<RUN_NAME_OR_ID>" --keep=5 --milestone=30
   ```
   Perintah ini akan menghapus checkpoint harian lama, namun mempertahankan 5 checkpoint terbaru serta checkpoint milestone setiap 30 hari.
2. Lakukan pengarsipan sesi simulasi yang sudah tidak aktif:
   Akses `/admin/simulation/{id}` dan lakukan transisi status ke `archived`.
3. Jalankan vacuum / optimize table pada SQLite atau PostgreSQL untuk mereklamasi ruang penyimpanan disk:
   ```bash
   sqlite3 database/database.sqlite "VACUUM;"
   ```

---

## 5. SOP-05: Penanganan Siklus Dependensi Handler (Deadlock / Infinite Loop)

### Gejala:
Pendaftaran handler baru memicu `CyclicDependencyException` saat aplikasi dinyalakan atau saat simulasi dimulai.

### Langkah Resolusi:
1. Baca rincian simpul siklus pada pesan galat:
   `Kahn Topological Order: Terdeteksi dependensi siklik antar modul: [A -> B -> C -> A]`
2. Buka berkas `ServiceProvider` modul terkait:
   - Periksa method `dependencies()` pada masing-masing handler.
3. Putus siklus dependensi dengan memindahkan kebutuhan data yang tidak langsung menjadi event terpisah atau restrukturisasi urutan ketergantungan.
4. Jalankan kembali test arsitektur untuk memvalidasi asiklisitas graf:
   ```bash
   vendor/bin/pest tests/Acceptance/SimKernel/SimKernelAlgorithmsTest.php
   ```

---

## 6. Eskalasi & Kontak Bantuan

| Tingkat Keparahan | Dampak Operasional | Waktu Respon Maksimum | Kanal Eskalasi |
|---|---|---|---|
| **Level 1 (Kritis)** | Korupsi rantai hash pada sesi produksi live, kegagalan probe saldo ledger | 15 Menit | Ruang Tanggap Darurat #emergency-core |
| **Level 2 (Tinggi)** | Divergensi deterministik antar dua simulasi dengan seed sama | 1 Jam | Tim Inti Kernel #sim-kernel-dev |
| **Level 3 (Sedang)** | Kegagalan ekspor CSV atau performa lambat pada skala beban tinggi | 4 Jam | Tiket JIRA / Issue Tracker Modul Core |
| **Level 4 (Rendah)** | Pertanyaan seputar pendaftaran handler baru atau konfigurasi tier | 1 Hari Kerja | Diskusi Platform Engineering |

