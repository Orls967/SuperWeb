# ADR-0052: Retensi Cold Archive > 5 Tahun, Drill Pemulihan & Perluasan `api:audit` (W8-D52)

## Status
Diterima (Accepted)

## Konteks
Tabel catatan peristiwa dan audit jangka panjang terus membesar seiring operasional lintas modul. Diperlukan mekanisme pemindahan data berusia > 5 tahun yang aman secara kriptografis tanpa mengandalkan partisi native DDL spesifik mesin database tertentu, serta audit dua-sumber menyeluruh untuk seluruh komponen Fase 55 tanpa menggandakan signature command `api:audit`.

## Keputusan
1. **Arsip Cold Storage Terverifikasi Digest (`RetentionArchiveProvider`)**:
   - Berlaku eksklusif bagi tabel terdaftar melalui kontrak `RetentionArchiveProvider` di 3 modul berbeda (`core_audit_logs -> core_audit_logs_archive`, `lgx_tracking_events -> lgx_tracking_events_archive`, `prc_po_versions -> prc_po_versions_archive`).
   - Command harian idempoten `api:archive-run` memindahkan baris berumur > 5 tahun per potongan, memverifikasi kecocokan jumlah baris serta hash SHA-256 kanonik antar-sumber sebelum menghapus baris dari tabel aktif.
   - Parameter partisi native database didokumentasikan sebagai parameter konfigurasi kebijakan (`label: asumsi_simulasi`) tanpa implementasi DDL partisi paksa di SQLite/MySQL.
2. **Drill Pemulihan (`api:archive-drill`)**:
   - Mengambil satu potongan arsip tersimpan ke tabel drill aman `core_archive_drill_restores`, menghitung ulang digest SHA-256 kanonik, dan membandingkannya dengan digest manifest `core_retention_runs` guna membuktikan keterbacaan cadangan.
3. **Perluasan `api:audit` Dua Sumber Tanpa Duplikasi Signature**:
   - Mengingat `AuditIntegrationCommand` di `modules/Integration` harus tetap menjadi deklarasi `$signature = 'api:audit'` tunggal (`CommandSignatureUniqueTest`) dan berkas di `modules/Integration/**` tidak boleh dimodifikasi selain `@deprecated`, Core mengaitkan `ApiAuditCompositeCommand` melalui hook `Artisan::starting()` di `CoreServiceProvider::booted()`.
   - Hook menjalankan audit warisan `Integration` + audit platform economy DAN tujuh pemeriksaan baru Fase 55 (sinkronisasi OpenAPI v2 vs rute, verifikasi ulang B2 100% webhook, delivery 2xx nyata, EDI `posted` ber-handler, klien aktif berkunci aktif, middleware keamanan rute v2, dan keaktifan `TenantIsolationScope`), lengkap dengan sub-skenario di `ApiAuditCorruptionFixture`.

## Konsekuensi
- Menjamin integritas dual-source lintas 7 sumber Fase 55 serta menjaga seluruh test eksisting `ApiAuditCommandTest` dan `AuditCommandContractTest` tetap hijau.
