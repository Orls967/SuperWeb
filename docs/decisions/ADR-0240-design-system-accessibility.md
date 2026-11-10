# ADR-0240: Arsitektur Design System Terpadu, Aksesibilitas WCAG 2.1 & Tata Kelola Komponen

**Nomor ADR:** `ADR-0240`  
**Status:** Diterima (*Accepted*)  
**Tanggal:** 2026-11-10  
**Penulis:** Pelaksana W8-P2  
**Keputusan Terkait:** `W8-D21`, `W8-D22`, `W8-D23`, `W8-D24`, `W8-D25`  

---

## Konteks Bisnis & Masalah

SuperWeb berkembang mencakup 30 lini bisnis vertikal dengan ratusan antarmuka pengguna. Kondisi sebelum Gelombang 8 mengalami fragmentasi visual:
1. Terjadi variasi pola komponen Blade antara satu modul dengan modul lain, menyebabkan inkonsistensi pengalaman pengguna.
2. Kontras warna dan label aksesibilitas sering terlewat karena tidak adanya alat linter otomatis yang terintegrasi dalam alur verifikasi mutu.
3. Kebutuhan dukungan multi-tema (Terang / Gelap / Kontras Tinggi) untuk aksesibilitas inklusif belum terakomodasi secara sistematis melalui variabel token terpusat.
4. Komponen antarmuka sering dibuat secara *ad-hoc* tanpa tata kelola siklus hidup rilis (*release lifecycle*), menimbulkan hutang teknis komponen mati (*zombie components*) atau duplikasi komponen antar modul.

---

## Keputusan Arsitektur

### 1. W8-D21: Manajemen Token Tema Tri-State & Pewarisan
- Variabel desain disimpan dalam tabel parameter berversi `core_ds_tokens` dengan klasifikasi tema: `light`, `dark`, dan `hc` (kontras tinggi).
- Mekanisme resolusi menerapkan pohon pewarisan: `hc` $\leftarrow$ `dark` $\leftarrow$ `light`.
- Alias token dalam format `{token_name}` dievaluasi pada **tema peminta** untuk menjamin konsistensi palet warna pada mode kontras tinggi.
- Perintah CLI `ds:tokens-build` mengompilasi basis data token ke stylesheet statis terkompresi `resources/css/ds-tokens.css`.
- Pengguna dapat memilih tema secara mandiri melalui atribut `data-theme` pada elemen `<html>`, yang persisten tersimpan di tabel `core_ds_user_prefs`.

### 2. W8-D22: Kalkulasi Kontras WCAG 2.1 Presisi Integer
- Perhitungan rasio kontras warna luminansi sRGB WCAG 2.1 diimplementasikan murni berbasis integer presisi tinggi via algoritma A1 `WcagContrastCalculator`.
- Nilai luminansi relatif dikonversi ke parts-per-million (ppm) dan rasio kontras ke basis points (bps).
- Ambang kepatuhan:
  - Teks normal Level AA: $\ge 45.000$ bps ($4.5:1$).
  - Teks besar Level AA: $\ge 30.000$ bps ($3.0:1$).
  - Komponen grafis / UI Level AA: $\ge 30.000$ bps ($3.0:1$).
  - Teks normal Level AAA: $\ge 70.000$ bps ($7.0:1$).

### 3. W8-D23: Komponen Kanonikal `x-ds.*` & Pemisahan Logika UI
- Namespace anonim Blade `ds` didaftarkan di `SharedServiceProvider` yang memetakan ke direktori `modules/Shared/resources/views/components/ds/`.
- Logika berat presentasi (sanitasi sortir tabel, filter query string, pembentukan koordinat grafis SVG native, dan format lokalisasi Indonesia) diletakkan di kelas-kelas PHP ViewModel `Modules\Shared\Application\Ui\*`.
- Skrip interaktif sisi klien dibatasi maksimal 20 baris per file dan didelegasikan ke modul Alpine.js standar.

### 4. W8-D24: Siklus Hidup Rilis Komponen 7-Status (`ComponentReleaseStateMachine`)
- Setiap komponen kanonikal dikelola siklus hidupnya melalui 7 status teratur:
  `proposed` $\rightarrow$ `in_design` $\rightarrow$ `a11y_review` $\rightarrow$ `approved` $\rightarrow$ `released` $\rightarrow$ `deprecated` $\rightarrow$ `removed`.
- Guard ketat mencegah rilis komponen tanpa sertifikat lulus uji kontras A1, kepatuhan `ui:lint` 0 error, dan approval resmi dari role `design_system_owner` melalui `ApprovalEngine`.
- Penghapusan komponen (`removed`) dicegah bila `ds:usage-scan` masih mendeteksi adanya pemanggilan komponen di view aplikasi.

### 5. W8-D25: Linter Aksesibilitas Terintegrasi (`ui:lint`)
- Command CLI `ui:lint` dikembangkan untuk memverifikasi secara statis berkas Blade dan DOM hasil render terhadap pelanggaran:
  - Warna hex *hard-coded* di dalam template.
  - Elemen form input tanpa label aksesibel.
  - Elemen gambar `<img>` tanpa atribut `alt`.
  - Atribut `tabindex` bernilai positif ($> 0$).
  - Tag tombol `<button>` kosong tanpa penanda aksesibel.
- Menerapkan mekanisme ratchet baseline (`tests/Architecture/baselines/ui-lint.json`) di mana jumlah pelanggaran hanya boleh berkurang seiring migrasi berkelanjutan.

---

## Konsekuensi

- **Positif:** Standar visual dan interaksi 30 lini menjadi seragam; jaminan aksesibilitas WCAG 2.1 Level AA/AAA ditegakkan secara deterministik dan terukur; layout mobile-first 375 px dijamin tanpa horizontal scrolling.
- **Batasan:** Modul dilarang menggunakan warna hex atau styling mentah di luar token desain kanonikal; setiap kebutuhan komponen khusus harus diajukan melalui tata kelola `ComponentRelease` resmi.

