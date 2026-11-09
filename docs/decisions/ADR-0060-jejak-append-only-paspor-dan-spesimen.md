# ADR-0060: Jejak Audit Kriptografis Paspor Pasien dan Rantai Kustodi Medis

## Konteks & Masalah
Rekam medis pasien, sampel laboratorium, dan manifest limbah B3 rentan terhadap sengketa hukum atau tuduhan malpraktik jika riwayat perubahannya dapat dimodifikasi di basis data tanpa jejak.

## Keputusan Arsitektur
1. **Penerapan CanonicalHashChain**: Paspor kesehatan pasien (`hsp_patient_passport_events`), rantai serah-terima spesimen diagnostik, dan manifest limbah medis B3 menerapkan struktur hash chain berbasis SHA-256.
2. **Koreksi sebagai Peristiwa Baru**: Tidak ada operasi update kolom pada baris kejadian. Setiap koreksi diagnosa atau pembatalan terapi dicatat sebagai event koreksi bertanda tangan dengan menghubungkan ke `prev_hash`.
3. **Pemberian Tanda Tangan Digital**: Setiap mata rantai paspor memuat hash payload, ID dokter/petugas, waktu UTC, dan signature verifikasi.

## Konsekuensi
- Integritas data dapat dibuktikan secara matematis di pengadilan dan audit akreditasi.
