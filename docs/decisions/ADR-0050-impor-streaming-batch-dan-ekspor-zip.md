# ADR-0050: Impor Streaming 500-Baris & Ekspor ZIP/XLSX Bertanda Tangan (W8-D50)

## Status
Diterima (Accepted)

## Konteks
Impor data B2B berskala ribuan baris (mis. 20.000 baris katalog/harga/HS code) tidak boleh dimuat sekaligus ke memori PHP (`array_map`/`file()`) karena melanggar batas memori produksi dan gagal melanjutkan saat terhenti di tengah batch. Ekspor data besar (≥ 50.000 baris) membutuhkan pengemasan terkompresi dan tautan unduh kedaluwarsa yang aman.

## Keputusan
1. **Impor Streaming Per Baris Tanpa Muat Penuh**:
   - Memakai `SplFileObject` (dan untuk XLSX minimal menggunakan `ZipArchive` + `XMLReader`/`SimpleXML` berbasis `ext-zip` bawaan PHP tanpa dependensi Composer eksternal).
   - Validasi dilakukan terlebih dahulu baris demi baris (**Fase 1 Dry-Run & Pratinjau Galat**) dan seluruh baris salah ditulis ke tabel `core_bulk_import_errors` serta file CSV galat berkolom nomor baris dan alasan.
2. **Batch Transaksi 500 Baris, Approval > 10.000 Baris & Resume Idempoten**:
   - Job dengan total baris valid $> 10.000$ wajib memperoleh persetujuan `ApprovalEngineInterface` dan menerbitkan tugas melalui `TaskInboxService::createTask()` sebelum eksekusi dimulai.
   - Eksekusi disalurkan melalui kontrak `BulkImportTarget` modul pemilik (`B2b`, `Distribution`, `Trade`) dalam transaksi atomik per 500 baris. Checkpoint `(job_id, last_processed_row)` dan kunci idempoten per `(job_id, row_number)` memastikan `php artisan api:import {job} --resume` melanjutkan tanpa duplikasi setelah interupsi.
3. **Ekspor Asinkron Per Potongan Ber-ZIP & Purging**:
   - `AsyncZipExportService` menulis dataset ekspor per potongan ke file CSV di penyimpanan sementara, memaketkannya menjadi berkas ZIP lewat `ZipArchive`, dan menandatangani URL unduhan menggunakan HMAC-SHA256 dengan masa berlaku **15 menit** (`expires` + `signature`).
   - Command harian `api:export-purge` menghapus arsip kedaluwarsa secara idempoten.

## Konsekuensi
- Konsumsi memori eksekusi konstan $O(\text{chunk\_size})$ meskipun jumlah baris tumbuh hingga $\ge 50.000$.
