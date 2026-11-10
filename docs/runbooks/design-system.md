# Runbook Operasional: Design System & Aksesibilitas (Fase 240)

**Modul:** `Shared` & `Core`  
**Sasaran:** Tim Operasional, DevOps, dan Design System Engineer  
**Dokumen:** `docs/runbooks/design-system.md`  

---

## 1. Daftar Perintah Konsol (CLI Commands)

### 1.1. `php artisan ui:lint`
Memindai berkas template Blade dan dokumen HTML render terhadap aturan aksesibilitas WCAG 2.1, warna hex mentah, dan struktur responsif 375 px.

**Opsi Pemanggilan:**
```bash
# Pindai direktori atau file tertentu
php artisan ui:lint --paths=modules/Shared/resources/views/components/ds

# Pindai hasil render rute aplikasi tertentu
php artisan ui:lint --render=/design-system

# Periksa kesesuaian viewport responsif 375 px
php artisan ui:lint --responsive

# Jalankan evaluasi penuh terhadap baseline ratchet
php artisan ui:lint --all --ratchet
```
- **Keluaran Sukses (Exit Code 0):** `0` pelanggaran ditemukan, atau pelanggaran $\le$ baseline ratchet saat ini.
- **Keluaran Galat (Exit Code 1):** Ditemukan pelanggaran baru di atas baseline.

---

### 1.2. `php artisan ds:tokens-build`
Mengambil definisi token desain berversi dari tabel `core_ds_tokens`, menyelesaikan pewarisan tema tri-state (`hc` $\leftarrow$ `dark` $\leftarrow$ `light`) via Algoritma A2 `ThemeTokenResolver`, dan mengompilasi stylesheet statis `resources/css/ds-tokens.css`.

**Pemanggilan:**
```bash
php artisan ds:tokens-build
```
- **Keluaran Sukses (Exit Code 0):** Berkas `resources/css/ds-tokens.css` berhasil ditulis ulang (ukuran $\le 200$ baris).
- **Keluaran Galat (Exit Code 1):** Terdeteksi siklus token (`TOKEN_CYCLE`) atau token tidak terdefinisi (`TOKEN_UNDEFINED`).

---

### 1.3. `php artisan ds:usage-scan`
Tugas terjadwal (dijalankan harian secara idempoten) untuk memindai seluruh template Blade di direktori `resources/views` dan `modules/*/resources/views`. Menghitung frekuensi pemanggilan masing-masing komponen kanonikal `x-ds.*` dan menyimpan hasilnya ke tabel `core_ds_component_usage`.

**Pemanggilan:**
```bash
php artisan ds:usage-scan
```
- **Keluaran Sukses (Exit Code 0):** Rekapitulasi pemakaian berhasil diperbarui tanpa duplikasi data.

---

### 1.4. `php artisan ds:audit`
Audit dua sumber integritas data desain sistem:
1. Membandingkan status komponen pada `core_ds_components` terhadap urutan log transisi pada `core_ds_component_history`.
2. Mendeteksi komponen non-released yang dipanggil oleh view aktif berdasarkan data `core_ds_component_usage`.
3. Menghitung ulang rasio kontras pasangan token aktif via A1 `WcagContrastCalculator` dan memvalidasinya terhadap ambang batas WCAG AA.
4. Mendeteksi temuan aksesibilitas yang masih belum terselesaikan (`is_remediated = false`) pada komponen berstatus `released`.

**Pemanggilan:**
```bash
php artisan ds:audit
```
- **Keluaran Sukses (Exit Code 0):** Seluruh invarian konsisten dan valid.
- **Keluaran Galat (Exit Code 1):** Terdeteksi anomali integritas dua sumber. Menampilkan rincian entitas yang korup.

---

## 2. Prosedur Investigasi Insiden Aksesibilitas (Troubleshooting Guide)

### 2.1. Insiden: Gerbang `ui:lint` Gagal Menjelang Deploy
1. **Periksa Berkas yang Dilaporkan:**
   Jalankan `php artisan ui:lint --paths={path/file}` pada file yang memicu galat.
2. **Kategori Pelanggaran `IMG_ALT`:**
   Tambahkan atribut `alt="Deskripsi gambar"` jika gambar bermakna informatif, atau `alt=""` jika gambar murni hiasan visual.
3. **Kategori Pelanggaran `INPUT_LABEL`:**
   Pastikan input memiliki elemen `<label for="{input_id}">` yang merujuk pada `id` input tersebut, atau sematkan atribut `aria-label="Nama Input"`.
4. **Kategori Pelanggaran `BUTTON_NAME`:**
   Tambahkan teks di dalam tombol, atau sematkan atribut `aria-label="Aksi Tombol"`.
5. **Kategori Pelanggaran `TABINDEX_POSITIVE`:**
   Hapus atribut `tabindex="1"` (atau angka positif lainnya). Gunakan penataan elemen DOM alami atau `tabindex="0"`.

---

### 2.2. Insiden: Peringatan Kontras Warna Rendah
1. **Identifikasi Token Bermasalah:**
   Jalankan pemeriksaan kontras:
   ```bash
   php artisan ds:tokens-build
   ```
2. **Evaluasi Rasio Kontras Aktual:**
   Gunakan kalkulator A1 untuk mengevaluasi pasangan warna:
   ```php
   $calc = new WcagContrastCalculator();
   $res = $calc->calculate('#777777', '#FFFFFF');
   // Output: 44.780 bps (Gagal AA, batas minimum 45.000 bps)
   ```
3. **Koreksi Nilai Token:**
   Perbarui nilai pada tabel `core_ds_tokens` ke warna yang lebih kontras (misal `#767676` dengan rasio $45.422$ bps), lalu jalankan ulang `php artisan ds:tokens-build`.

---

### 2.3. Insiden: Penolakan Transisi Status Komponen
1. **Jika Transisi ke `approved` Ditolak:**
   Periksa temuan yang belum diselesaikan pada tabel `core_ds_a11y_findings`:
   ```bash
   php artisan tinker --execute="echo Modules\Core\Domain\Models\DsA11yFinding::where('component_id', \$id)->where('is_remediated', false)->count();"
   ```
   Tandai temuan selesai setelah perbaikan kode dilakukan.
2. **Jika Transisi ke `removed` Ditolak:**
   Jalankan pemindaian pemakaian untuk melihat berkas mana yang masih memanggil komponen:
   ```bash
   php artisan ds:usage-scan
   ```
   Migrasikan template yang tercatat pada `core_ds_component_usage` ke komponen kanonikal baru.

---

## 3. SLA & Jadwal Pemeliharaan Berkala

- **Pemindaian Pemakaian Harian (`ds:usage-scan`):**
  Dijadwalkan setiap hari pukul 02:00 WIB via scheduler Laravel (`routes/console.php`).
- **Audit Integritas Dua Sumber (`ds:audit`):**
  Dijalankan pada setiap alur CI dan sebelum rilis versi aplikasi.
- **Waktu Maksimum Penanganan Temuan Aksesibilitas Kritis:**
  Temuan a11y berstatus `critical` wajib diselesaikan dalam $1 \times 24$ jam kerja sebelum rilis produksi.
