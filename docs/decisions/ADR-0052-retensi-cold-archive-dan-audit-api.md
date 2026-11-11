# ADR-0052: Retensi Data Dingin, Partisi Berjenjang & Audit Kriptografis Dua Sumber API (W8-D52)

## Status
Diterima (Accepted)

## Konteks
Pertumbuhan transaksi B2B menghasilkan jutaan rekaman event pelacakan logistik, log audit, dan versi pesanan pembelian setiap kuartal. Mempertahankan data operasional berumur tua pada tabel transaksi panas (*hot OLTP tables*) memperlambat kinerja kueri harian, memperbesar indeks B-Tree, dan meningkatkan biaya komputasi database produksi.

Namun, regulasi perpajakan dan kepatuhan hukum menuntut penyimpanan rekaman hingga 5-10 tahun. Data yang diarsipkan tidak boleh hilang, tidak boleh termutasi, dan harus dapat diverifikasi integritasnya melalui pengujian petik bencana (*disaster recovery drill*).

## Keputusan Arsitektur

### 1. Kebijakan Retensi Berjenjang Berbasis Penyedia Kontrak
Modul `Core` mendefinisikan interface `RetentionArchiveProvider` yang diimplementasikan oleh masing-masing modul pemilik data:
- **Core:** Memindahkan `audit_logs` berumur $> 90\text{ hari}$ ke `core_audit_logs_archive`.
- **Logistics:** Memindahkan `lgx_tracking_events` berumur $> 180\text{ hari}$ ke `lgx_tracking_events_archive`.
- **Procurement:** Memindahkan `prc_po_versions` berumur $> 365\text{ hari}$ ke `prc_po_versions_archive`.

### 2. Eksekusi Pemindahan Partisi & Pencatatan Checksum (`api:archive-run`)
Perintah `api:archive-run` mengeksekusi pemindahan data secara bertahap dalam batch aman:
1. Membaca rekaman yang telah melewati batas kedaluwarsa hari retensi.
2. Memasukkan rekaman ke tabel arsip dingin target.
3. Menghitung ringkasan hash SHA-256 kanonik dari kumpulan baris yang dipindahkan (`chunk_sha256`).
4. Mencatat fakta eksekusi ke tabel `core_retention_runs` dengan status `verified`.
5. Menghapus baris dari tabel sumber panas setelah verifikasi penyisipan berhasil dikonfirmasi.

### 3. Simulasi Bencana & Uji Petik Restorasi (`api:archive-drill`)
Untuk memastikan arsip dingin tidak mengalami kerusakan data (*bit rot*) atau manipulasi ilegal:
- Perintah CLI `api:archive-drill` secara otomatis mengambil sampel acak kumpulan data arsip dari `core_retention_runs`.
- Melakukan restorasi sementara ke tabel simulasi `core_archive_drill_restores`.
- Menghitung ulang nilai hash SHA-256 baris terestorasi dan mencocokkannya dengan `chunk_sha256` asli.
- Bila nilai hash identik, status drill dicatat sebagai `verified_match`. Bila tidak cocok, status `parity_corrupted` dipicu dan memunculkan alarm kegagalan integritas.

### 4. Perintah Audit Dua Sumber Terpadu (`api:audit`)
Audit integritas API v2 dan integrasi B2B dijalankan melalui perintah `api:audit`:
- **Sumber 1 (Webhook HMAC):** Mengambil sampel pengiriman webhook terkirim (`delivered`) dan memverifikasi keabsahan tanda tangan terhadap rahasia terenkripsi.
- **Sumber 2 (EDI Inbound Integrity):** Memeriksa seluruh pertukaran EDI berstatus `posted`, memastikan keberadaan dokumen pengakuan `CONTRL`/`997`, modul handler terdaftar, serta referensi transaksi downstream.
- **Sumber 3 (API Client Keys):** Memverifikasi bahwa setiap klien B2B aktif memiliki minimal satu kunci aktif yang valid.
- Diskrepansi sekecil apa pun akan mengembalikan exit code 1 dan mencetak daftar rekaman yang rusak (*corrupted record identifiers*).

## Konsekuensi & Kepatuhan
- **Positif:** Menghemat ruang penyimpanan tabel panas hingga 70%, menjaga performa query OLTP tetap cepat, dan memenuhi syarat audit kepatuhan ISO 27001.
- **Negatif:** Kueri laporan komprehensif yang membutuhkan data historis sangat tua harus melakukan union manual ke tabel arsip.
- **Verifikasi:** Diuji pada `ApiV2LifecycleTest` skenario `test_55_5_api_audit_clean_and_corruption_fixtures` dan arsitektur audit registry test.
