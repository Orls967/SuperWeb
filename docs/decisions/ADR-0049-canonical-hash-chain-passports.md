# ADR-0049: Paspor Aset Append-Only dan Rantai Hash Kanonik (G7-D49)

## Status
Diterima (Accepted) — 2026-11-08

## Konteks
Integritas riwayat perawatan pesawat, paspor digital kapal laut, dan garis keturunan (*lineage*) hasil panen akuakultur memerlukan bukti anti-pemalsuan dan audit jejak yang tidak dapat dimanipulasi secara retroaktif.

## Keputusan
1. **Append-Only Immutable Records:** Riwayat log pemeliharaan, alih kepemilikan kapal, dan tahapan pemrosesan lot ikan menggunakan struktur `Modules\Shared\Domain\Numerics\CanonicalHashChain`. Setiap peristiwa baru memuat hash SHA-256 dari peristiwa sebelumnya.
2. **Koreksi sebagai Peristiwa Baru:** Tidak ada operasi SQL `UPDATE` atau `DELETE` pada baris fakta historis. Segala bentuk ralat atau penyesuaian dicatat sebagai peristiwa koreksi baru dengan tipe `REVERSAL` atau `AMENDMENT`.
3. **Verifikasi Publik Teredaksi:** Halaman penelusuran publik (seperti paspor kapal digital atau sertifikat asal udang) memverifikasi integritas hash-chain secara menyeluruh, namun menyembunyikan data sensitif komersial dan koordinat GPS presisi melalui teknik reduksi dan komitmen salt.

## Konsekuensi
Menjamin bukti kepatuhan audit regulasi maritim dan aviasi dapat diverifikasi secara kriptografis tanpa mengekspos rahasia dagang.

