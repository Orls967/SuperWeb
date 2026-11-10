# ADR-0078: Transfer Aset Tetap & Stok Lintas Batas Kepabeanan Terintegrasi

## Status
Diterima (Accepted)

## Konteks
Operasional grup konglomerasi mencakup perpindahan fisik mesin/peralatan antar-pabrik entitas anak (mis. mutasi aset tetap dari Manufaktur ke Properti) serta pengiriman bahan baku dan produk jadi antar-negara (mis. pengiriman dari anak di Singapura ke anak di Indonesia).
1. Pada mutasi aset tetap, nilai buku historis dan akumulasi penyusutan harus dipindahkan tanpa menimbulkan laba fiktif antar-perusahaan sebelum konsolidasi.
2. Pada pengiriman stok lintas batas, transaksi melibatkan regulasi kepabeanan (Pemberitahuan Ekspor Barang - PEB, Pemberitahuan Impor Barang - PIB), bea masuk, dan PPN Impor yang dibayar oleh anak penerima.

## Keputusan
1. Membuat modul transfer aset tetap (`ic_asset_transfers`):
   - Mencatat nilai perolehan awal, akumulasi penyusutan, dan nilai buku neto (*Net Book Value* - NBV).
   - Memposting pengalihan buku besar aset secara simetris:
     - Sisi Pengirim: Kredit akun aset tetap, Debit akun akumulasi penyusutan, Debit piutang transfer aset.
     - Sisi Penerima: Debit akun aset tetap, Kredit akun akumulasi penyusutan, Kredit hutang transfer aset.
2. Membuat modul transfer stok lintas batas (`ic_stock_transfers`):
   - Mencatat nomor referensi dokumen kepabeanan simulasi (`customs_peb_number`, `customs_pib_number`).
   - Memperhitungkan nilai CIF (*Cost, Insurance, Freight*), bea masuk (*import duty*), dan PPN impor (*import VAT*).
   - Memposting pengeluaran persediaan dari entitas asal dan penerimaan persediaan ditambah kapitalisasi landed cost di entitas tujuan:
     - Sisi Penerima: Debit `stk:inventory:{penerima}:IDR` (sebesar CIF + Bea Masuk), Debit `tax:vat_in:{penerima}:IDR` (sebesar PPN Impor), Kredit `ic:ap:{pengirim}:{penerima}:IDR` (sebesar CIF), Kredit `trs:bank_clearing:{penerima}:IDR` (sebesar Bea Masuk & PPN disetor ke kas negara).

## Konsekuensi
- Menjamin kepatuhan administrasi perpajakan dan pabean pada transaksi lintas batas grup.
- Nilai persediaan di anak penerima mencerminkan total biaya perolehan wajar termasuk bea impor.
- Pelacakan fisik dan hukum aset tetap antar-entitas terjaga dengan baik.

