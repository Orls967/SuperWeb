# Panduan Integrasi Pengembang B2B: Platform API v2 Superweb (Fase 55)

**Target Pembaca:** Rekanan B2B, Pengembang Integrasi Pihak Ketiga, Vendor ERP & Logistik  
**Versi API:** 2.0.0  
**Tanggal:** 2026-11-12  

---

## 1. Pendahuluan & Gambaran Umum

Platform API v2 Superweb dirancang sebagai antarmuka pertukaran data B2B berperforma tinggi dan berstandar enterprise. Seluruh komunikasi API beroperasi melalui protokol HTTPS dengan format muatan JSON, format pertukaran berkas EDIFACT/X12, serta streaming impor massal.

### Prinsip Utama Integrasi:
- **RESTful Enveloping:** Setiap respons sukses memiliki struktur pembungkus standar `{ data, meta }`.
- **RFC 7807 Problem Details:** Setiap galat HTTP memiliki rincian terstruktur pada header `Content-Type: application/problem+json`.
- **Paginasi Kursor Deterministik:** Penjelajahan data menggunakan kursor aman URL yang kebal pergeseran data.
- **Idempotensi Persisten 24 Jam:** Rute mutasi mewajibkan header `Idempotency-Key` untuk mencegah duplikasi saat retry jaringan.
- **Throttling Integer Token Bucket:** Pengaturan kuota transparan dengan header `Retry-After`.

---

## 2. Autentikasi & Otorisasi

API v2 mendukung dua metode autentikasi:

### A. Kunci API B2B (*Live Server-to-Server Key*)
Gunakan token bearer berformat `sk_live_{random}` atau `sw_live_{random}` pada header HTTP `Authorization`:
```http
Authorization: Bearer sw_live_gn9UmzEzAbCdEfGhIjKlMnOpQrStUvWx
```

### B. Kemampuan Kunci (*Abilities*)
Kunci API dibatasi oleh daftar izin granular:
- `resource:read`: Membaca data sumber daya (`GET /api/v2/{resource}`).
- `resource:action`: Mengeksekusi tindakan mutasi domain (`POST /api/v2/{resource}/actions/{action}`).
- `edi:write`: Mengirim berkas pertukaran EDI (`POST /api/v2/edi/interchanges`).
- `bulk:import`: Mengunggah berkas impor massal (`POST /api/v2/bulk/imports`).
- `bulk:export`: Meminta pengunduhan berkas ZIP ekspor (`POST /api/v2/bulk/exports`).

---

## 3. Format Respons & Paginasi Kursor

### A. Contoh Permintaan Koleksi
```http
GET /api/v2/purchase-orders?limit=2&sort=number HTTP/1.1
Host: api.superweb.example.com
Authorization: Bearer sw_live_gn9UmzEzAbCdEfGhIjKlMnOpQrStUvWx
X-Tenant-ID: tenant-a
```

### B. Contoh Respons Sukses
```json
{
  "data": [
    {
      "id": "01a1cbaf-b408-71db-8f51-826478969733",
      "number": "PO-0001",
      "title": "Initial PO 1",
      "status": "approved",
      "total_amount": 50000,
      "currency": "IDR",
      "created_at": "2026-11-12T01:30:00+08:00"
    },
    {
      "id": "01a1cbaf-b408-71db-8f51-826478969734",
      "number": "PO-0002",
      "title": "Initial PO 2",
      "status": "approved",
      "total_amount": 100000,
      "currency": "IDR",
      "created_at": "2026-11-12T01:30:00+08:00"
    }
  ],
  "meta": {
    "limit": 2,
    "has_more": true,
    "next_cursor": "eyJpZCI6IjAxYTFjYmFmLWI0MDgtNzFkYi04ZjUxLTgyNjQ3ODk2OTczNCIsInZhbCI6IlBPLTAwMDIifQ==",
    "total_returned": 2
  }
}
```

### C. Mengambil Halaman Berikutnya
Sertakan nilai `next_cursor` pada parameter kueri `cursor`:
```http
GET /api/v2/purchase-orders?limit=2&sort=number&cursor=eyJpZCI6IjAxYTFjYmFmLWI0MDgtNzFkYi04ZjUxLTgyNjQ3ODk2OTczNCIsInZhbCI6IlBPLTAwMDIifQ== HTTP/1.1
```

---

## 4. Mutasi Idempoten & Pencegahan Duplikasi

Setiap panggilan mutasi (`POST`, `PUT`, `PATCH`, `DELETE`) wajib menyertakan header `Idempotency-Key`:
```http
POST /api/v2/purchase-orders/actions/acknowledge HTTP/1.1
Host: api.superweb.example.com
Authorization: Bearer sw_live_gn9UmzEzAbCdEfGhIjKlMnOpQrStUvWx
Idempotency-Key: b2b-order-ack-2026-0001
Content-Type: application/json

{
  "po_id": "01a1cbaf-b408-71db-8f51-826478969733",
  "note": "Pesanan diterima dan siap dikirim"
}
```

### Perilaku Saat Retry Jaringan:
- **Pengulangan Pertama (Sukses Awal):** Server mengeksekusi mutasi dan menyimpan respons di cache 24 jam. HTTP Status `200 OK`.
- **Pengulangan Kedua (Retry Sah):** Bila klien mengulang request identik dengan `Idempotency-Key` sama, server mengembalikan respons asli tanpa menjalankan ulang mutasi di database. Header respons membawa `X-Idempotent-Replay: true`.
- **Pengulangan Berkonflik:** Bila `Idempotency-Key` sama dikirim dengan payload yang telah diubah (`note: 'BERBEDA'`), server menolak permintaan dengan status HTTP `409 Conflict`.

---

## 5. Penerimaan & Validasi Webhook

Superweb mengirimkan webhook saat terjadi event bisnis penting (misal `order.created.v1`, `shipment.dispatched.v1`).

### A. Verifikasi Tanda Tangan pada Sisi Penerima (Contoh PHP)
```php
<?php
$payload = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
$secretKey = getenv('SUPERWEB_WEBHOOK_SECRET');

// 1. Ekstrak timestamp dan tanda tangan v1
$parts = explode(',', $signatureHeader);
$timestamp = null;
$signatures = [];
foreach ($parts as $part) {
    if (str_starts_with($part, 't=')) {
        $timestamp = (int) substr($part, 2);
    } elseif (str_starts_with($part, 'v1=')) {
        $signatures[] = substr($part, 3);
    }
}

// 2. Cegah Replay Attack (toleransi 300 detik)
if (abs(time() - $timestamp) > 300) {
    http_response_code(400);
    exit('Timestamp expired');
}

// 3. Hitung HMAC-SHA256
$signedInput = "{$timestamp}.{$payload}";
$expectedSignature = hash_hmac('sha256', $signedInput, $secretKey);

// 4. Verifikasi konstan-waktu
$valid = false;
foreach ($signatures as $sig) {
    if (hash_equals($expectedSignature, strtolower($sig))) {
        $valid = true;
        break;
    }
}

if (!$valid) {
    http_response_code(401);
    exit('Invalid signature');
}

// Proses event bisnis secara asinkron
http_response_code(200);
echo json_encode(['status' => 'acknowledged']);
```

---

## 6. Integrasi Dokumen EDIFACT & X12

### A. Mengirimkan Interchanges Masuk
Kirimkan raw string EDI melalui endpoint `/api/v2/edi/interchanges`:
```http
POST /api/v2/edi/interchanges HTTP/1.1
Host: api.superweb.example.com
Authorization: Bearer sw_live_gn9UmzEzAbCdEfGhIjKlMnOpQrStUvWx
Idempotency-Key: edi-orders-ic-9001
Content-Type: application/json

{
  "raw_payload": "UNB+UNOC:3+SENDER01:ZZ+SUPERWEB:ZZ+261112:1200+IC9001'UNH+1+ORDERS:D:96A:UN'BGM+220+PO99001+9'DTM+137:20261112:102'NAD+BY+BUYER01::9'LIN+1++ITEM-A:SRV'QTY+21:10'UNS+S'UNT+8+1'UNZ+1+IC9001'"
}
```

### B. Struktur Respons & Fungsional Acknowledgment (CONTRL / 997)
Respons mengembalikan status siklus hidup dokumen dan payload pengakuan fungsional:
```json
{
  "id": 142,
  "control_ref": "IC9001",
  "status": "posted",
  "handler_module": "Distribution",
  "target_reference": "01a1cbaf-b408-71db-8f51-826478969733",
  "contrl_payload": "UNB+UNOC:3+SUPERWEB:ZZ+SENDER01:ZZ+261112:1200+ACKIC9001'UNH+1+CONTRL:D:96A:UN'UCI+IC9001+SENDER01:ZZ+SUPERWEB+7'UNT+3+1'UNZ+1+ACKIC9001'"
}
```
Bila terjadi galat sintaks (misal `UNT_COUNT_MISMATCH`), status akan bernilai `rejected` dan kode aksi acknowledgment `UCI` bernilai `4` (ditolak).

---

## 7. Batasan Throttling & Penanganan Kode Status 429

Bila kuota burst token klien habis, server mengembalikan status HTTP `429 Too Many Requests`:
```http
HTTP/1.1 429 Too Many Requests
Content-Type: application/problem+json
Retry-After: 1
X-RateLimit-Remaining: 0

{
  "type": "https://superweb.example.com/probs/rate-limit-exceeded",
  "title": "Too Many Requests",
  "status": 429,
  "detail": "Rate limit exceeded. Please retry after 1 seconds.",
  "retry_after_ms": 500
}
```
Aplikasi klien wajib menghentikan sementara pengiriman request selama durasi yang ditentukan pada header `Retry-After` atau field `retry_after_ms` sebelum mencoba kembali (*exponential backoff*).

