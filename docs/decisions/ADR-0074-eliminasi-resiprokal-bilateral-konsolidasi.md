# ADR-0074: Eliminasi Resiprokal Bilateral & Toleransi Nol Rekonsiliasi Konsolidasi

## Status
Diterima (Accepted)

## Konteks
Dalam laporan keuangan konsolidasi sesuai PSAK 65 (IFRS 10), seluruh saldo timbal balik (*reciprocal balances*) dan transaksi intra-kelompok usaha wajib dieliminasi penuh:
1. Piutang intercompany di Entitas A wajib tepat sama besarnya dengan Hutang intercompany di Entitas B.
2. Penjualan intercompany di Entitas A wajib tepat sama dengan Beban Pokok Penjualan / Pembelian di Entitas B.
3. Bila terjadi diskrepansi waktu (*timing differences*) pengiriman barang atau pencatatan transfer bank yang belum selesai di akhir bulan, selisih tersebut harus diidentifikasi sebagai pos transit dan tidak boleh langsung dihapuskan tanpa penelusuran.

## Keputusan
1. Mengimplementasikan algoritma bersertifikat `BilateralEliminationMatcher` (D5):
   - Mengelompokkan seluruh entri piutang dan hutang antar pasangan entitas bilateral $(E_{\text{seller}}, E_{\text{buyer}})$.
   - Menghitung diskrepansi saldo:
     $$\Delta_{\text{reciprocal}} = |\text{Balance}_{AR} - \text{Balance}_{AP}|$$
   - Bila $\Delta_{\text{reciprocal}} = 0$, jurnal eliminasi otomatis dibuat dengan status `MATCHED_EXACT`.
   - Bila $\Delta_{\text{reciprocal}} > 0$, sistem menandai selisih ke dalam antrean investigasi transit dengan batas toleransi absolut nol pada cut-off konsolidasi akhir periode.
2. Mencatat seluruh voucher eliminasi ke tabel `ic_elimination_entries` dan memposting jurnal berimbang ke `LedgerService`:
   - Debit `ic:ap:{penjual}:{pembeli}:{ccy}` (mengurangi hutang)
   - Kredit `ic:ar:{pembeli}:{penjual}:{ccy}` (mengurangi piutang)
   - Debit `ic:sales:{penjual}:{ccy}` (mengurangi omzet kotor konsolidasi)
   - Kredit `ic:cogs:{penjual}:{pembeli}:{ccy}` (mengurangi beban pokok konsolidasi)
3. Sertifikat `BilateralEliminationCertificate` memverifikasi secara matematis bahwa $\Sigma \text{Debit} = \Sigma \text{Kredit}$ dan saldo eliminasi neto adalah nol.

## Konsekuensi
- Mencegah penggandaan pendapatan (*revenue double-counting*) pada laporan keuangan konsolidasi grup.
- Menjamin kepatuhan penuh terhadap standar akuntansi keuangan PSAK 65 / IFRS 10.
- Menghadirkan rekonsiliasi otomatis cepat saat proses *fast close* tutup buku bulanan.

