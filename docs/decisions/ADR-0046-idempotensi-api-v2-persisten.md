# ADR-0046: Idempotensi Persisten API v2 Berbasis Hash Permintaan Selama 24 Jam (W8-D46)

## Status
Diterima (Accepted)

## Konteks
Pada komunikasi antar-sistem B2B, fluktuasi jaringan seluler atau kegagalan koneksi TCP sering menyebabkan timeout di sisi klien setelah server berhasil mengeksekusi sebagian mutasi. Klien otomatis melakukan retry atas permintaan tersebut.

Kerangka legacy memiliki kelemahan:
1. Menggunakan cache memori sementara (Redis/Array) tanpa persistensi basis data, sehingga restart worker atau rotasi instance menghilangkan status idempotensi.
2. Tidak memeriksa apakah muatan payload pada retry identik dengan request awal atau telah dimanipulasi dengan kunci idempotensi yang sama.
3. Ketiadaan penanganan konkurensi memungkinkan dua request bersamaan dengan kunci identik dieksekusi dobel sebelum respon pertama selesai ditulis.

## Keputusan Arsitektur

### 1. Header `Idempotency-Key` Wajib pada Seluruh Rute Mutasi
Semua permintaan HTTP bermetode mutasi state (`POST`, `PUT`, `PATCH`, `DELETE`) pada rute `/api/v2/*` diwajibkan menyertakan header `Idempotency-Key` non-kosong ($\le 255\text{ karakter}$). Ketiadaan header ini memicu penolakan seketika dengan status HTTP `422 Unprocessable Content` berformat RFC 7807.

### 2. Penghitungan Hash Kanonik Permintaan
Untuk membedakan pengulangan transmisi sah vs manipulasi muatan:
$$\text{canonical\_payload} = \text{ksort}(\text{json\_decode}(\text{request\_body}))$$
$$\text{request\_hash} = \text{hash}(\text{'sha256'}, \text{canonical\_payload})$$
Hash SHA-256 ini dihitung sebelum middleware meneruskan eksekusi ke lapisan controller domain.

### 3. Tabel Persistensi Fakta Idempotensi
Catatan disimpan pada tabel `core_api_idempotency_records` dengan skema:
- `client_code` (string): Identitas akun klien B2B pemanggil.
- `route_name` (string): Pengenal rute endpoint target.
- `http_method` (string): Kata kerja HTTP permintaan.
- `idempotency_key` (string): Nilai kunci unik yang disediakan klien.
- `request_hash` (string, 64 hex): Ringkasan kriptografis badan permintaan.
- `response_status` (int): Kode status HTTP hasil eksekusi.
- `response_headers` (json): Header respons yang relevan.
- `response_body` (json): Muatan data lengkap yang dikembalikan oleh handler domain.
- `expires_at` (timestamp): Kedaluwarsa tepat 24 jam menurut `ClockInterface`.
- Indeks unik komposit: `(client_code, route_name, http_method, idempotency_key)`.

### 4. Semantik Penanganan: Replay vs Conflict
1. **Kasus Kunci Baru:** Permintaan diproses oleh domain handler, respons disimpan ke tabel persistensi, dan dikembalikan ke klien dengan status `200/201`.
2. **Kasus Replay Sah (Kunci Sama + Hash Sama):** Permintaan tidak dieksekusi ulang ke domain handler downstream. Respons asli yang tersimpan diambil dan dikembalikan ke klien secara instan dengan penambahan header:
   ```http
   X-Idempotent-Replay: true
   ```
3. **Kasus Konflik (Kunci Sama + Hash Berbeda):** Permintaan ditolak dengan status HTTP `409 Conflict` berformat RFC 7807:
   ```json
   {
     "type": "https://superweb.example.com/probs/idempotency-conflict",
     "title": "Idempotency Conflict",
     "status": 409,
     "detail": "Provided Idempotency-Key is already bound to a different request payload."
   }
   ```

## Konsekuensi & Kepatuhan
- **Positif:** Mengeliminasi transaksi ganda, duplikasi draft pesanan pembelian, atau pembayaran berulang pada jaringan tidak stabil.
- **Negatif:** Penambahan 1 kueri baca dan 1 kueri tulis basis data per mutasi (overhead terukur $< 3\text{ ms}$).
- **Verifikasi:** Diuji pada `ApiV2LifecycleTest` skenario `test_55_2_api_v2_envelope_cursor_rfc7807_idempotency_and_b1_rate_limit`.
