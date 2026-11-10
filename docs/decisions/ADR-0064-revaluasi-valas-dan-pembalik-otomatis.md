# ADR-0064: Revaluasi Pos Moneter Valas Akhir Periode & Jurnal Pembalik Otomatis

## Status
DITERIMA (2026-11-11) — Keputusan W8-D64 (Gelombang 8, Prompt P5).

## Konteks
Sesuai PSAK 10, pos moneter valuta asing (piutang, utang, kas bank valas, pinjaman) wajib direvaluasi pada akhir periode akuntansi menggunakan kurs penutupan (*closing rate*). Keuntungan atau kerugian selisih kurs yang belum terealisasi (*unrealized gain/loss*) tidak boleh dibiarkan mengendap permanen pada akun penyesuaian aset/liabilitas karena akan menimbulkan dobel hitung (*double-counting*) saat pelunasan kas aktual di masa depan.

## Keputusan
1. **Algoritma Revaluasi D1 (`FxRevaluationCalculator`)**: Menghitung penyesuaian nilai tercatat dan laba/rugi belum terealisasi menggunakan kurs rasional penutupan dengan pembulatan setengah menjauhi nol (*round half away from zero*).
2. **Posting Jurnal Revaluasi**:
   - Aset Untung: `DR trs:fx_reval_asset_adj:IDR / CR trs:fx_unrealized_gain_loss:IDR`
   - Aset Rugi: `DR trs:fx_unrealized_gain_loss:IDR / CR trs:fx_reval_asset_adj:IDR`
   - Liabilitas Rugi: `DR trs:fx_unrealized_gain_loss:IDR / CR trs:fx_reval_liability_adj:IDR`
   - Liabilitas Untung: `DR trs:fx_reval_liability_adj:IDR / CR trs:fx_unrealized_gain_loss:IDR`
3. **Pembalik Otomatis Awal Periode**: Pada hari pertama periode berikutnya, perintah `treasury:reverse-revaluations` secara otomatis memposting jurnal pembalik persis arah berlawanan (`reverse_of`), mengembalikan saldo penyesuaian revaluasi ke nilai carrying asal.
4. **Pengakuan Untung/Rugi Terealisasi**: Saat pelunasan kas riil, selisih kurs dihitung dari nilai pelunasan terhadap carrying asli lot moneter (proporsional jika pelunasan parsial) dan diposting ke `trs:fx_realized_gain_loss:IDR`.

## Konsekuensi
- Menghilangkan distorsi laba rugi antar periode dan menjamin kebenaran pencatatan realized gain/loss saat pelunasan riil.

