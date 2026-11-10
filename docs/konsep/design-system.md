# Konsep Desain Sistem & Aksesibilitas Terpadu 30 Lini (Fase 240)

**Fase:** Fase 240 (Gelombang 8, Prompt W8-P2.1)  
**Dokumen Konsep:** `docs/konsep/design-system.md`  
**Status:** Disetujui (Baseline Resmi Gelombang 8)  
**Tanggal Penyusunan:** 2026-11-10  

---

## 1. Masalah Bisnis & Latar Belakang

SuperWeb melayani 30 lini ekosistem vertikal mulai dari Rumah Sakit, Logistik, Aviasi, Manufaktur, Ritel, hingga Pelabuhan dan Energi. Sebelum Fase 240:
1. **Inkonsistensi Pengalaman Pengguna Lintas Modul:** Antarmuka pengguna dibangun secara parsial dengan variasi styling Tailwind yang tidak seragam, menghasilkan pengalaman pengguna yang terfragmentasi ketika operator berpindah modul.
2. **Ketiadaan Tata Kelola Aksesibilitas (WCAG 2.1):** Pengguna dengan keterbatasan penglihatan atau kebutuhan kontras tinggi terhambat oleh palet warna yang tidak memenuhi rasio kontras 4.5:1. Navigasi keyboard pada komponen kompleks (modal, dropdown) sering mengalami jebakan fokus (*focus trap failure*) atau hilangnya outline fokus visual.
3. **Ketidaksesuaian Tampilan Layar Lapangan (375 px):** Banyak tabel lebar memicu horizontal scrolling pada perangkat mobile 375 px yang dipakai staf lapangan (supir logistik, perawat bangsal, operator pelabuhan).
4. **Hutang Teknis Komponen UI:** Tanpa mekanisme siklus hidup resmi, developer membuat komponen duplikat secara ad-hoc atau tetap menggunakan komponen yang sudah usang (*deprecated*), meningkatkan beban pemeliharaan kode.

---

## 2. Aktor & Peran (Roles)

1. **`design_system_owner`**: Penanggung jawab utama pustaka desain perusahaan. Memiliki wewenang mengonfigurasi token tema, menyetujui rilis komponen dari `a11y_review` ke `approved` via `ApprovalEngine`, memantau metrik pemakaian komponen, dan mengelola backlog perbaikan riset UX.
2. **`module_developer`**: Pengembang lini bisnis yang memanfaatkan pustaka komponen `x-ds.*`, mengajukan usulan komponen baru (`proposed`), dan menyelaraskan template view modul dengan standar desain.
3. **`operational_user` (Semua Role Modul)**: Pengguna akhir di 30 lini (dokter, manajer logistik, operator gudang, kasir) yang berinteraksi dengan antarmuka, memiliki hak mengubah preferensi tema (`light`, `dark`, `hc`) sesuai kenyamanan visual dan lingkungan kerja lapangan.

---

## 3. Konsep Inti & Istilah

- **Token Desain Kanonikal:** Struktur data atomik berversi (`core_ds_tokens`) yang memisahkan definisi warna dan gaya dari implementasi markup.
- **Pewarisan Tema Tri-State:** Pola resolusi di mana tema kontras tinggi (`hc`) mewarisi nilai dari tema gelap (`dark`), dan tema gelap mewarisi tema terang (`light`).
- **Alias Token di Tema Peminta:** Evaluasi alias `{token_name}` di dalam konteks tema yang memanggil, memastikan tema turunan dapat mendefinisikan ulang variabel dasar tanpa merusak warna kontras.
- **Deteksi Siklus Token (`TOKEN_CYCLE`):** Proteksi rekursi tanpa akhir saat dua atau lebih token saling merujuk.
- **State Machine Rilis Komponen (7 Status):** Siklus hidup terkelola: `proposed` $\rightarrow$ `in_design` $\rightarrow$ `a11y_review` $\rightarrow$ `approved` $\rightarrow$ `released` $\rightarrow$ `deprecated` $\rightarrow$ `removed`.
- **Linter Aksesibilitas UI (`ui:lint`):** Perangkat audit statis dan runtime untuk menegakkan larangan warna hex mentah dan memverifikasi kepatuhan atribut HTML semantik.
- **Responsive Table-to-Card:** Kemampuan komponen tabel enterprise mentransformasikan baris tabel menjadi kartu terstruktur pada viewport sempit ($\le 375$ px).

---

## 4. Invarian Bisnis & Arsitektur

Berikut adalah invarian ketat yang wajib dipertahankan dan diverifikasi oleh sistem (ditautkan ke kode produksi):
1. **INV-DS-01 (Kepatuhan Rasio Kontras Minimum):** Setiap teks normal wajib memiliki rasio kontras luminansi relatif terhadap latar belakangnya minimal 45.000 bps ($4.5:1$, Level AA) dan teks besar minimal 30.000 bps ($3.0:1$) pada semua tema aktif. Diverifikasi oleh `WcagContrastCalculator`.
2. **INV-DS-02 (Pencegahan Siklus Alias Token):** Pohon resolusi token tema dilarang memuat dependensi sirkular. Siklus alias wajib dideteksi dan ditolak dengan exception `TokenCycleException`.
3. **INV-DS-03 (Larangan Rilis Komponen Tanpa Approval Aksesibilitas):** Komponen tidak dapat berpindah dari `a11y_review` ke `approved` sebelum seluruh temuan pada `core_ds_a11y_findings` dinyatakan tuntas (`is_remediated = true`) dan disetujui resmi oleh `design_system_owner`.
4. **INV-DS-04 (Larangan Pemakaian Komponen Non-Released):** View produksi dilarang memanggil komponen yang belum berstatus `released`. Pelanggaran ditangkap oleh `ui:lint` dan `ds:audit`.
5. **INV-DS-05 (Proteksi Penghapusan Komponen Aktif):** Komponen berstatus `deprecated` dilarang dihapus (`removed`) jika pemindaian `ds:usage-scan` masih mendeteksi adanya penggunaan pada halaman aktif ($> 0$).
6. **INV-DS-06 (Determinisme Perhitungan Matematika Kontras):** Seluruh kalkulasi rasio kontras dan luminansi sRGB wajib deterministik murni integer tanpa drift presisi pecahan antar platform.
7. **INV-DS-07 (Integritas Dua Sumber Audit):** Seluruh status rilis komponen pada tabel `core_ds_components` wajib konsisten dengan riwayat transisi pada `core_ds_component_history` dan hasil pemindaian aktual `ds:usage-scan`. Diverifikasi oleh `ds:audit`.
8. **INV-DS-08 (Zero Horizontal Scroll di 375 px):** Seluruh halaman berlayout `x-ds.shell` wajib bebas dari overflow scroll horizontal pada lebar viewport 375 px.

---

## 5. Aturan Regulasi & Sumber Standar

- **W3C Web Content Accessibility Guidelines (WCAG) 2.1**:
  - *Tautan Sumber:* [W3C WCAG 2.1 Recommendation](https://www.w3.org/TR/WCAG21/)
  - *Tanggal Akses:* 2026-11-10
  - *Success Criterion 1.4.3 (Contrast Minimum - Level AA):* Rasio kontras teks normal minimal 4.5:1.
  - *Success Criterion 1.4.6 (Contrast Enhanced - Level AAA):* Rasio kontras teks normal minimal 7:1.
  - *Success Criterion 1.4.11 (Non-text Contrast - Level AA):* Elemen visual UI dan grafis bermakna minimal 3:1.
  - *Success Criterion 2.1.1 (Keyboard - Level A):* Seluruh fungsi antarmuka dapat dioperasikan via keyboard tanpa perangkap fokus.
  - *Success Criterion 2.4.7 (Focus Visible - Level AA):* Indikator fokus terlihat jelas saat penelusuran keyboard.
- **UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi (UU PDP)**:
  - Antarmuka publik dan preview komponen dilarang mengekspos data pribadi mentah (PII). Seluruh data simulasi di katalog komponen wajib menggunakan data sintesis berlabel fixture.

---

## 6. Alur Nilai (Value Stream)

```
[Kebutuhan Komponen Baru] 
       │
       ▼
[Pengajuan: proposed] ──(Validasi Keunikan)──► [Rancang: in_design]
                                                      │
                                                      ▼
[Audit Otomatis: ui:lint & A1/A2] ◄──────────────(Pemeriksaan)
       │
       ▼
[Review Aksesibilitas: a11y_review] ──(Temuan Tuntas)──► [Persetujuan Owner: approved]
                                                                  │
                                                                  ▼
[Katalog /design-system & Dokumentasi] ◄────────────── [Rilis Resmi: released]
       │
       ▼
[Digunakan di 30 Lini Bisnis SuperWeb via x-ds.*]
       │
       ▼
[Pemantauan Berkala: ds:usage-scan & Riset UX] ──► [Siklus Pembaruan / Deprecate]
```

---

## 7. Batas Simulasi

- **Tingkat Simulasi S1 (Komputasi Deterministik Internal):**
  - Kalkulasi kontras sRGB WCAG 2.1 murni dieksekusi secara lokal oleh kelas domain `WcagContrastCalculator` tanpa panggilan API pihak ketiga.
  - Linter DOM HTML dieksekusi secara in-process melalui ekstensi PHP DOM (`DOMDocument`) tanpa browser headless eksternal.
  - Pengujian responsif 375 px dievaluasi berbasis inspeksi markup struktural dan deteksi kelas utilitas lebar tetap (> 375 px) via `ui:lint --responsive`.

---

## 8. Katalog Kasus (15 Kasus Unik Bernama)

Berikut adalah 15 kasus pengujian deterministik yang diimplementasikan dan ditanam dalam seeder T1:
1. `CASE-DS-01 (Kontras Tepat 4.5:1 Lulus AA)`: Pasangan warna `#767676` di atas latar `#FFFFFF` menghasilkan rasio 45.422 bps ($\ge 45.000$, LULUS Level AA). Dites pada teks normal body.
2. `CASE-DS-02 (Kontras 4.478:1 Gagal AA)`: Pasangan warna `#777777` di atas latar `#FFFFFF` menghasilkan rasio 44.780 bps ($< 45.000$, GAGAL Level AA). Menghasilkan penolakan otomatis pada gerbang audit.
3. `CASE-DS-03 (Pewarisan Tema Kontras Tinggi Mewarisi Dark)`: Tema `hc` mewarisi token warna utama dari tema `dark` (`#93C5FD`), namun mendefinisikan latar hitam pekat `#000000` dan teks putih `#FFFFFF`.
4. `CASE-DS-04 (Penyelesaian Alias Token di Tema Peminta)`: Alias `{text}` pada tema `hc` diselesaikan ke `#FFFFFF` menghasilkan kontras sempurna 210.000 bps (21:1).
5. `CASE-DS-05 (Siklus Dependensi Token Ditolak)`: Konfigurasi token sirkular $a = \{b\}$ dan $b = \{a\}$ menghasilkan galat `TOKEN_CYCLE` dan membatalkan build token.
6. `CASE-DS-06 (Token Tidak Terdefinisi)`: Alias merujuk pada token yang tidak ada (`{token_ghaib}`) menghasilkan galat `TOKEN_UNDEFINED`.
7. `CASE-DS-07 (Komponen Deprecated Masih Digunakan)`: Komponen `old-data-grid` berstatus `deprecated` ditolak untuk transisi ke `removed` karena `ds:usage-scan` mendeteksi 12 pemanggilan di view produksi.
8. `CASE-DS-08 (Input Form Tanpa Label Tertangkap Linter)`: Tag `<input type="text" name="nik">` tanpa atribut `aria-label` dan tanpa pasangan `<label for="...">` tertangkap sebagai pelanggaran `INPUT_LABEL` pada nomor baris yang tepat.
9. `CASE-DS-09 (Gambar Tanpa Atribut Alt)`: Tag `<img src="logo.png">` tanpa atribut `alt` tertangkap sebagai pelanggaran `IMG_ALT`, sementara `<img src="dec.png" alt="">` lolos sebagai elemen dekoratif.
10. `CASE-DS-10 (Tombol Kosong Tanpa Nama Aksesibel)`: Tag `<button class="p-2"></button>` tertangkap sebagai pelanggaran `BUTTON_NAME`.
11. `CASE-DS-11 (Tabindex Positif Merusak Urutan Fokus)`: Tag `<div tabindex="2">` tertangkap sebagai pelanggaran `TABINDEX_POSITIVE`.
12. `CASE-DS-12 (Tabel Enterprise Berubah Menjadi Kartu di 375 px)`: Komponen `x-ds.table` merender markup responsif dengan kelas CSS `@media (max-width: 375px)` yang menyusun baris menjadi blok kartu mandiri.
13. `CASE-DS-13 (Pencegahan Double-Submit Form Modal)`: Komponen `x-ds.modal-confirm` menonaktifkan tombol submit seketika dan menampilkan spinner saat aksi dikirim.
14. `CASE-DS-14 (Evolusi Iterasi Riset UX)`: Iterasi riset UX `Sprint-42-Checkout` mencatat peningkatan task success rate dari 6.500 bps (65%) menjadi 9.200 bps (92%) setelah perbaikan kontras dan penataan form.
15. `CASE-DS-15 (Audit Korupsi Status vs Riwayat)`: Percobaan modifikasi langsung status komponen di basis data tanpa baris riwayat `core_ds_component_history` tertangkap seketika oleh `ds:audit` dengan kode keluar non-nol.

---

## 9. Penjelasan Mendalam Setiap Kasus Katalog

### Rincian Teknis CASE-DS-01 s/d CASE-DS-05
- **CASE-DS-01:** Menjadi batas ukur presisi matematis antara $181.164$ ppm luminansi teks terhadap $1.000.000$ ppm latar putih. Formula pembagian integer bulat menghasilkan $45.422$ bps yang berada tepat $422$ bps di atas ambang minimum Level AA.
- **CASE-DS-02:** Menunjukkan bahwa selisih hanya satu tingkat kecerahan heksadesimal (`#777777` vs `#767676`) menurunkan luminansi relatif ke $184.475$ ppm, menyebabkan rasio kontras jatuh ke $44.780$ bps, yang secara tegas ditolak oleh verifier.
- **CASE-DS-03 & CASE-DS-04:** Menguji sifat pewarisan polimorfik tema tri-state di mana tema kontras tinggi tidak mendefinisikan ulang token `primary`, namun mewarisi warna terang `#93C5FD` dari tema gelap, menghasilkan kontras $116.454$ bps (11.6:1) terhadap latar hitam `#000000`.
- **CASE-DS-05:** Menguji stabilitas algoritma saat developer secara keliru membuat hubungan dependensi berputar. Algoritma menelusuri penumpukan stack `visited` dan menghentikan loop pada siklus pertama dengan kode keluar `TOKEN_CYCLE`.

### Rincian Teknis CASE-DS-06 s/d CASE-DS-11
- **CASE-DS-06:** Mengisolasi kegagalan token hilang tanpa menjatuhkan seluruh aplikasi, memberikan feedback diagnostik spesifik pada tahap kompilasi CSS.
- **CASE-DS-07:** Penegakan invariant penghapusan komponen. Sistem menolak penghapusan komponen selama jejak pemakaian di `core_ds_component_usage` belum nol, mencegah timbulnya galat runtime `ViewException: Component [old-data-grid] not found`.
- **CASE-DS-08 s/d CASE-DS-11:** Serangkaian kasus deteksi statis pada AST DOM HTML yang mencegah kode cacat aksesibilitas masuk ke lingkungan staging maupun produksi melalui linter `ui:lint`.

### Rincian Teknis CASE-DS-12 s/d CASE-DS-15
- **CASE-DS-12:** Mengatasi kendala krusial staf lapangan dengan resolusi layar 375 px, mentransformasikan layout sel baris horisontal menjadi daftar tumpukan kartu semantik lengkap dengan label atribut data.
- **CASE-DS-13:** Mencegah masalah umum transaksi ganda (*duplicate submission*) pada aksi pembayaran atau persetujuan dokumen melalui penguncian otomatis tombol submit via Alpine.js `x-bind:disabled="submitting"`.
- **CASE-DS-14:** Menghubungkan riset lapangan kualitatif dengan metrik kuantitatif terukur dalam satuan integer basis points (bps), yang terintegrasi pada papan visualisasi tren SVG di `/design-system/research`.
- **CASE-DS-15:** Menjamin integritas dua sumber (*two-source audit*), di mana setiap perubahan status komponen wajib dapat ditelusuri ke rekaman append-only pada tabel riwayat `core_ds_component_history`.
