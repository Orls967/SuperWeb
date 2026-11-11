# Runbook Operasional — Batas Modul, Kepemilikan Data, API & Multi-Tenancy

Dokumen panduan operasional teknis untuk tim Platform Architect, Tim Security, dan Software Engineer dalam mengelola kepatuhan batas modul, API, dan isolasi tenant pada repositori SuperWeb.

---

## 1. Operasi Batas Modul (Fase R4)

### 1.1 Pemindaian Dependensi Modul (`arch:boundaries`)
Perintah ini digunakan untuk memetakan dependensi lintas modul dan memeriksa apakah terdapat siklus dependensi atau pelanggaran kepemilikan skema.

```bash
# Menampilkan ringkasan tabel di terminal
php artisan arch:boundaries

# Ekspor grafik dalam format SVG (deterministik tanpa stempel waktu)
php artisan arch:boundaries --format=svg --out=docs/diagrams/peta-modul.svg

# Ekspor katalog markdown untuk dokumentasi resmi
php artisan arch:boundaries --format=md --out=docs/catalog/dependensi-modul.md

# Format JSON terstruktur untuk integrasi pipeline CI/CD
php artisan arch:boundaries --format=json
```

**Kriteria Sukses**:
- Jumlah siklus Strongly Connected Components (SCC) dengan ukuran $> 1$ bernilai tepat 0.
- Tidak ada `PREFIX_CONFLICT` pada daftar prefiks modul.

---

### 1.2 Triage Pelanggaran Arsitektur (`boundary:triage`)
Menyinkronkan pelanggaran hasil pemindaian statis AST ke dalam tabel persisten `core_boundary_violations`.

```bash
php artisan boundary:triage
```

**Perilaku Perintah**:
- Pelanggaran baru dimasukkan dengan status awal `detected`.
- Pelanggaran lama yang sedang dalam status `migrating` dan kini tidak ditemukan lagi di kode sumber secara otomatis dipindahkan ke status `verified`.
- Pelanggaran yang sudah berstatus `closed` diabaikan secara idempoten.

---

### 1.3 Audit Integritas Dua Sumber (`boundary:audit`)
Menjalankan audit rekonsiliasi antara kode sumber aktual, baseline ratchet, dan tabel database.

```bash
php artisan boundary:audit
```

**Pemeriksaan yang Dijalankan**:
1. **Source vs Database Sync**: Setiap pelanggaran statis memiliki baris padanan di `core_boundary_violations`.
2. **Ratchet Baseline vs Current State**: Memastikan tidak ada pelanggaran baru yang tidak terdaftar pada `arch-scan.json`.
3. **Validitas Waiver**: Memastikan seluruh entri berstatus `waived` memiliki:
   - Rujukan anchor yang valid pada `docs/DECISIONS.md`.
   - Tanggal kedaluwarsa `expires_at` yang belum terlewati.
4. **Integritas Status Terminal**: Memastikan entri berstatus `closed` tidak lagi muncul dalam file `arch-scan.json`.

Jika audit mendeteksi korupsi atau inkonsistensi, perintah keluar dengan kode status `1` dan mencetak rincian diskrepansi.

---

### 1.4 Pemeliharaan Waiver Harian (`boundary:expire-waivers`)
Perintah terjadwal harian (cron) untuk memproses kedaluwarsa waiver.

```bash
php artisan boundary:expire-waivers
```

**Tindakan Otomatis**:
1. Menemukan semua pelanggaran dengan status `waived` yang memiliki `expires_at < now()`.
2. Memindahkan status kembali ke `triaged`.
3. Menerbitkan event Outbox `core.boundary.waiver_expired.v1`.
4. Menerbitkan tiket peringatan di Kotak Masuk "Tugas Saya" via `TaskInboxService::createTask()` kepada peran `platform_architect`.

---

## 2. Prosedur Penanganan Insiden Arsitektur

### 2.1 Insiden 1: Siklus Dependensi Ditemukan (SCC Size > 1)
**Gejala**: `arch:boundaries` atau `boundary:audit` gagal dengan pesan `CYCLE_DETECTED: Modul [A, B] membentuk siklus dependensi`.
**Langkah Mitigasi**:
1. Jalankan `php artisan arch:boundaries --format=json` dan telusuri bagian `cycles`.
2. Identifikasi berkas spesifik pada modul A yang mengimpor kelas dari modul B, dan sebaliknya.
3. Rancang antarmuka kontrak baru pada `Modules/Core/Contracts` untuk memutus salah satu arah dependensi.
4. Ubah modul pemanggil untuk bergantung pada interface kontrak tersebut, bukan kelas implementasi konkret.
5. Jalankan kembali `php artisan arch:boundaries` untuk memastikan siklus telah terurai menjadi DAG.

---

### 2.2 Insiden 2: Konflik Prefiks Tabel (PREFIX_CONFLICT)
**Gejala**: Build gagal dengan galat `PREFIX_CONFLICT: Prefix 'xyz_' didaftarkan oleh lebih dari satu modul`.
**Langkah Mitigasi**:
1. Buka file `config/modules.php`.
2. Periksa entri pada `table_prefixes`.
3. Tentukan modul mana yang merupakan pemilik sejati domain data tersebut.
4. Jika salah satu modul mewakili subdomain yang lebih spesifik, gunakan prefiks bertumpuk (misal `xyz_sub_`).
5. Jalankan `vendor/bin/pest tests/Architecture/ModulePrefixRegistryTest.php` untuk memvalidasi resolusi.

---

### 2.3 Insiden 3: Waiver Kedaluwarsa Memblokir CI
**Gejala**: CI gagal karena terdapat waiver yang melewati tenggat waktu 90 hari.
**Langkah Mitigasi**:
1. Identifikasi pelanggaran yang kedaluwarsa melalui `php artisan boundary:audit`.
2. Jika migrasi teknis telah siap, selesaikan refaktor dan hapus entri dari baseline melalui `php artisan arch:scan --update-baseline`.
3. Jika perpanjangan waktu sangat mendesak dan disetujui arsitek:
   - Buat pengajuan persetujuan baru via `ApprovalEngine`.
   - Catat justifikasi baru dan anchor keputusan di `docs/DECISIONS.md`.
   - Jalankan `WaiveViolationAction` dengan masa berlaku maksimal 90 hari berikutnya.

