# ADR-0048: Penandatanganan Webhook HMAC, Rotasi Kunci Dual-Secret & Integrasi Event Spine (W8-D48 / B2)

## Status
Diterima (Accepted)

## Konteks
Pemberitahuan perubahan status domain ke sistem mitra eksternal (misal: pesanan dibuat, pengiriman diperbarui, faktur diterbitkan) membutuhkan jaminan otentisitas, integritas muatan, serta ketahanan terhadap kegagalan endpoint mitra tanpa memblokir proses bisnis inti Superweb.

Tantangan operasional yang dihadapi:
1. **Downtime Rotasi Rahasia:** Bila rahasia (*secret key*) webhook dirotasi secara langsung, pesan yang dikirim sebelum mitra memperbarui rahasia di sistemnya akan ditolak (HMAC mismatch).
2. **Serangan Replay:** Penyerang dapat merekam muatan webhook yang valid lalu mengirimkannya ulang ke endpoint mitra berulang kali.
3. **Kegagalan Endpoint Mitra:** Bila server mitra down, retry tanpa henti dapat menghabiskan kuota antrean dan membebani worker internal.

## Keputusan Arsitektur

### 1. Format Header Tanda Tangan Kanonik `X-Signature`
Setiap permintaan keluar webhook membawa header `X-Signature` yang merangkum stempel waktu Unix dan satu atau lebih tanda tangan HMAC:
```http
X-Signature: t=1700000000,v1=0d74ebc95953607011bb60f2d3651900e64d5d82aeb6b91d62eb922cf3ac68eb
```
Input bertanda tangan dikonstruksi secara kanonik:
$$\text{signed\_input} = \text{timestamp} \mathbin{\Vert} \text{"."} \mathbin{\Vert} \text{raw\_payload\_json}$$
$$\text{v1} = \text{hash\_hmac}('sha256', \text{signed\_input}, \text{secret})$$

### 2. Rotasi Kunci Aman Tanpa Downtime (*Dual-Secret Grace Period*)
Saat rahasia webhook dirotasi melalui `WebhookSubscriptionService`:
- Rahasia baru dienkripsi dan disimpan sebagai `secret_encrypted`.
- Rahasia sebelumnya dipindahkan ke `previous_secret_encrypted` dengan stempel waktu `previous_secret_expires_at = now() + grace_window` (maksimal 7 hari).
- Selama masa tenggang, `WebhookSignatureVerifier::sign` membentuk header ganda:
  ```http
  X-Signature: t=1700000000,v1={hmac_rahasia_lama},v1={hmac_rahasia_baru}
  ```
- Mitra dapat memverifikasi salah satu dari tanda tangan `v1` yang cocok.

### 3. Pencegahan Replay & Komparasi Konstan-Waktu
Penerima/verifikator memvalidasi stempel waktu `t`:
$$\Delta t = |\text{now\_unix} - t|$$
Bila $\Delta t > 300\text{ detik}$, verifikasi ditolak dengan status `EXPIRED`.
Pencocokan hash HMAC wajib menggunakan `hash_equals` konstan-waktu guna mengeliminasi kerentanan *timing side-channel attacks*.

### 4. Integrasi Event Spine Outbox & Circuit Breaker
- Pengiriman webhook tidak memanggil HTTP secara sinkron dalam transaksi database, melainkan menerbitkan event ke `OutboxBusInterface` milik W8-P3.
- Bila transmisi gagal berturut-turut $\ge 5$ kali (`consecutive_failures >= 5`), pemutus arus (`circuit_state`) beralih ke `open` dan pengiriman ke URL tersebut ditangguhkan selama 30 menit.
- Pesan yang gagal dialihkan ke Dead-Letter Queue (`core_event_deliveries` status `dead_lettered`) untuk inspeksi manual.

## Konsekuensi & Kepatuhan
- **Positif:** Mengeliminasi downtime integrasi webhook saat audit keamanan menuntut rotasi berkala, kebal replay attack, dan mengamankan antrean sistem.
- **Negatif:** Muatan header HTTP bertambah sedikit saat masa tenggang aktif.
- **Verifikasi:** Diuji pada `ApiV2AlgorithmsTest` dengan vektor prompt resmi dan pengujian sabotase sertifikat SHA-256.
