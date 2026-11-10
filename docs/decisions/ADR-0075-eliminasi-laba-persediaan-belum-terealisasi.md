# ADR-0075: Eliminasi Laba Antar-Perusahaan dalam Persediaan Akhir

## Status
Diterima (Accepted)

## Konteks
Ketika Entitas A menjual persediaan barang dagang ke Entitas B dengan menambahkan margin keuntungan transfer pricing, dan pada akhir periode pelaporan barang dagangan tersebut masih tersimpan di gudang Entitas B (belum terjual ke pihak ketiga eksternal), maka margin keuntungan tersebut merupakan laba antar-perusahaan yang belum terealisasi (*unrealized intercompany inventory profit*).

Sesuai PSAK 65 paragraf B86(c), laba belum terealisasi yang timbul dari transaksi intra-kelompok harus dieliminasi penuh terhadap nilai tercatat persediaan dan laba kotor konsolidasi.

## Keputusan
1. Membuat mekanisme penelusuran laba persediaan intra-grup pada tabel `ic_unrealized_inventory_profits`.
2. Menghitung porsi laba belum terealisasi berdasarkan rasio margin laba kotor penjual ($GMR$):
   $$GMR = \frac{\text{IntercompanyPrice} - \text{CostOfGoodsSold}}{\text{IntercompanyPrice}}$$
   $$\text{UnrealizedProfit} = \lfloor \text{EndingInventoryBalance} \times GMR \rfloor$$
3. Memposting jurnal eliminasi konsolidasi berimbang ke `LedgerService`:
   - Debit `ic:unrealized_profit_reserve:{penjual}:IDR` = $-\text{UnrealizedProfit}$ (mengurangi laba ditahan / laba periode berjalan)
   - Kredit `ic:inventory_adj:{pembeli}:IDR` = $+\text{UnrealizedProfit}$ (mengurangi nilai persediaan akhir di neraca konsolidasi menjadi sebesar biaya perolehan awal grup)
4. Pada periode berikutnya ketika persediaan tersebut terjual ke pihak ketiga eksternal, jurnal pembalik (*reversal*) otomatis diposting untuk mengakui laba yang telah terealisasi.

## Konsekuensi
- Nilai persediaan di neraca konsolidasi mencerminkan biaya perolehan riil grup ke pihak luar.
- Mencegah penggelembungan laba kotor konsolidasi (*overstatement of group earnings*).

