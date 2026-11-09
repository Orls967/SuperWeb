# ADR-0056: Alokasi Ranjang Anti-Bentrok dan Kebijakan Peningkatan Kelas

## Konteks & Masalah
Konkurensi admisi di rumah sakit besar kerap memicu terjadinya alokasi tempat tidur ganda (double-booking). Selain itu, keterbatasan tempat tidur pada kelas hak pasien sering memaksa rumah sakit memindahkan pasien ke kelas yang lebih tinggi.

## Keputusan Arsitektur
1. **Pencegahan Alokasi Ganda**: Setiap transaksi pengikatan ranjang wajib mengunci baris ranjang melalui `lockForUpdate()` dalam transaksi basis data. Satu ranjang hanya boleh menampung tepat satu pasien rawat inap aktif.
2. **Kebijakan Non-Substitusi**: Ranjang `ICU` dan `ISOLATION` bersifat non-substitutable (pasien harus mengantre hingga ranjang bebas).
3. **Peningkatan Kelas (Upgrade)**: Jika kelas hak (misal: Kelas 3) penuh, sistem diizinkan menempatkan pasien pada Kelas 2 atau Kelas 1 dengan mencatat `is_upgraded = true`.
4. **Proteksi Finansial Pasien**: Selisih biaya akibat peningkatan kelas karena alasan operasional rumah sakit ditanggung oleh pihak rumah sakit (`upgrade_absorbed_by_hospital = true`). Pasien hanya ditagih sesuai tarif haknya.

## Konsekuensi
- Mencegah insiden operasional pasien tiba di kamar yang sudah terisi.
- Tagihan pasien tetap adil dan transparan.
