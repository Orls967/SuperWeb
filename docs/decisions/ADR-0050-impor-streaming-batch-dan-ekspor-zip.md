# ADR-0050: Impor Massal Streaming Berkelanjutan & Ekspor Berkas ZIP Asinkron (W8-D50)

## Status
Diterima (Accepted)

## Konteks
Operasi B2B skala besar (katalog produk distributor, harga eceran tertinggi, tarif bea cukai HS Code) sering melibatkan pertukaran puluhan ribu baris data. Pendekatan lama yang memuat seluruh berkas ke dalam memori PHP via `file_get_contents` atau `str_getcsv` memicu kehabisan batas alokasi memori (*out of memory*) dan kegagalan total proses (*all-or-nothing failure*) jika terdapat satu baris tidak valid di baris ke-19.000.

Selain itu, jika proses latar belakang terhenti akibat restart container atau timeout jaringan, pengunggahan harus diulang dari baris pertama.

## Keputusan Arsitektur

### 1. Validasi & Pemrosesan Streaming Berkelompok (Chunk 500 Baris)
`StreamingBulkImportService` menggunakan pointer berkas streaming berbasis `SplFileObject`:
- Berkas dibaca per baris tanpa memuat seluruh berkas ke RAM, menjamin konsumsi memori konstan $< 32\text{ MB}$ bahkan untuk berkas berukuran ratusan megabita.
- Validasi awal menghasilkan ringkasan: `total_rows`, `valid_rows`, dan `error_rows`.
- Baris yang tidak valid diisolasi ke tabel `core_bulk_import_errors` lengkap dengan nomor baris dan alasan galat, tanpa menghentikan pemrosesan baris-baris sah lainnya.

### 2. Batas Ambang Persetujuan Wajib (> 10.000 Baris)
Bila jumlah baris pada berkas impor melebihi 10.000 baris:
- Status job secara otomatis diatur menjadi `awaiting_approval`.
- Eksekusi mutasi basis data ditangguhkan hingga pengguna berwenang (peran `api_admin` atau `super_admin`) menyetujui job melalui `approveJob()`.
- Hal ini mencegah injeksi perubahan harga atau persediaan massal secara tidak terkontrol oleh integrasi eksternal.

### 3. Kemampuan Melanjutkan Proses yang Terputus (*Resumable Job Execution*)
- Setiap kelompok 500 baris yang selesai diproses memperbarui kolom `processed_rows` dan `last_processed_row` pada catatan `CoreBulkImportJob`.
- Jika eksekusi terputus, operator dapat menjalankan perintah CLI:
  ```bash
  php artisan api:import {job_id} --resume
  ```
- Pembaca berkas akan melakukan `seek()` langsung ke baris `last_processed_row` dan melanjutkan pemrosesan sisa baris secara aman dan idempoten.

### 4. Ekspor ZIP Chunked dengan Tautan Bertanda Tangan Sementara
Untuk penarikan data massal hingga 50.000 baris:
- `AsyncZipExportService` mengekstrak data menggunakan generator kursor `lazy()` Eloquent.
- Data dikompresi langsung ke arsip berkas ZIP dengan ringkasan hash SHA-256 tercatat pada `CoreBulkExportJob`.
- Unduhan berkas dilindungi oleh temporary signed URL Laravel dengan masa berlaku 15 menit:
  ```php
  URL::temporarySignedRoute('admin.api.exports.download', now()->addMinutes(15), ['export' => $job->id]);
  ```
- Perintah terjadwal `api:export-purge` secara otomatis menghapus berkas ZIP lokal yang berumur $> 7\text{ hari}$ guna mencegah kepenuhan disk penyimpanan.

## Konsekuensi & Kepatuhan
- **Positif:** Skalabilitas tinggi untuk berkas data besar, perlindungan memori terjamin, pemulihan insiden cepat tanpa duplikasi.
- **Negatif:** Penulisan I/O disk lokal membutuhkan kuota ruang sementara yang cukup pada volume server.
- **Verifikasi:** Diuji pada `ApiV2LifecycleTest` skenario `test_55_4_bulk_import_20k_resume_export_zip_and_tenant_isolation`.
