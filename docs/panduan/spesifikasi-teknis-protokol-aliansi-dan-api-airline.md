# Spesifikasi Teknis: Protokol Aliansi & Antarmuka API Mitra Maskapai — Fase 112

## 1. Arsitektur Komunikasi Aliansi
Platform Global Travel Pass berkomunikasi dengan mitra maskapai penerbangan (Airline Partners) dan penyedia transportasi kereta api melalui arsitektur RESTful API yang aman dengan protokol TLS 1.3, autentikasi mutual mTLS, dan verifikasi payload menggunakan HMAC-SHA256.

```
+------------------------+             +--------------------------+             +-------------------------+
|   Travel Pass System   |             |  Partner Gateway (Proxy) |             |  Airline Partner Host   |
| (Modules\Hotel\Domain) |             | (Saga Orchestration Bus) |             | (External System API)   |
+------------------------+             +--------------------------+             +-------------------------+
            |                                       |                                        |
            |-- 1. Panggil PartnerPointConverter -->|                                        |
            |   (Hitung Miles & Fee)                |                                        |
            |                                       |                                        |
            |-- 2. Saga: Hold Poin & Ledger Debit ->|                                        |
            |                                       |                                        |
            |                                       |-- 3. POST /v1/rewards/credit --------->|
            |                                       |      (Idempotency-Key: HTL-TX-XXXX)    |
            |                                       |                                        |
            |                                       |<-- 4. 200 OK (Partner TX ID) ----------|
            |                                       |   [atau 504 Gateway Timeout]           |
            |                                       |                                        |
            |<-- 5A. Saga Commit (Settlement OK) ---|                                        |
            |    [atau 5B. Saga Compensate Rollback]|                                        |
            |                                       |                                        |
```

---

## 2. Definisi Endpoint & Payload Transaksi

### 2.1 Panggilan Transfer Poin ke Mitra
- **Metode HTTP:** `POST`
- **URI:** `/api/v1/hotel/alliance/partner-transfer`
- **Header:**
  - `Content-Type: application/json`
  - `X-Idempotency-Key: HTL-ALN-YYYYMMDD-UUID`
  - `X-Signature-SHA256: 6a8f89bc...`

#### Contoh Payload Permintaan (Request):
```json
{
  "travel_pass_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
  "partner_code": "GARUDA_AIR",
  "member_partner_account": "GA-988273612",
  "points_to_transfer": 10000,
  "client_timestamp": "2026-11-08T06:30:00Z"
}
```

#### Contoh Payload Respons Berhasil (Response 200 OK):
```json
{
  "status": "SUCCESS",
  "transaction_id": "ALN-TX-882910-GA",
  "points_deducted": 10000,
  "partner_units_credited": 1000,
  "transfer_fee_idr": 35000,
  "net_payable_to_partner_idr": 965000,
  "partner_confirmation_code": "GA-CONF-202611-9921",
  "certificate_hash": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
}
```

---

## 3. Penanganan Kesalahan & Skenario Rollback
1. **Kegagalan Validasi Saldo (Saldo Poin Tidak Cukup):**
   - HTTP Status: `422 Unprocessable Entity`
   - Kode Kesalahan: `INSUFFICIENT_POINTS_BALANCE`
   - Tindakan: Permintaan ditolak seketika tanpa menyentuh buku besar atau API mitra.
2. **Mitra Menolak Akun Tamu (Nomor Anggota Mitra Tidak Ditemukan):**
   - HTTP Status: `400 Bad Request`
   - Kode Kesalahan: `PARTNER_MEMBER_NOT_FOUND`
   - Tindakan: Saga membatalkan hold poin tamu dan mengembalikan saldo.
3. **Koneksi Terputus / Timeout Jaringan (Network Timeout):**
   - HTTP Status: `504 Gateway Timeout`
   - Kode Kesalahan: `PARTNER_TIMEOUT_COMPENSATING`
   - Tindakan: Saga secara otomatis mengeksekusi kompensasi rollback: mengkredit kembali saldo tamu dan mereverse jurnal debit liabilitas poin.

---

## 4. Keamanan & Kepatuhan Data (PDP/GDPR)
- Nomor akun keanggotaan mitra dan nama lengkap tamu dikirimkan dalam saluran terenkripsi end-to-end.
- Token autentikasi API mitra diperbarui secara otomatis setiap 3.600 detik menggunakan mekanisme OAuth2 Client Credentials dengan rotasi kunci simetris.
- Log transaksi masking: 6 digit tengah dari nomor kartu keanggotaan mitra disamarkan dengan tanda bintang pada seluruh berkas log aplikasi (misal `GA-98****12`).

