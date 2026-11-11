# ADR-0045: Standarisasi Respons RESTful API v2, Paginasi Kursor & RFC 7807 Problem Details (W8-D45)

## Status
Diterima (Accepted)

## Konteks
Layanan API publik legacy pada Superweb generasi pertama mengalami inkonsistensi representasi data antar-modul:
1. Format pembungkus (*envelope*) tidak seragam: beberapa endpoint mengembalikan array mentah, beberapa menggunakan kunci `items`, dan lainnya `results`.
2. Paginasi berbasis offset (`page` dan `offset/limit`) mengalami anomali duplikasi data (*page drift*) ketika terjadi penyisipan baris baru di halaman awal selama pengguna menelusuri halaman berikutnya.
3. Struktur pesan kesalahan bervariasi antara teks biasa, array string Laravel default, dan JSON custom tanpa standarisasi kode galat yang dapat diparsing oleh mesin klien B2B secara terprediksi.

## Keputusan Arsitektur

### 1. Pembungkus Respons JSON Kanonik
Seluruh respons sukses pada rute `/api/v2/*` diwajibkan menggunakan pembungkus seragam:
```json
{
  "data": [ ... ],
  "meta": {
    "limit": 25,
    "has_more": true,
    "next_cursor": "eyJpZCI6MTAwLCJzb3J0X3ZhbCI6IlBPLTAwMTAwIn0=",
    "total_returned": 25
  }
}
```
Untuk entitas tunggal (`GET /api/v2/{resource}/{id}`):
```json
{
  "data": { ... }
}
```

### 2. Paginasi Kursor Deterministik Berbasis Keadaan
Paginasi offset ditiadakan sepenuhnya pada rute koleksi v2 dan digantikan oleh `CursorPaginationService`:
- Kursor dikodekan dalam string Base64 aman URL yang merangkum nilai pengurutan (`sort_val`) dan kunci primer penentu keunikan (`id`).
- Operator perbandingan kursor menggunakan ketaksamaan komposit:
  $$\text{WHERE } (\text{sort\_col} > \text{cursor\_val}) \lor (\text{sort\_col} = \text{cursor\_val} \land \text{id} > \text{cursor\_id})$$
- Paginasi meminta `limit + 1` baris ke basis data. Jika jumlah baris yang dikembalikan melebihi limit, `has_more = true` dan nilai baris ke-`limit` diekstrak sebagai dasar generasi `next_cursor`.
- Penyisipan data baru di halaman sebelumnya tidak akan menggeser jendela pembacaan halaman berikutnya.

### 3. Format Galat RFC 7807 Problem Details
Setiap respons kegagalan HTTP ($4xx$ dan $5xx$) wajib menggunakan header `Content-Type: application/problem+json` dengan skema terdefinisi:
```json
{
  "type": "https://superweb.example.com/probs/invalid-sort-parameter",
  "title": "Invalid Sort Parameter",
  "status": 422,
  "detail": "Column [salary] is not allowed for sorting on [purchase-orders].",
  "instance": "/api/v2/purchase-orders?sort=salary"
}
```
Parameter pengurutan (`sort`) dibatasi ketat pada kolom-kolom yang secara eksplisit dideklarasikan oleh `ApiResourceProvider::declaredSorts()`. Permintaan pengurutan pada kolom tak terdaftar langsung ditolak dengan status 422 RFC 7807 untuk mencegah serangan injeksi kolom SQL.

### 4. Header Deprikasi Versi Legacy
Bila suatu resource v2 menggantikan rute v1 legacy, header standar HTTP dipancarkan:
- `Deprecation: true`
- `Link: </api/v1/resource>; rel="predecessor-version"`

## Konsekuensi & Kepatuhan
- **Positif:** Integrasi mitra B2B memiliki kontrak data stabil dan bebas drift. Parsing galat pada SDK klien menjadi generik dan terpusat.
- **Negatif:** Klien tidak dapat melompat ke sembarang nomor halaman secara acak (harus menelusuri secara sekuensial via kursor).
- **Verifikasi:** Diuji secara otomatis pada `ApiV2LifecycleTest::test_55_2_api_v2_envelope_cursor_rfc7807_idempotency_and_b1_rate_limit`.
