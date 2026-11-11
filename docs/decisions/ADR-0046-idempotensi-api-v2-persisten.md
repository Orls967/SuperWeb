# ADR-0046: Idempotensi Persisten API v2 Selama 24 Jam (W8-D46)

## Status
Diterima (Accepted)

## Konteks
Mitra eksternal melakukan retry otomatis saat jaringan terputus pada endpoint mutasi (`/api/v2/{resource}/actions/*`, `POST /api/v2/edi/interchanges`, `POST /api/v2/webhooks/*/test`). Implementasi cache sementara pada layanan lama tidak persisten terhadap restart cache dan tidak membedakan pengulangan payload identik vs manipulasi isi dengan kunci sama.

## Keputusan
1. **Header `Idempotency-Key` Wajib pada Mutasi**: Seluruh rute `/api/v2/*` bermetode `POST`, `PUT`, `PATCH`, `DELETE` yang memodifikasi state wajib membawa header `Idempotency-Key`. Ketiadaan header mengembalikan HTTP `422 Unprocessable Content` dalam format `application/problem+json`.
2. **Penyimpanan Fakta & Hash Kanonik 24 Jam**: `IdempotencyStoreService` menghitung hash SHA-256 kanonik badan permintaan (`request_hash`) dan menyimpannya ke tabel `core_api_idempotency_records` dengan kunci unik `(client_code, route_name, http_method, idempotency_key)` selama tepat 24 jam menurut `ClockInterface`.
3. **Semantik Replay vs Konflik**:
   - Kunci sama + `request_hash` identik sebelum kedaluwarsa $\implies$ mengembalikan status HTTP dan payload respons asli secara utuh tanpa menjalankan ulang aksi domain (`X-Idempotent-Replay: true`).
   - Kunci sama + `request_hash` berbeda $\implies$ mengembalikan HTTP `409 Conflict` berformat RFC 7807 Problem Details dan menolak eksekusi.

## Konsekuensi
- Menjamin retry klien B2B bersifat idempoten penuh bahkan setelah restart cache/worker.
- Menutup kelas bug X4 dengan melarang pembuatan kunci acak di sisi server saat memanggil handler downstream.
