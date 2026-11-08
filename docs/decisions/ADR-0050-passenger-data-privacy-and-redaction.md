# ADR-0050: Perlindungan Data Pribadi Penumpang dan Redaksi Spasial (G7-D50)

## Status
Diterima (Accepted) — 2026-11-08

## Konteks
Kepatuhan terhadap UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi (UU PDP) dan standar privasi internasional mewajibkan perlindungan ketat atas Personally Identifiable Information (PII) penumpang aviasi serta titik koordinat tambak akuakultur yang bersifat rahasia operasional.

## Keputusan
1. **Enkripsi & Blind Index:** Nama lengkap, nomor paspor/NIK, dan kontak penumpang disimpan dalam kolom terenkripsi (`pii_name_encrypted`, `pii_contact_encrypted`). Pencarian data oleh petugas dilakukan melalui `pii_blind_index` (HMAC ber-salt rahasia aplikasi) tanpa mendekripsi seluruh basis data.
2. **Minimisasi Log & Manifest:** Nomor paspor, tanggal lahir, dan rekam medis penumpang dilarang ditulis ke dalam log aplikasi atau log audit. Dokumen manifest ke otoritas bandara hanya menyertakan field wajib yang dipersyaratkan hukum.
3. **Redaksi Spasial Publik:** Koordinat presisi kapal dan tambak akuakultur tidak ditampilkan pada antarmuka publik. Sistem menggunakan Geohash resolusi 5 (akurasi ~4,9 km) ditambah komitmen kriptografis `sha256("<salt>|<lat>|<lon>")` untuk membuktikan kebenaran lokasi tanpa membocorkannya.

## Konsekuensi
Menghilangkan risiko kebocoran data pribadi penumpang dan melindungi aset lokasi tambak marikultur.
