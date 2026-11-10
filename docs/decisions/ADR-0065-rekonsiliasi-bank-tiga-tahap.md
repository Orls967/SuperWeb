# ADR-0065: Rekonsiliasi Bank Tiga Tahap & Pengelolaan Antrean Mismatch

## Status
DITERIMA (2026-11-11) — Keputusan W8-D65 (Gelombang 8, Prompt P5).

## Konteks
Rekonsiliasi rekening koran perbankan (*bank statement*) dengan buku kas internal perusahaan rentan mengalami kegagalan pencocokan akibat perbedaan penomoran referensi, jeda kliring perbankan (selisih hari tanggal transaksi), dan setoran/tarikan gabungan (*batch settlement*).

## Keputusan
1. **Algoritma Pencocokan 3-Tahap D2 (`BankReconciliationMatcher`)**:
   - **Tahap 1 (Exact Match)**: Nominal identik dan nomor referensi tidak kosong sama persis.
   - **Tahap 2 (Tolerance Match)**: Selisih nominal $\le 500$ minor unit dan selisih tanggal $\le 3$ hari kalender, dipasangkan secara greedy berdasarkan jarak terdekat.
   - **Tahap 3 (Bounded Many-to-One)**: Memadankan 1 transaksi bank dengan kombinasi 2 hingga 4 mutasi buku kas yang jumlahnya tepat sama, dengan batas 12 kandidat terdekat ($\le 5$ hari) untuk menjamin kompleksitas tetap konstan ($\le 781$ kombinasi).
2. **Antrean Mutasi Tak Terpadankan**: Seluruh baris mutasi yang tidak terpadankan masuk ke antrean bertanggal (`trs_bank_reconciliation_unmatched`) untuk ditindaklanjuti secara manual atau dieskalasikan ke `TaskInboxService`.
3. **Pembatalan Rekonsiliasi (`Unmatch`)**: Pengguna dapat membatalkan rekonsiliasi yang keliru cocok dengan merekam alasan operasional dan jejak audit trail.
4. **Posting Selisih Rekonsiliasi Disetujui**: Selisih yang disetujui (misal biaya administrasi bank atau bunga jasa giro) diposting ke akun `trs:bank_reconciliation_diff:IDR`.

## Konsekuensi
- Menghasilkan tingkat otomatisasi rekonsiliasi tinggi tanpa mengorbankan integritas dan kepastian audit matematis.

