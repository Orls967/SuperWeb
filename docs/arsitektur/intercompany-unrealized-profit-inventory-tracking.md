# Arsitektur Teknis: Penelusuran & Eliminasi Laba Persediaan Belum Terealisasi

## 1. Landasan Teoretis & Mekanika Akuntansi
Ketika Entitas A menjual persediaan barang dagang ke Entitas B dengan margin laba kotor, barang tersebut dicatat di neraca Entitas B pada harga beli intercompany yang lebih tinggi dari biaya produksi awal grup.
Jika pada akhir tahun buku:
- $60\%$ dari barang tersebut telah berhasil dijual Entitas B ke pelanggan pihak ketiga di luar grup $\implies$ laba atas $60\%$ barang telah **sah terealisasi**.
- $40\%$ dari barang tersebut masih tersisa di gudang penyimpanan Entitas B $\implies$ laba atas $40\%$ barang tersebut masih **belum terealisasi** (*unrealized intercompany profit*) dan wajib dieliminasi dari neraca konsolidasi grup.

---

## 2. Model Penelusuran Lot Persediaan Intra-Grup
Tabel `ic_unrealized_inventory_profits` menyimpan data analitis per periode:
1. `selling_entity_id`: Entitas yang memproduksi dan menjual barang.
2. `buying_entity_id`: Entitas yang menyimpan sisa persediaan.
3. `inventory_balance_idr`: Nilai saldo akhir persediaan di buku pembeli per tanggal neraca.
4. `unrealized_profit_ratio_permille`: Rasio laba kotor penjual dalam permil ($GMR \times 1000$).
5. `unrealized_profit_idr`: Nilai nominal laba yang wajib dieliminasi:
   $$\text{UnrealizedProfit} = \left\lfloor \frac{\text{InventoryBalance} \times \text{ProfitRatioPermille}}{100000} \right\rfloor$$

---

## 3. Ayat Jurnal Eliminasi dan Pembalik Periode Berikutnya

### A. Jurnal Eliminasi Periode Berjalan (31 Desember Tahun $t$)
- Debit `ic:unrealized_profit_reserve:{penjual}:IDR` = $-\text{UnrealizedProfit}$ (mengurangi laba konsolidasi)
- Kredit `ic:inventory_adj:{pembeli}:IDR` = $+\text{UnrealizedProfit}$ (menurunkan nilai persediaan di neraca)
- Netto saldo jurnal = $0$.

### B. Jurnal Pembalik Otomatis Awal Periode Berikutnya (1 Januari Tahun $t+1$)
Pada awal tahun buku baru, jurnal pembalik simetris diposting secara otomatis oleh sistem saat penutupan buku tahunan:
- Debit `ic:inventory_adj:{pembeli}:IDR` = $-\text{UnrealizedProfit}$
- Kredit `ic:retained_earnings_adj:{penjual}:IDR` = $+\text{UnrealizedProfit}$

Dengan demikian, ketika persediaan akhirnya terjual kepada pihak ketiga pada tahun $t+1$, laba kotor riil grup akan diakui secara proporsional dan tepat waktu pada laporan laba rugi konsolidasi tahun $t+1$.
