# ADR-0072: Metodologi Transfer Pricing Interkuartil OECD & PMK 172/2023

## Status
Diterima (Accepted)

## Konteks
Berdasarkan Peraturan Menteri Keuangan Nomor 172 Tahun 2023 (PMK 172/2023) dan Panduan Transfer Pricing OECD (OECD Transfer Pricing Guidelines 2022), transaksi dengan pihak yang memiliki hubungan istimewa wajib menerapkan Prinsip Kewajaran dan Kelaziman Usaha (*Arm's Length Principle* - ALP). Margin atau harga transaksi harus berada dalam rentang kewajaran (*arm's length range*) yang ditentukan menggunakan metode statistik rentang interkuartil (*interquartile range* / IQR).

Sistem lama hanya menguji batas statis menggunakan tipe data float dan pembagian kasar, tanpa sertifikasi matematis algoritma atau pembuatan dokumen kepatuhan (Local File / Master File).

## Keputusan
1. Mengimplementasikan algoritma bersertifikat `TransferPricingMarkupCalculator` (D4):
   - Mendukung 4 metode resmi:
     - `CUP`: *Comparable Uncontrolled Price* (perbandingan harga langsung).
     - `CPM`: *Cost Plus Method* (markup atas beban pokok produksi).
     - `RPM`: *Resale Price Method* (margin kotor penjualan kembali).
     - `TNMM`: *Transactional Net Margin Method* (margin laba operasi bersih).
   - Menghitung nilai statistik integer basis points (bps, 1% = 100 bps) untuk himpunan benchmark independen terurut $X = [x_1, x_2, \dots, x_n]$:
     - Posisi Kuartil Bawah ($Q_1$): Indeks $\lfloor 0.25 \times (n + 1) \rfloor$.
     - Posisi Median: Indeks $\lfloor 0.50 \times (n + 1) \rfloor$.
     - Posisi Kuartil Atas ($Q_3$): Indeks $\lfloor 0.75 \times (n + 1) \rfloor$.
   - Kriteria Kepatuhan: Margin aktual transaksi $M$ wajib berada pada interval tertutup $[Q_1, Q_3]$.
   - Penyesuaian (*True-up Adjustment*): Bila $M < Q_1$ atau $M > Q_3$, maka nilai penyesuaian dihitung hingga margin transaksi mencapai nilai Median.
2. Menyimpan data benchmark pembanding di `ic_tp_benchmarks` dan menghasilkan draf dokumentasi formal Local File / Master File di `ic_tp_documents`.
3. Menghasilkan sertifikat verifikasi bervalidasi hash SHA-256 (`TransferPricingMarkupCertificate`) pada setiap perhitungan margin.

## Konsekuensi
- Menghilangkan risiko koreksi fiskal material dan denda perpajakan atas sengketa harga transfer.
- Menjamin kepatuhan penuh terhadap standar PMK 172/2023 tanpa manipulasi manual.
- Mengeliminasi penggunaan tipe data float pada perhitungan margin pajak.
