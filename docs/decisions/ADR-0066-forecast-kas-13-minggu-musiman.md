# ADR-0066: Peramalan Arus Kas 13 Minggu Berbasis Dekomposisi Musiman

## Status
DITERIMA (2026-11-11) — Keputusan W8-D66 (Gelombang 8, Prompt P5).

## Konteks
Peramalan likuiditas arus kas jangka pendek (1 kuartal / 13 minggu) merupakan instrumen krusial manajemen risiko solvabilitas perbendaharaan. Pendekatan peramalan statis atau rata-rata bergerak sederhana gagal menangkap fluktuasi pola musiman mingguan (misal beban payroll akhir bulan atau pelunasan faktur vendor pertengahan bulan).

## Keputusan
1. **Algoritma Peramalan D3 (`SeasonalCashForecaster`)**: Menggunakan model dekomposisi musiman multiplikatif dengan periode musiman $p = 4$ minggu (1 bulan kalender).
2. **Deseasonalisasi & Regresi OLS Rasional**: Data historis (minimal $2p = 8$ minggu) dinormalisasi dengan indeks musiman permil, kemudian dihitung garis tren liniernya menggunakan Ordinary Least Squares (OLS) rasional murni tanpa float.
3. **Proyeksi Skenario Ganda**: Mendukung skenario `base`, `downside` (pengali stres likuiditas), dan `upside` berbasis parameter berversi bertanda `asumsi_simulasi`.
4. **Evaluasi Akurasi**: Setiap perkiraan disimpan berversi dan dievaluasi terhadap arus kas aktual menggunakan metrik Symmetric Mean Absolute Percentage Error (SMAPE) dalam satuan permil integer.

## Konsekuensi
- Manajemen perbendaharaan memperoleh visibilitas arus kas 13 minggu yang akurat dan berbasis statistik tanpa bias komputasi float.

