# ADR-0076: Translasi Valas Laporan Keuangan Anak & FCTR Konsolidasi

## Status
Diterima (Accepted)

## Konteks
Konglomerasi memiliki entitas anak atau perusahaan patungan di luar negeri yang menyelenggarakan pembukuan dalam mata uang fungsional asing (mis. USD, SGD, MYR). Untuk menyajikan laporan keuangan konsolidasi dalam mata uang pelaporan IDR, laporan keuangan entitas asing harus ditranslasikan sesuai ketentuan PSAK 10 (IAS 21) tentang Efek Perubahan Kurs Valuta Asing:
1. Pos-pos Aset dan Liabilitas di neraca ditranslasikan menggunakan kurs penutupan (*closing rate*) pada tanggal neraca.
2. Pos-pos Pendapatan dan Beban di laporan laba rugi ditranslasikan menggunakan kurs rata-rata (*average rate*) sepanjang periode pelaporan.
3. Selisih matematis yang timbul dari perbedaan kurs neraca vs kurs rata-rata laba rugi tidak boleh dimasukkan ke dalam laba rugi berjalan, melainkan wajib dicatat dalam pos terpisah Ekuitas, yaitu Cadangan Selisih Kurs Translasi (*Foreign Currency Translation Reserve* - FCTR) atau Penghasilan Komprehensif Lain (*Other Comprehensive Income* - OCI).

## Keputusan
1. Mengintegrasikan layanan kurs berversi dari modul Treasury (`Modules\Treasury\Contracts\ExchangeRateLookup`) untuk memperoleh kurs penutupan dan kurs rata-rata secara deterministik.
2. Menghitung nilai translasi:
   - Aset Neto Akhir pada Kurs Penutupan: $E_1 = \text{NetAssets}_{\text{foreign}} \times \text{ClosingRate}$
   - Aset Neto Awal pada Kurs Pembukaan: $E_0 = \text{NetAssets}_{\text{foreign, begin}} \times \text{OpeningRate}$
   - Laba Bersih Periode pada Kurs Rata-Rata: $NI = \text{NetIncome}_{\text{foreign}} \times \text{AverageRate}$
   - Selisih Kurs Translasi (FCTR):
     $$\Delta_{\text{FCTR}} = E_1 - (E_0 + NI)$$
3. Memposting penyesuaian FCTR ke `LedgerService`:
   - Jika $\Delta_{\text{FCTR}} > 0$ (gain translasi):
     - Debit `ic:foreign_equity_adj:{anak}:IDR` = $-\Delta_{\text{FCTR}}$
     - Kredit `ic:fctr_reserve:{anak}:IDR` = $+\Delta_{\text{FCTR}}$
   - Jika $\Delta_{\text{FCTR}} < 0$ (loss translasi):
     - Debit `ic:fctr_reserve:{anak}:IDR` = $-\Delta_{\text{FCTR}}$
     - Kredit `ic:foreign_equity_adj:{anak}:IDR` = $+\Delta_{\text{FCTR}}$

## Konsekuensi
- Neraca konsolidasi tetap seimbang secara absolut tanpa mendistorsi kinerja laba rugi operasional grup.
- Kepatuhan penuh terhadap PSAK 10 / IAS 21.
- Mencegah volatilitas semu pada laba bersih konsolidasi akibat fluktuasi kurs mata uang anak asing.

