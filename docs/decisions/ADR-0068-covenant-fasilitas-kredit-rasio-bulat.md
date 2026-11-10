# ADR-0068: Pemantauan Batasan Covenant Fasilitas Kredit Berbasis Rasio Integer Permil

## Status
DITERIMA (2026-11-11) — Keputusan W8-D68 (Gelombang 8, Prompt P5).

## Konteks
Perjanjian kredit perbankan korporat mewajibkan peminjam mematuhi batasan rasio keuangan (*financial covenants*), seperti rasio utang terhadap ekuitas (*Debt-to-Equity Ratio* / DER) dan rasio cakupan bunga (*Interest Coverage Ratio* / ICR). Penggunaan float pada pengecekan rasio berisiko menimbulkan perdebatan hukum saat terjadi pelanggaran marjinal dan melanggar aturan arsitektur A3/FloatMoneyRule.

## Keputusan
1. **Rasio Keuangan Sebagai Integer Permil**: Seluruh batas covenant dan hasil perhitungan rasio solvabilitas disimpan dan dihitung dalam satuan integer per seribu (permille, ‰) tanpa float:
   Contoh: Max DER 2,50x = 2500‰; Min ICR 3,00x = 3000‰.
2. **Pengecekan Prospektif Saat Penarikan**: Setiap pengajuan penarikan fasilitas bank (`drawFacility`) menghitung rasio DER pasca-penarikan dari saldo riil buku besar.
3. **Hard-Stop Penolakan Covenant**: Jika penarikan menyebabkan rasio melanggar covenant bank, sistem menolak transaksi secara mutlak dengan exception `COVENANT_BREACH`, kecuali terdapat persetujuan *covenant waiver* resmi dari bank pemberi pinjaman.
4. **LoanExposureProvider Contract**: Integrasi modul pinjaman (Finance) via interface `Modules\Finance\Contracts\LoanExposureProvider` untuk membaca eksposur kewajiban grup.

## Konsekuensi
- Memastikan eliminasi total tipe float pada penegakan batas kredit dan melindungi korporasi dari wanprestasi pinjaman sindikasi bank.

