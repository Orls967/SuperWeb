# ADR-0079: Standarisasi Agregasi Neraca Saldo Multi-Entitas (Consolidated Trial Balance) Sesuai PSAK 65 / IFRS 10

**Status**: DITERIMA  
**Tanggal**: 2026-11-11  
**Pencetus**: Arsitek Solusi Keuangan Grup [W8-P5.3]  
**Konteks**: Modul `EnterpriseFinance` (Fase 54)

---

## 1. Konteks Bisnis & Masalah
Konglomerasi memiliki unit bisnis di berbagai yurisdiksi dan sektor (manufaktur, distribusi, teknologi, logistik, trading). Laporan keuangan masing-masing entitas disusun dengan chart of accounts (COA) lokal dan mata uang fungsional masing-masing. Diperlukan proses agregasi neraca saldo (Trial Balance) terstandarisasi yang mampu:
1. Memetakan akun lokal ke COA grup kanonikal secara otomatis.
2. Mengagregasikan saldo debit dan kredit seluruh akun tanpa pembulatan mengambang.
3. Menjamin zero-sum debit sama dengan kredit ($\Sigma \text{Debit} = \Sigma \text{Kredit}$) sebelum proses eliminasi.

## 2. Keputusan yang Diambil
1. **Penerapan Algoritma Bersertifikat D8 (`ConsolidatedTrialBalanceAggregator`)**: Seluruh saldo neraca entitas diagregasi secara deterministik pada tingkat mata uang dasar pelaporan grup (IDR).
2. **Kanonikalisasi Saldo Akun**: Seluruh saldo dinyatakan dalam integer unit moneter terkecil (`minor_unit`). Tidak diperkenankan konversi float intermediate.
3. **Penerbitan Sertifikat Integritas SHA-256**: Setiap eksekusi agregasi menghasilkan sertifikat kriptografis yang mencatat timestamp, daftar ID entitas, total debit, total kredit, delta zero-sum, dan hash SHA-256 payload.
4. **Pencegahan Diskrepansi**: Apabila $\Sigma \text{Debit} \neq \Sigma \text{Kredit}$, sistem secara otomatis menolak penerbitan laporan konsolidasi dan memicu alarm investigasi transaksi gantung.

## 3. Konsekuensi Positif & Mitigasi Risiko
- **Positif**: Konsolidasi laporan keuangan terbebas dari perbedaan angka akibat selisih desimal; waktu penyusunan laporan keuangan grup terpangkas dari hitungan hari menjadi detik.
- **Mitigasi**: Selisih kurs translasi valas diselesaikan terlebih dahulu melalui FCTR reserve di modul Intercompany sebelum neraca saldo ditarik ke agregator konsolidasi.
