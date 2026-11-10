# Katalog Galat Domain: Design System & Aksesibilitas (Fase 240)

**Modul:** `Shared` & `Core`  
**Dokumen:** `docs/galat/design-system.md`  

---

## 1. Daftar Exception Domain & Panduan Penanganan

### 1.1. `InvalidContrastRatioException`
- **Pesan Galat Standar:**  
  `"Rasio kontras warna {ratio_bps} bps antara '{color_a}' dan '{color_b}' di bawah ambang batas WCAG AA ({threshold_bps} bps)."`
- **Akar Penyebab:**  
  Kombinasi warna teks/elemen dengan warna latar belakang menghasilkan kontras luminansi relatif yang tidak memadai (misal: teks abu-abu terang `#9CA3AF` di atas latar putih `#FFFFFF`).
- **Tindakan Pemulihan:**  
  Sesuaikan nilai warna pada tema terkait. Gelapkan warna teks atau terangkan warna latar belakang hingga rasio kontras $\ge 45.000$ bps ($4.5:1$).

---

### 1.2. `TokenCycleException`
- **Pesan Galat Standar:**  
  `"Terdeteksi siklus dependensi token sirkular pada rantai pewarisan: {cycle_chain}."`
- **Akar Penyebab:**  
  Dua atau lebih variabel token saling mereferensikan satu sama lain melalui alias sintaks `{token_name}` (misal: token `a` merujuk `{b}`, dan token `b` merujuk `{a}`).
- **Tindakan Pemulihan:**  
  Putuskan rantai ketergantungan melingkar pada tabel `core_ds_tokens`. Pastikan salah satu token mendefinisikan nilai heksadesimal absolut.

---

### 1.3. `UndefinedTokenException`
- **Pesan Galat Standar:**  
  `"Token alias '{token_name}' tidak ditemukan pada tema peminta maupun rantai warisannya."`
- **Akar Penyebab:**  
  Penyebutan nama token yang salah ketik atau belum didaftarkan di tabel `core_ds_tokens`.
- **Tindakan Pemulihan:**  
  Daftarkan token yang hilang atau perbaiki penamaan alias.

---

### 1.4. `ComponentTransitionGuardException`
- **Pesan Galat Standar:**  
  `"Transisi status komponen '{name}' dari '{from_status}' ke '{to_status}' ditolak oleh guard: {reason}."`
- **Akar Penyebab:**  
  Percobaan memindahkan status rilis komponen tanpa memenuhi prasyarat wajib (misal: mencoba rilis ke `approved` saat masih ada temuan a11y terbuka, atau mencoba menghapus komponen `deprecated` yang masih dipakai oleh 5 halaman aktif).
- **Tindakan Pemulihan:**  
  Penuhi seluruh prasyarat yang dipersyaratkan oleh guard: selesaikan remediasi aksesibilitas, ganti pemanggilan komponen di halaman aktif hingga bernilai 0, atau lampirkan komponen pengganti resmi.

---

### 1.5. `InvalidComponentReleaseException`
- **Pesan Galat Standar:**  
  `"Komponen '{name}' tidak dapat didaftarkan karena telah ada komponen aktif dengan nama yang sama."`
- **Akar Penyebab:**  
  Percobaan mendaftarkan komponen baru dengan nama duplikat yang melanggar batasan keunikan sistem.
- **Tindakan Pemulihan:**  
  Gunakan nama komponen kanonikal baru atau gunakan komponen yang sudah ada.
