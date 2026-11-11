# ADR-0047: Rate Limiter Integer Token Bucket per Tier B2B (B1 / W8-D47)

## Status
Diterima (Accepted)

## Konteks
Pembatas laju cache per-jendela konvensional tidak memodelkan ledakan (*burst*) secara halus maupun memberikan waktu tunggu milidetik deterministik, serta berisiko memakai pembagian floating-point yang rentan galat presisi (`arch:scan` A3 / PR3).

## Keputusan
1. **Algoritma `IntegerTokenBucket` (B1) Milli-Token**: Setiap token bernilai $1.000$ milli-token bilangan bulat. Refill antara $t_{\text{last}}$ dan $t_{\text{now}}$ (dalam milidetik) dihitung sebagai:
   $$\text{tokens}' = \min(\text{capacity} \times 1.000,\ \text{tokens} + (t_{\text{now}} - t_{\text{last}}) \times \text{rate\_per\_sec})$$
2. **Penolakan Kemunduran Jam & Sisa Waktu Bulat**: Bila $t_{\text{now}} < t_{\text{last}}$, kelas melempar `DomainException` dengan kode `CLOCK_REGRESSION`. Saat saldo $< 1.000$, permintaan ditolak dengan `retry_after_ms` $= \lceil (1.000 - \text{tokens}') / \text{rate\_per\_sec} \rceil$ melalui pembagian bilangan bulat murni `intdiv((1000 - $tokens) + $rate - 1, $rate)`.
3. **Parameter Tier B2B (Label: `asumsi_simulasi`)**:
   - **Silver**: kapasitas `60`, laju `1` req/detik, kuota harian `10.000`.
   - **Gold**: kapasitas `300`, laju `5` req/detik, kuota harian `100.000`.
   - **Platinum**: kapasitas `1.200`, laju `20` req/detik, kuota harian `1.000.000`.
4. **Respons HTTP `429` & Rollup Per Jam**: Permintaan yang ditolak baik oleh bucket maupun kuota harian mengembalikan HTTP `429` RFC 7807 dengan header `Retry-After` (detik dibulatkan ke atas per RFC 9110) serta field `retry_after_ms`, dan memancarkan event `core.api.client_quota_exceeded.v1` ke Outbox. Command terjadwal `api:usage-rollup` merangkum pemakaian per jam ke `core_api_usage_rollups` secara idempoten.

## Konsekuensi
- Perhitungan burst dan `Retry-After` deterministik 100% bebas float dan dapat diverifikasi oleh `TokenBucketCertificateVerifier`.
