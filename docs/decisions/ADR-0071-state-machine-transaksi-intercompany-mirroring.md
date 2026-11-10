# ADR-0071: State Machine Transaksi Intercompany Mirroring (8 Status)

## Status
Diterima (Accepted)

## Konteks
Siklus transaksi antar-anak perusahaan memerlukan koordinasi akuntansi bilateral yang ketat. Pada sistem lama, transaksi hanya memiliki tiga status acak (`matched`, `settled`, `disputed`) tanpa adanya jejak audit perubahan (*audit trail*), validasi kepatuhan transfer pricing sebelum pembukuan, atau verifikasi eliminasi konsolidasi periodik.

## Keputusan
Menetapkan mesin status resmi 8 tahapan terverifikasi untuk model `IntercompanyTransaction`:
1. `DRAFT`: Transaksi disiapkan oleh operator akuntansi entitas penjual.
2. `SUBMITTED`: Transaksi diajukan untuk penelaahan harga transfer dan kepatuhan.
3. `ARM_LENGTH_VALIDATED`: Margin laba kotor/operasi telah diverifikasi memenuhi rentang interkuartil PMK 172/OECD oleh algoritma D4.
4. `POSTED_SELLER`: Sisi penjual berhasil membukukan faktur penjualan (AR Intercompany dan Revenue) ke buku besar.
5. `POSTED_BUYER`: Tagihan pembelian cermin (*mirror purchase bill*) berhasil dibuat dan dibukukan (Expense/Stok dan AP Intercompany) ke buku besar pembeli.
6. `RECONCILED`: Saldo piutang penjual dan hutang pembeli telah dicocokkan pada penutupan bulan oleh algoritma D5.
7. `ELIMINATED`: Saldo resiprokal dan omzet intercompany telah diposting ke jurnal eliminasi konsolidasi grup.
8. `REVERSED`: Transaksi dibatalkan secara simetris dengan jurnal pembalik lengkap di kedua belah pihak.

Setiap perpindahan status wajib dicatat ke tabel append-only `ic_transaction_histories` dengan mencatat waktu transisi, pelaku, dan alasan transisi.

## Konsekuensi
- Tidak ada transaksi yang dapat dibukukan sebelum margin transfer pricing tervalidasi.
- Transaksi cermin selalu atomik: kegagalan pembukuan di sisi pembeli akan membatalkan atau menahan status sisi penjual.
- Jejak audit transisi status lengkap untuk keperluan audit eksternal dan otoritas perpajakan.

