# ADR-0048: Pengiriman Webhook B2B via Event Spine, HMAC B2 & Circuit Breaker (W8-D48)

## Status
Diterima (Accepted)

## Konteks
Implementasi webhook lama di modul Integration menulis `delivered` pada skema lokal tanpa integrasi outbox persisten, tanpa dukungan rotasi secret aktif bersamaan, tanpa jendela anti-replay eksplisit, dan tanpa perlindungan circuit breaker terhadap endpoint mitra yang mati. Membangun mesin retry baru juga dilarang karena W8-P3 telah menyediakan Event Spine dan `DeadLetterQueueService`.

## Keputusan
1. **Penandatanganan & Verifikasi `WebhookSignatureVerifier` (B2)**:
   - Setiap dispatch menandatangani payload mentah memakai format header `X-Signature: t=<unix>,v1=<hex>[,v1=<hex>]` dengan `hex = HMAC-SHA256(secret, "<t>.<raw_payload>")`.
   - Verifikasi menegakkan jendela replay `|now - t| <= 300` detik, perbandingan konstan waktu `hash_equals()`, serta dukungan dua secret aktif bersamaan (rotasi maksimum **7 hari**) dan multi-nilai `v1=` sehingga klien dapat memverifikasi dengan kunci lama maupun baru selama masa transisi.
2. **Integrasi Penuh EventDelivery & DLQ W8-P3**:
   - Setiap pengiriman webhook menerbitkan pesan ke `OutboxBusInterface` dan melacak `CoreEventDelivery` (`core_event_deliveries`) serta `CoreWebhookDispatch` (`core_webhook_dispatches`). Retry mengikuti jadwal `DeterministicBackoff` (A5) dan kegagalan berulang bermigrasi ke konsol DLQ R8 (`DeadLetterQueueService`).
   - Pengiriman hanya dapat berstatus `delivered` setelah menerima kode respons HTTP **2xx nyata** (atau handler HTTP test in-process) serta lolos anti-SSRF `OutboxBusService::isSafeWebhookUrl()`.
3. **Circuit Breaker per Endpoint**:
   - `5` kali kegagalan berturut-turut mengalihkan circuit ke `open` selama `300` detik (pengiriman baru ditunda/di-DLQ tanpa membanjiri endpoint mati). Setelah 300 detik berlalu, status berpindah ke `half_open` untuk tepat `1` percobaan; sukses menutup circuit (`closed`), gagal membuka ulang 300 detik.

## Konsekuensi
- Keandalan pengiriman dan DLQ replay terpadu dalam infrastruktur W8-P3 tanpa kode antrean terpisah.
- Rotasi rahasia webhook mitra berlangsung mulus tanpa kehilangan event selama periode tumpang tindih 7 hari.
