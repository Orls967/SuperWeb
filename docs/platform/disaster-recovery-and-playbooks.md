# Buku Panduan Pemulihan Bencana & Prosedur Tanggap Darurat Rilis

Dokumen ini memuat skenario kegagalan bencana (disaster scenarios), matriks eskalasi, dan langkah tanggap darurat teknis untuk insinyur keandalan sistem (SRE) dan tim rilis platform.

---

## 1. Klasifikasi Tingkat Keparahan Insiden Rilis

Platform membagi insiden deployment ke dalam empat tingkat keparahan:

| Tingkat | Kriteria Dampak | Waktu Respons Target (MTTA) | Tindakan Segera |
|---|---|---|---|
| **SEV-1 (Bencana)** | Kematian total layanan produksi, kehilangan data, atau pelanggaran keamanan masif | < 5 Menit | Auto-Rollback seketika, rakit tim krisis (War Room), eskalasi ke VP Eng |
| **SEV-2 (Kritis)** | Fitur utama transaksi atau gateway pembayaran terdegradasi parah | < 15 Menit | Hentikan kenaikan lalu lintas canary, evaluasi rollback parsial |
| **SEV-3 (Mayor)** | Masalah fungsional non-transaksional pada modul sekunder | < 1 Jam | Terapkan hotfix out-of-band melalui Release Train aktif |
| **SEV-4 (Minor)** | Gangguan kosmetik atau inkonsistensi teks dashboard pelaporan | < 4 Jam | Dijadwalkan ke Release Train berikutnya |

---

## 2. Playbook 1: Kegagalan Prosedur Auto-Rollback Otomatis

### Gejala:
Algoritma A6 mendeteksi error spike dan mengeluarkan rekomendasi `ROLLBACK`, namun perintah pembalikan gagal dijalankan (misalnya karena gangguan koneksi API load balancer atau kegagalan orkestrator kontainer).

### Prosedur Penanganan Darurat:
1. **Langkah 1 - Pemutusan Lalu Lintas Manual**:
   Akses gateway proksi secara langsung dan arahkan 100% lalu lintas ke pod versi rilis sebelumnya:
   ```bash
   php artisan platform:canary-traffic-override --percentage=0 --force
   ```
2. **Langkah 2 - Paksa Transisi Status di Basis Data**:
   Perbarui status record deployment yang tertahan agar state machine kembali konsisten:
   ```bash
   php artisan platform:deployment-force-status --code="DEP-ACTIVE" --status="rolled_back" --reason="Manual emergency intervention"
   ```
3. **Langkah 3 - Verifikasi Pemulihan Layanan**:
   Periksa metrik latensi dan tingkat error di panel observabilitas hingga angka kembali normal di bawah 0.1%.

---

## 3. Playbook 2: Migrasi Skema Database Mengalami Kunci Macet (Stuck DDL)

### Gejala:
Pernyataan `ALTER TABLE` atau migrasi skema tertahan dalam status `Waiting for table metadata lock`, menyebabkan antrean kueri menumpuk cepat dan memicu kehabisan batas koneksi database (`Too many connections`).

### Prosedur Penanganan Darurat:
1. **Langkah 1 - Identifikasi Kueri Pemblokir**:
   Jalankan pemeriksaan pada tabel performance schema MySQL:
   ```sql
   SELECT sys.format_statement(digest_text), timer_wait/1000000000000 AS duration_sec
   FROM performance_schema.events_statements_current
   WHERE waiting_for_table_metadata_lock = 1;
   ```
2. **Langkah 2 - Hentikan Proses DDL Bermasalah**:
   Hentikan koneksi sesi pembuat migrasi DDL:
   ```sql
   KILL <connection_id_ddl>;
   ```
3. **Langkah 3 - Batalkan Migrasi dan Jalankan Skrip Reversal**:
   Gunakan generator skrip pembalikan otomatis:
   ```bash
   php artisan platform:migration-rollback-script --table="users"
   ```

---

## 4. Playbook 3: Korupsi Data atau Kegagalan Terdistribusi pada Job Backfill

### Gejala:
Job backfill data massal mengalami pengecualian berulang (`DeadLetterChunkException`) dan meninggalkan ribuan baris dalam keadaan setengah bermigrasi.

### Prosedur Penanganan Darurat:
1. **Langkah 1 - Hentikan Pekerja Backfill Seketika**:
   Kirim sinyal jeda ke pengatur konkurensi:
   ```bash
   php artisan platform:backfill-pause --job-name="dual_write_uuid_migration"
   ```
2. **Langkah 2 - Inspeksi Status Potongan (Chunk Forensics)**:
   Periksa detail potongan yang mengalami kegagalan:
   ```bash
   php artisan platform:backfill-inspect --job-name="dual_write_uuid_migration" --filter="failed"
   ```
3. **Langkah 3 - Jalankan Pemulihan Titik Cek (Checkpoint Recovery)**:
   Gunakan kelas pemulihan `BackfillResumptionCoordinator` untuk memproses ulang potongan yang tertahan setelah akar penyebab bug diperbaiki:
   ```bash
   php artisan platform:backfill-resume --job-name="dual_write_uuid_migration" --force-retry
   ```

---

## 5. Prosedur Retrospektif dan Pelaporan Pasca-Bencana (Blameless Post-Mortem)

Setiap insiden SEV-1 dan SEV-2 wajib diakhiri dengan penyusunan dokumen retrospektif dalam kurun waktu 48 jam:
1. **Kronologi Kejadian (Timeline)**: Urutan waktu peristiwa presisi detik dari deteksi awal hingga pemulihan penuh.
2. **Akar Masalah (Root Cause)**: Analisis 5-Whys yang berfokus pada kelemahan sistemik, bukan kesalahan individu pengembang.
3. **Pelajaran yang Dipetik (Lessons Learned)**: Identifikasi apa yang berjalan baik, apa yang berjalan buruk, dan di mana sistem deteksi terlambat.
4. **Tindakan Pencegahan (Action Items)**: Daftar tiket perbaikan dengan penanggung jawab dan tanggal penyelesaian terikat.
