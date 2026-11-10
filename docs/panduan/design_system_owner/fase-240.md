# Panduan Peran: Design System Owner (Fase 240)

**Peran RBAC:** `design_system_owner`  
**Modul Pengampu:** `Core` & `Shared`  
**Dokumen Panduan:** `docs/panduan/design_system_owner/fase-240.md`  

---

## 1. Ikhtisar Peran & Tanggung Jawab

Peran `design_system_owner` memegang mandat penuh atas keselarasan antarmuka, konsistensi token tema, pemenuhan standar aksesibilitas WCAG 2.1 Level AA/AAA, dan tata kelola rilis komponen di seluruh 30 lini bisnis SuperWeb.

---

## 2. Alur Kerja Standar (Standard Operating Procedures)

### Prosedur 1: Mengelola Permohonan Komponen Baru
1. **Membuka Daftar Rilis:**
   - Navigasi ke URL `/design-system/releases`.
   - Periksa komponen berstatus `proposed` yang diajukan oleh modul bisnis.
2. **Evaluasi Usulan Bisnis:**
   - Telaah alasan pengajuan (`reason`) dan modul pemohon (`requested_by_module`).
   - Jika usulan identik dengan komponen yang sudah ada, tolak usulan ke status `removed` dengan alasan `DUPLICATE_OF_{EXISTING}`.
   - Jika usulan diterima, jalankan transisi ke status `in_design`.
3. **Validasi Desain & Aksesibilitas (`in_design` $\rightarrow$ `a11y_review`):**
   - Pastikan implementasi Blade komponen telah dilengkapi token semantik.
   - Sistem secara otomatis menjalankan `ui:lint` dan verifikasi kontras A1 pada 3 tema (`light`, `dark`, `hc`).
   - Jika lulus tanpa pelanggaran, status bergeser ke `a11y_review`.

### Prosedur 2: Peninjauan Aksesibilitas & Persetujuan Rilis
1. **Meninjau Temuan Aksesibilitas:**
   - Pada halaman detail rilis `/design-system/components/{name}`, periksa daftar `core_ds_a11y_findings`.
   - Bila terdapat temuan (misal: rasio kontras warna tertentu $< 45.000$ bps atau atribut form hilang), minta pengembang modul melakukan remediasi (`remediation`).
2. **Persetujuan Final Rilis (`approved`):**
   - Setelah seluruh temuan ditandai remediated (`is_remediated = true`), klik tombol **"Setujui Rilis Komponen"**.
   - Aksi ini memicu integrasi dengan `ApprovalEngine` modul Core, membubuhkan tanda tangan digital approval owner.
3. **Penerbitan ke Katalog Publik (`released`):**
   - Jalankan transisi ke `released`.
   - Komponen resmi tampil di katalog `/design-system` dan dapat diimpor oleh seluruh modul aplikasi.

### Prosedur 3: Deprecate dan Hapus Komponen Usang
1. **Menandai Deprecated:**
   - Ketika komponen baru pengganti telah siap, transisikan komponen lama ke status `deprecated`.
   - Tetapkan penunjuk `replacement_component` (wajib diisi).
2. **Memantau Pengurangan Pemakaian:**
   - Pantau laporan harian dari perintah `ds:usage-scan`.
   - Koordinasikan dengan tim pengembang modul untuk memigrasikan view Blade yang masih memanggil komponen usang tersebut.
3. **Pembersihan Final (`removed`):**
   - Transisi ke `removed` hanya dapat dilakukan apabila `ds:usage-scan` mencatat total pemanggilan aktual = 0 di seluruh berkas view produksi.

### Prosedur 4: Pemantauan Riset UX & Task Success Rate
1. **Membuka Dashboard Riset:**
   - Akses `/design-system/research`.
   - Pantau visualisasi SVG tren *Task Success Rate* (bps) per iterasi riset keterpakaian (*usability test*).
2. **Menindaklanjuti Temuan UX:**
   - Temuan riset berstatus `OPEN` dapat dipindahkan ke `BACKLOG` atau dikaitkan langsung dengan komponen kanonikal terkait untuk perbaikan struktural.
   - Ekspor laporan metrik riset via tombol **"Ekspor Laporan CSV"**.

---

## 3. Prosedur Pengujian Pembaca Layar (Screen Reader Manual Test)

Sebelum memberikan approval untuk rilis komponen interaktif, lakukan pengujian manual dengan pembaca layar (NVDA di Windows atau VoiceOver di macOS):

1. **Pengujian Modal Dialog (`x-ds.modal-confirm`):**
   - Buka modal dialog menggunakan keyboard (tekan `Enter` pada pemicu).
   - Pastikan fokus keyboard langsung berpindah ke tombol pertama di dalam modal atau elemen judul modal (`aria-labelledby`).
   - Tekan tombol `Tab` berulang kali. Pastikan fokus hanya berputar di dalam elemen-elemen modal (*focus trap*) dan tidak pernah bocor ke elemen latar belakang halaman.
   - Tekan tombol `Escape`. Pastikan modal tertutup dan fokus keyboard kembali ke elemen pemicu awal.

2. **Pengujian Dropdown & Menu Navigasi:**
   - Navigasikan ke komponen dropdown menggunakan tombol `Tab`.
   - Tekan `Panah Bawah` atau `Space` untuk membuka daftar menu.
   - Gunakan `Panah Atas` dan `Panah Bawah` untuk menjelajahi item. Pastikan pembaca layar mengumumkan nama item dan posisinya (misal: "Opsi 1 dari 5").
   - Tekan `Enter` untuk memilih, atau `Escape` untuk membatalkan dan menutup menu.

3. **Pengujian Tabel Enterprise (`x-ds.table`):**
   - Navigasikan ke baris header tabel. Pastikan kolom yang dapat diurutkan diumumkan dengan status sortirnya (`aria-sort="ascending"`, `aria-sort="descending"`, atau `aria-sort="none"`).
   - Pada baris data dengan angka moneter, pastikan angka dibacakan dengan jelas dalam konteks mata uang IDR.

---

## 4. Matriks Eskalasi & Kontak Darurat

Bila terjadi kegagalan sistemik antarmuka (misal: kegagalan kompilasi token tema di lingkungan produksi):
- Hubungi tim Core Platform & DX.
- Lakukan rollback darurat ke versi rilis token sebelumnya dengan memulihkan berkas `resources/css/ds-tokens.css` dari commit rilis terakhir.
- Batalkan deployment yang sedang berlangsung via alur pembatalan Release Train.
