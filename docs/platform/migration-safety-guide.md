# Panduan Keselamatan Migrasi Database — MySQL Zero-Downtime DDL

Dokumen ini menjelaskan arsitektur dan pedoman rekayasa skema database untuk memastikan seluruh migrasi pada modul Platform dan modul lainnya dapat berjalan tanpa downtime (zero-downtime) di lingkungan produksi MySQL 8.0+.

---

## 1. Masalah Utama DDL Konvensional pada Skala Besar

Perubahan skema (Data Definition Language / DDL) pada tabel dengan jutaan baris data memiliki risiko tinggi:
- **Table Lock**: Penguncian tabel secara eksklusif yang memblokir semua operasi pembacaan (SELECT) dan penulisan (INSERT/UPDATE/DELETE).
- **Connection Pool Exhaustion**: Kueri aplikasi mengantre menunggu pelepasan lock, mengakibatkan kehabisan koneksi pool PHP-FPM dan kegagalan sistem menyeluruh (504 Gateway Timeout).
- **Replication Lag**: Operasi DDL yang memakan waktu lama memperlambat replikasi ke replika baca (read replicas).
- **Disk I/O Spike**: Pembuatan tabel sementara (table copy) menghabiskan bandwidth disk I/O dan ruang penyimpanan penyimpanan database.

---

## 2. Taksonomi Operasi DDL MySQL 8.0+

Platform mengklasifikasikan setiap operasi DDL ke dalam 3 algoritma eksekusi:

| Algoritma | Karakteristik Kunci | Dampak Kunci (Lock) | Rekomendasi Penggunaan |
|---|---|---|---|
| **INSTANT** | Modifikasi kamus metadata saja, tanpa menyentuh data baris tabel | Hampir 0 detik (metadata lock mikrodetik) | Sangat dianjurkan untuk penambahan kolom baru di akhir tabel |
| **INPLACE** | Tabel diubah di tempat tanpa menyalin keseluruhan baris ke tabel baru | Lock bersama (SHARED) atau tanpa lock penulisan | Digunakan untuk pembuatan indeks sekunder baru |
| **COPY** | Tabel baru dibuat di latar belakang, seluruh baris disalin, lalu nama tabel diganti | Kunci eksklusif penuh; penulisan diblokir selama durasi penyalinan | Dilarang di produksi tanpa jendela pemeliharaan resmi |

---

## 3. Aturan Keselamatan Migrasi (Algoritma A5)

Algoritma `MigrationSafetyLinter` memindai berkas migrasi PHP untuk mendeteksi pola berbahaya:

### Aturan 1: Dilarang Menggunakan `dropColumn` Langsung
Menghapus kolom secara langsung akan menyebabkan kueri aplikasi yang sedang berjalan mengalami fatal error bila kolom tersebut masih dibaca oleh kode produksi aktif.
- **Solusi Dua Langkah (Expand & Contract)**:
  1. Hapus referensi kolom dari model Eloquent dan kode aplikasi.
  2. Rilis kode aplikasi ke produksi dan tunggu konfirmasi stabil.
  3. Pada rilis berikutnya, jalankan migrasi penghapusan kolom.

### Aturan 2: Dilarang Menambahkan Kolom Non-Nullable Tanpa Nilai Default
Menambahkan kolom baru dengan batasan NOT NULL tanpa menyertakan `->default(...)` pada tabel yang telah berisi baris data akan menyebabkan kegagalan eksekusi kueri penulisan.
- **Rekomendasi**: Selalu definisikan kolom baru sebagai `->nullable()` atau tentukan nilai bawaan eksplisit `->default(...)`.

### Aturan 3: Dilarang Mengganti Nama Kolom (`renameColumn`)
Mengganti nama kolom secara langsung memutus kompatibilitas mundur dengan versi aplikasi sebelumnya.
- **Rekomendasi**: Gunakan pendekatan penambahan kolom baru, replikasi data asinkron via backfill, kemudian alihkan pembacaan ke kolom baru.

### Aturan 4: Dilarang Mengubah Tipe Kolom yang Memerlukan Table Copy
Penyempitan tipe data (misalnya `BIGINT` menjadi `INT` atau `VARCHAR(255)` menjadi `VARCHAR(50)`) memicu verifikasi seluruh nilai baris dan penguncian tabel penuh.

---

## 4. Arsitektur Linter Statis Migrasi (`platform:migration-lint`)

Sistem linter diimplementasikan dengan komponen:
- `MysqlSchemaAstAnalyzer`: Memetakan struktur AST migrasi Laravel Blueprint.
- `SqlAstTokenScanner`: Memindai pernyataan mentah `DB::statement(...)`.
- `InstantDdlCapabilityMatrix`: Memeriksa kapabilitas algoritma DDL pada versi mesin database aktif.
- `MigrationSafetyCertificate`: Menghasilkan sertifikat validasi bertanda tangan digital jika tidak ditemukan pelanggaran.

Contoh penggunaan perintah linter:
```bash
php artisan platform:migration-lint --path=modules/Platform/database/migrations
```

Bila terdeteksi pelanggaran keselamatan, linter akan menghasilkan keluaran dengan kode keluar bukan nol (exit code 1) dan mencantumkan baris kode penyebab pelanggaran secara terperinci.

---

## 5. Pola Zero-Downtime: Expand and Contract Pattern

Untuk perubahan skema berskala masif, terapkan tahapan terkoordinasi:

```
Fase 1: Expand
   - Tambah kolom baru (nullable)
   - Tulis ganda (dual-write) ke kolom lama dan baru di aplikasi

Fase 2: Backfill
   - Jalankan job backfill asinkron untuk menyalin data lama ke kolom baru
   - Verifikasi integritas data menggunakan checksum

Fase 3: Switch
   - Alihkan operasi pembacaan aplikasi ke kolom baru sepenuhnya
   - Hentikan penulisan ke kolom lama

Fase 4: Contract
   - Hapus referensi kode ke kolom lama
   - Hapus kolom lama melalui migrasi pembersihan terpisah
```

Dengan menerapkan siklus ini secara disiplin, modul Platform menjamin ketersediaan sistem tetap 100% tanpa gangguan operasional selama pembaruan skema.
