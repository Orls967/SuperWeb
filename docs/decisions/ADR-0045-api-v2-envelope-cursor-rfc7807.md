# ADR-0045: Standarisasi API v2 (Envelope, Kursor Keyset, RFC 7807 & Deprecation) (W8-D45)

## Status
Diterima (Accepted)

## Konteks
Endpoint generasi pertama (`/api/v1/*`, `/api/v3/*`) memiliki format paginasi dan struktur pesan kesalahan yang heterogen antar modul, serta tidak memiliki mekanisme penemuan kontrak terpadu berbasis provider lintas modul. Selain itu, perubahan offset pagination rentan bergeser ketika baris baru masuk di tengah penarikan data mitra B2B.

## Keputusan
1. **Prefiks Versi & Envelope Baku**: Seluruh endpoint v2 berada di bawah `/api/v2/*` yang didaftarkan melalui kontrak `Modules\Core\Contracts\ApiResourceProvider`. Setiap respons koleksi memakai amplop JSON baku `{"data": [...], "meta": {"next_cursor": ..., "limit": ..., "sort": ..., "deprecated_v1": ...}}` dengan limit default `50` dan batas keras maksimum `200`.
2. **Kursor Stabil Keyset (Base64URL)**: Kursor menyandikan `{"k": <nilai_kunci_urut>, "id": <id_rekaman>}` dalam Base64URL tanpa padding, disertai `ORDER BY` eksplisit `(sort_col, id)` sehingga penambahan data baru tidak menggeser halaman berikutnya.
3. **Problem Details RFC 7807 / RFC 9457**: Seluruh galat (401, 403, 404, 409, 422, 429) mengembalikan `Content-Type: application/problem+json` berisi `type`, `title`, `status`, `detail`, `instance`, dan opsional `errors[]`.
4. **Filter & Sort Terdeklarasi + Deprecation Header**: Parameter `filter` dan `sort` ditolak (`422` Problem) bila menyebut kolom di luar daftar deklarasi `ApiResourceDefinition`. Endpoint v2 untuk resource yang memiliki padanan `/api/v1/*` aktif menyertakan header `Deprecation: true` dan tautan `Link: </api/v2/...>; rel="successor-version"`.
5. **OpenAPI 3.1 Otomatis Tanpa Mengubah v1**: Rute resource v2 didaftarkan sebagai rute eksplisit per resource saat aplikasi boot selesai sehingga `OpenApiGenerateCommand` otomatis mendokumentasikannya di `docs/openapi-v3.json` tanpa mengubah entri v1.

## Konsekuensi
- Mitra memperoleh kontrak paginasi deterministik serta galat standar yang konsisten di delapan modul bisnis.
- GraphQL ditangguhkan di Register Minus sesuai pembatasan IZIN-2 (tanpa dependensi baru).
