# Panduan Operasional Operator Simulasi (Fase 67)

Panduan ini ditujukan bagi personil dengan peran **`sim_operator`** dan **`admin`** yang bertanggung jawab menjalankan, memantau, membandingkan, dan mengaudit sesi simulasi waktu virtual dan Digital Twin State Bus pada platform Superweb.

---

## 1. Ikhtisar Peran & Tanggung Jawab

Sebagai `sim_operator`, wewenang Anda mencakup:
1. **Memulai Sesi Simulasi Baru:** Menentukan nama sesi, durasi target hari virtual (1–1.825 hari), seed acak MINSTD, dan mode eksekusi (`live` atau `sandbox`).
2. **Pengendalian Siklus Hidup:** Melakukan jeda sementara (*pause*), melanjutkan sesi (*resume*), dan memantau progres persentase harian.
3. **Komparasi & Deteksi Divergensi:** Membandingkan dua sesi simulasi (`sim:diff`) untuk membuktikan determinisme atau melacak hari pertama terjadinya deviasi output.
4. **Audit Integritas Digital Twin:** Menginspeksi rantai hash state bayangan entitas domain (armada kendaraan, episode rawat inap, kamar hotel, peti kemas logistik).
5. **Ekspor Data Forensik:** Mengunduh berkas laporan CSV untuk analisis pembukuan, checkpoint, dan benchmark skala.

---

## 2. Antarmuka Konsol Web UI

Konsol operator dapat diakses melalui peramban pada rute `/admin/simulation` setelah berhasil masuk sebagai pengguna dengan role `sim_operator` atau `admin`.

### 2.1. Halaman Beranda Konsol (`/admin/simulation`)
Menyajikan panel statistik KPI utama:
- **Total Sesi:** Jumlah seluruh sesi simulasi yang tercatat dalam sistem.
- **Aktif Berjalan:** Sesi dengan status `running`.
- **Selesai (Completed):** Sesi yang telah tuntas mencapai target hari tanpa interupsi.
- **Mode Sandbox:** Sesi simulasi terisolasi transaksional yang tidak mengubah data produksi.

Tabel daftar sesi dilengkapi fitur pencarian berdasarkan nama, filter status (`draft`, `provisioned`, `running`, `paused`, `checkpointed`, `completed`, `failed`, `archived`), dan filter mode (`live`, `sandbox`).

### 2.2. Membuat Sesi Baru via Web UI
1. Klik tombol **Buat Sesi Baru**.
2. Masukkan **Nama Sesi** (misal: `q4-stress-test-2026`).
3. Tentukan **Target Hari** (misal: `365` untuk simulasi satu tahun buku).
4. Masukkan **Seed MINSTD** (bilangan bulat antara 1 hingga 2.147.483.646).
5. Pilih **Mode**:
   - `live`: Perubahan state dicatat permanen ke basis data operasional.
   - `sandbox`: Seluruh transaksi di-rollback secara otomatis di akhir simulasi; hanya ringkasan hash dan metrik yang disimpan.
6. Masukkan header wajib `Idempotency-Key` (dihasilkan otomatis oleh antarmuka form).
7. Klik **Mulai Simulasi**.

### 2.3. Halaman Detail Sesi (`/admin/simulation/{id}`)
Menampilkan:
- Informasi header sesi: status state machine, progress bar, hash rantai terkini.
- Rincian checkpoint periodik (tiap 7 hari).
- Riwayat transisi status append-only beserta aktor yang mengeksekusi.
- Tombol aksi: **Jeda (Pause)**, **Lanjutkan (Resume)**, dan **Ekspor Checkpoint CSV**.

### 2.4. Halaman Komparasi Sesi (Diff) (`/admin/simulation/diff`)
Digunakan untuk membandingkan dua sesi:
1. Pilih **Sesi A (Baseline)** dan **Sesi B (Komparasi)** dari menu dropdown.
2. Klik **Bandingkan Sekarang**.
3. Sistem akan menganalisis:
   - Kesesuaian seed awal.
   - Status kecocokan rantai hash fingerprint.
   - Hari pertama divergensi (*first divergence day*).
   - Tabel perbedaan output per handler domain jika divergensi terdeteksi.

### 2.5. Halaman Digital Twin Bus (`/admin/simulation/twins`)
Menampilkan daftar entitas bayangan domain:
- Filter berdasarkan tipe entitas (`vehicle`, `hospital_episode`, `hotel_room`, `container`).
- Filter ID entitas dan mode sandbox.
- Klik **Audit Rantai** pada salah satu baris untuk memeriksa validitas rantai kriptografis `prev_hash` $\to$ `event_hash` dan snapshot state.

---

## 3. Pengoperasian Melalui Konsol CLI (Artisan)

Untuk otomasi dan proses batch di latar belakang, operator dapat menggunakan perintah artisan resmi:

### 3.1. Menjalankan Simulasi (`sim:run`)
```bash
# Menjalankan simulasi 365 hari live dengan seed 42
php artisan sim:run 365 --seed=42 --name="benchmark-2026" --mode=live

# Menjalankan simulasi sandbox 30 hari untuk uji coba
php artisan sim:run 30 --seed=1001 --name="sandbox-test" --mode=sandbox
```

**Opsi Tersedia:**
- `--seed=<int>`: Seed acak MINSTD (default: `42`).
- `--name=<string>`: Nama unik sesi simulasi.
- `--mode=<live|sandbox>`: Mode isolasi (default: `live`).

### 3.2. Membandingkan Dua Sesi (`sim:diff`)
```bash
# Membandingkan sesi run-a dan run-b
php artisan sim:diff --a="benchmark-run-a" --b="benchmark-run-b"
```

**Keluaran Standar:**
- Jika identik: `[OK] Runs are identical. No divergence detected.` (Exit Code 0).
- Jika berbeda: `[DIVERGENCE] Divergence detected at Day X. Reason: ...` (Exit Code 1).

### 3.3. Audit Kriptografis Kernel (`sim:audit`)
```bash
# Memverifikasi integritas algoritma A1, A2, A3 dan data checkpoint
php artisan sim:audit
```

**Evaluasi:**
- Seluruh verifier lulus: Menampilkan tabel ringkasan audit hijau dan keluar dengan Exit Code 0.
- Terdeteksi korupsi atau manipulasi: Menampilkan pesan peringatan merah dan keluar dengan Exit Code non-zero (1).

### 3.4. Pembersihan Checkpoint Usang (`sim:checkpoint-prune`)
```bash
# Mempertahankan 5 checkpoint terakhir dan kelipatan 30 hari
php artisan sim:checkpoint-prune --run="benchmark-2026" --keep=5 --milestone=30
```

### 3.5. Provisi Skala Infrastruktur (`scale:provision`)
```bash
# Provisi kapasitas outbox dan twin untuk beban medium
php artisan scale:provision medium
```

---

## 4. Kebijakan Keamanan & Anti-IDOR

1. **Prinsip Kepemilikan Sesi:** Setiap sesi simulasi mencatat `created_by` (ID pengguna pembuat).
2. **Pencegahan Manipulasi Silang:** Operator yang masuk dengan ID berbeda akan menerima respon **`403 Forbidden`** jika mencoba mengirimkan request POST `/admin/simulation/{id}/pause` atau `resume` pada sesi yang dimiliki operator lain.
3. **Pengecualian Peran Admin:** Pengguna dengan role `admin` memiliki wewenang pengawasan menyeluruh dan dapat mengelola seluruh sesi tanpa batas kepemilikan.
4. **Idempotensi Wajib:** Setiap pembuatan sesi via API wajib menyertakan header HTTP `Idempotency-Key`. Permintaan berulang dengan kunci yang sama akan mengembalikan objek sesi awal tanpa membuat duplikasi di basis data.

---

## 5. Prosedur Penanganan Insiden Divergensi

Jika dua sesi yang seharusnya identik menghasilkan divergensi:
1. Buka menu **Diff** (`/admin/simulation/diff`) atau jalankan `php artisan sim:diff --a=<sesi-1> --b=<sesi-2>`.
2. Identifikasi **Hari Pertama Divergensi**. Misal: Divergensi terjadi pada Hari ke-14.
3. Periksa tabel **Rincian Output Handler** pada hari tersebut:
   - Bandingkan nilai output `hospital_discharge`, `hotel_night_audit`, dan `logistics_demurrage`.
   - Temukan handler mana yang menghasilkan hash berbeda pertama kali.
4. Periksa log sistem pada waktu virtual hari ke-14 untuk mendeteksi apakah ada pemanggilan seed yang tidak terisolasi atau modifikasi data manual di luar kernel.
5. Laporkan temuan ke tim rekayasa platform untuk investigasi lebih lanjut.

