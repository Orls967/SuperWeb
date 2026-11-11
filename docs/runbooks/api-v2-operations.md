# Runbook Operasional: API Gateway v2, Webhook, EDI & Multi-tenancy (Fase 55)

**Dokumen Kontrol:** SW-RB-55-01  
**Versi:** 1.0.0  
**Tanggal:** 2026-11-12  
**Lingkup:** Modul `Core`, integrasi antar-modul (`B2b`, `Distribution`, `Procurement`, `Wms`, `Treasury`, `Finance`, `Logistics`, `Trade`), gerbang API v2, dan partisi cold archive.

---

## 1. Ikhtisar Komponen & Topologi

API Gateway v2 menyediakan lapisan gerbang terpadu untuk mitra eksternal B2B, pertukaran berkas EDI standar industri, orkestrator transmisi webhook dengan rotasi HMAC, dan pemrosesan impor/ekspor data massal streaming.

```
                              [Mitra Eksternal B2B / Sistem Korporat]
                                                 │
                                                 ▼
                             ┌───────────────────────────────────────┐
                             │   ApiV2GatewayMiddleware              │
                             │  - Autentikasi Sanctum / Key Bearer   │
                             │  - Konteks Multi-tenancy Fail-Closed │
                             │  - Integer Token Bucket Throttling    │
                             │  - Idempotensi Persisten 24 Jam       │
                             └───────────────────┬───────────────────┘
                                                 │
                     ┌───────────────────────────┼───────────────────────────┐
                     ▼                           ▼                           ▼
        ┌─────────────────────────┐ ┌─────────────────────────┐ ┌─────────────────────────┐
        │  ApiV2ResourceController │ │    ApiV2EdiController   │ │   ApiV2BulkController   │
        │ - RESTful Enveloping    │ │ - Codec EDIFACT/X12     │ │ - Streaming CSV Chunk   │
        │ - Cursor Pagination     │ │ - State Machine 8-Status│ │ - Async ZIP Exporter    │
        │ - RFC 7807 Probs        │ │ - Dispatcher Downstream │ │ - Persetujuan >10.000   │
        └─────────────────────────┘ └─────────────────────────┘ └─────────────────────────┘
```

---

## 2. Prosedur Manajemen Klien API & Kuota Token Bucket

### A. Alokasi Tingkatan Kuota (*Tiering*)
Setiap klien B2B terdaftar pada salah satu dari tiga tingkatan kuota dengan parameter berikut:
- **SILVER**: Kapasitas burst = 60 token, laju isi ulang = 1 token/detik (60 req/menit), kuota harian = 10.000 permintaan.
- **GOLD**: Kapasitas burst = 300 token, laju isi ulang = 5 token/detik (300 req/menit), kuota harian = 100.000 permintaan.
- **PLATINUM**: Kapasitas burst = 1.200 token, laju isi ulang = 20 token/detik (1.200 req/menit), kuota harian = 1.000.000 permintaan.

### B. Rumus Algoritma Integer Token Bucket (Tanpa Titik Mengambang)
Algoritma berjalan secara deterministik murni menggunakan integer milli-tokens ($1\text{ token} = 1.000\text{ milli-tokens}$):
$$\Delta t = \text{now\_ms} - \text{last\_ms}$$
$$\text{added\_milli} = \Delta t \times \text{rate\_per\_sec}$$
$$\text{refilled\_milli} = \min(\text{capacity} \times 1.000, \text{current\_milli} + \text{added\_milli})$$
$$\text{cost\_milli} = 1 \times 1.000$$

Bila $\text{refilled\_milli} \ge \text{cost\_milli}$:
- Permintaan diizinkan (`allowed = true`).
- $\text{remaining\_milli} = \text{refilled\_milli} - \text{cost\_milli}$.
- Header respons: `X-RateLimit-Remaining: floor(remaining_milli / 1000)`.

Bila $\text{refilled\_milli} < \text{cost\_milli}$:
- Permintaan ditolak dengan status HTTP `429 Too Many Requests`.
- $\text{deficit\_milli} = \text{cost\_milli} - \text{refilled\_milli}$.
- $\text{retry\_after\_ms} = \lceil \text{deficit\_milli} / \text{rate\_per\_sec} \rceil$.
- $\text{retry\_after\_sec} = \lceil \text{retry\_after\_ms} / 1.000 \rceil$.
- Header respons: `Retry-After: {retry_after_sec}`, payload RFC 7807 `application/problem+json`.

### C. Penanganan Insiden Throttling Berulang
1. Periksa log penggunaan pada tabel `core_api_usage_events` atau jalankan `php artisan api:usage-rollup`.
2. Identifikasi apakah klien mengalami loop retry tanpa backoff. Klien wajib mengindahkan header `Retry-After`.
3. Jika lonjakan permintaan adalah transaksi musiman sah, tingkatkan sementara kapasitas melalui konsol admin:
   ```bash
   php artisan tinker --execute="Modules\Core\Domain\Models\CoreApiClient::where('code', 'CLI-XXX')->update(['capacity' => 600, 'refill_per_sec' => 10]);"
   ```

---

## 3. Prosedur Rotasi Kunci API Tanpa Waktu Henti (*Zero-Downtime Key Rotation*)

### A. Kebijakan Masa Tenggang (*Dual-Active Grace Period*)
Sesuai ADR-0048, saat kunci API klien dirotasi:
1. Kunci baru diterbitkan dengan status `active` dan `expires_at = null`.
2. Kunci lama tetap berstatus `active` dengan stempel waktu kedaluwarsa eksplisit $\le 7\text{ hari}$ (`expires_at = now() + grace_days`).
3. Selama masa tenggang, request dengan kunci lama tetap dilayani.
4. Setelah masa tenggang habis, perintah terjadwal `api:keys-rotate --expire-old` secara otomatis mengubah status kunci lama menjadi `revoked`.

### B. Perintah Operasional Rotasi Kunci
Untuk merotasi seluruh kunci klien aktif dengan masa tenggang 7 hari:
```bash
php artisan api:keys-rotate --grace-days=7
```
Untuk merotasi kunci klien tertentu saja:
```bash
php artisan api:keys-rotate --client=CLI-B2B-S01 --grace-days=3
```
Untuk mencabut kunci yang telah melewati masa kedaluwarsa:
```bash
php artisan api:keys-rotate --expire-old
```

---

## 4. Pengelolaan Webhook & Circuit Breaker

### A. Spesifikasi Header Tanda Tangan HMAC
Setiap dispatch webhook membawa header tanda tangan `X-Signature` dengan format:
```http
X-Signature: t=1700000000,v1=0d74ebc95953607011bb60f2d3651900e64d5d82aeb6b91d62eb922cf3ac68eb
```
Di mana input tanda tangan dihitung via:
$$\text{signed\_input} = \text{timestamp} \mathbin{\Vert} \text{"."} \mathbin{\Vert} \text{payload\_json}$$
$$\text{v1} = \text{HMAC-SHA256}(\text{signed\_input}, \text{webhook\_secret})$$

Saat rotasi rahasia webhook berlangsung, dua tanda tangan dipancarkan secara bersamaan:
```http
X-Signature: t=1700000000,v1={hash_rahasia_lama},v1={hash_rahasia_baru}
```
Penerima dapat memvalidasi salah satu tanda tangan selama masa transisi.

### B. Ambang Batas Pemutus Arus (*Circuit Breaker*)
- **Status Normal (`closed`)**: Kegagalan beruntun $< 5$. Transmisi dicoba ulang dengan exponential backoff.
- **Status Terbuka (`open`)**: Kegagalan beruntun $\ge 5$. Transmisi ke URL target dihentikan sementara selama 30 menit. Dispatch dialihkan ke Dead-Letter Queue (`core_event_deliveries` status `dead_lettered`).
- **Pemulihan Otomatis (`half-open`)**: Setelah masa tenggang 30 menit berlalu, sistem mengirimkan satu uji *probe*. Jika berhasil, status kembali ke `closed`.

### C. Pemulihan Manual Webhook yang Terbuka
Jika endpoint mitra telah diperbaiki dan pemutus arus perlu ditutup kembali secara manual:
```bash
php artisan tinker --execute="Modules\Core\Domain\Models\CoreWebhook::where('code', 'WH-XXX')->update(['circuit_state' => 'closed', 'consecutive_failures' => 0, 'circuit_opened_at' => null]);"
```

---

## 5. Saluran Pemrosesan EDI & State Machine

### A. Alur 8 Status Siklus Hidup EDI
Setiap berkas EDIFACT (ORDERS, INVOIC, DESADV) maupun ASC X12 (850, 810, 856) melewati siklus hidup berikut:
1. `received`: Payload mentah dicatat ke basis data dengan hash kanonik.
2. `parsed`: Segmen dipisahkan dengan perlakuan karakter escape (`?` pada EDIFACT, `*` delimiter pada X12).
3. `validated`: Validasi jumlah segmen pesan (UNT segment count vs actual, UNH/UNT ref matching, UNB/UNZ matching).
4. `mapped`: Dipetakan ke modul pemilik downstream (`Distribution`, `Procurement`, `Wms`).
5. `acknowledged`: Fungsional acknowledgment (CONTRL atau 997) dikonstruksi dan siap diambil mitra.
6. `posted`: Aksi domain downstream dieksekusi (membuat draft pesanan/faktur) dengan **JAMINAN ZERO LEDGER POSTING**.
7. `rejected`: Ditolak jika terjadi kesalahan sintaks, ketidakcocokan segmen, atau kegagalan modul downstream.
8. `archived`: Berkas telah dipindahkan ke partisi cold retention.

### B. Invarian Kekebalan Catatan Transisi (*Immutable Transition Guard*)
Tabel `core_edi_transitions` dilengkapi pengawal tingkat model Eloquent (`CoreEdiTransition`) yang mencegah operasi `UPDATE` dan `DELETE`. Setiap upaya memanipulasi riwayat transisi akan melempar `DomainException: IMMUTABLE_AUDIT_LOG`.

---

## 6. Penanganan Impor Massal Streaming & Ekspor ZIP

### A. Alur Impor Streaming
1. Klien mengunggah berkas CSV ke `/api/v2/bulk/imports`.
2. Validasi streaming baris demi baris menggunakan `SplFileObject` (potongan 500 baris, memori konstan $< 32\text{ MB}$).
3. Jika total baris $\le 10.000$, job dapat langsung dieksekusi via `StreamingBulkImportService::executeJob`.
4. Jika total baris $> 10.000$, job dialihkan ke status `awaiting_approval`. Administrator wajib menyetujui job melalui konsol admin atau panggilan API berwenang `api_admin`.
5. Jika proses terputus, perintah `api:import {job} --resume` melanjutkan pemrosesan mulai dari `last_processed_row` tanpa menduplikasi data (*idempotent chunk resume*).

### B. Ekspor ZIP Chunked
1. Sistem mengekstrak hingga 50.000 baris data menggunakan generator lazy cursor.
2. Data dikompresi menjadi arsip ZIP dengan checksum SHA-256 tercatat.
3. Tautan unduhan diterbitkan menggunakan temporary signed URL Laravel dengan batas masa berlaku 15 menit.
4. Perintah pembersihan terjadwal `api:export-purge` menghapus berkas ZIP lokal yang telah melewati masa retensi 7 hari.

---

## 7. Retensi Data & Cold Archive Drill

### A. Kebijakan Retensi Berjenjang
- **Core Audit Logs**: Retensi operasional 90 hari, dipindahkan ke `core_audit_logs_archive`.
- **Logistics Tracking Events**: Retensi operasional 180 hari, dipindahkan ke `lgx_tracking_events_archive`.
- **Procurement PO Versions**: Retensi operasional 365 hari, dipindahkan ke `prc_po_versions_archive`.

### B. Eksekusi Pemindahan & Simulasi Bencana (*Disaster Recovery Drill*)
Perintah reguler pemindahan partisi:
```bash
php artisan api:archive-run
```
Perintah audit integritas dan drill restorasi uji petik:
```bash
php artisan api:archive-drill
```
Drill restorasi mengambil sampel acak baris arsip, menghitung ulang ringkasan hash SHA-256, dan membandingkannya dengan `chunk_sha256` pada catatan `core_retention_runs`. Diskrepansi sekecil apa pun akan memicu alert kegagalan integritas.

