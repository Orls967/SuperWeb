# ADR-0063: Buku Besar Multi-Currency Dua Kaki Berimbang & Subledger Lot Valas

## Status
DITERIMA (2026-11-11) — Keputusan W8-D63 (Gelombang 8, Prompt P5).

## Konteks
Pencatatan multi-currency dalam standar akuntansi ganda mewajibkan setiap jurnal transaksi valuta asing seimbang pada mata uang asal dan seimbang pada mata uang fungsional pelaporan (IDR). Modul perbendaharaan membutuhkan mekanisme pelacakan saldo moneter valas individual (lot valas) untuk menghitung carrying amount historis, revaluasi, dan laba/rugi selisih kurs terealisasi saat pelunasan.

## Keputusan
1. **Posting Dua Kaki Berimbang**: Setiap transaksi valas diposting ke ledger menjadi dua kaki jurnal independen yang masing-masing seimbang ($\Sigma \text{Debit} = \Sigma \text{Kredit} = 0$):
   - Kaki Valuta Asing: Menggunakan akun dengan `asset_code = CCY` (contoh USD).
   - Kaki Fungsional: Menggunakan akun dengan `asset_code = IDR`.
   Kedua kaki jurnal disatukan oleh turunan kunci idempotensi yang sama (`{key}_FX` dan `{key}_IDR`).
2. **Subledger Lot Valas (`trs_fx_lots`)**: Menyimpan riwayat setiap transaksi moneter valas masuk: `currency`, `original_foreign_amount`, `remaining_foreign_amount`, `rate_numerator`, `rate_denominator`, `carrying_functional_idr`, dan `lot_date`.
3. **FxMonetaryItemFeed Contract**: Modul sumber pos moneter valas (misal Procurement untuk utang valas, Sales untuk piutang valas) melaporkan saldo pos moneter secara berkala melalui antarmuka `Modules\Treasury\Contracts\FxMonetaryItemFeed`.

## Konsekuensi
- Menjamin integritas neraca multi-mata uang tanpa selisih cross-currency.
- Memungkinkan perhitungan realized gain/loss proporsional saat pelunasan parsial berdasarkan lot historis.
