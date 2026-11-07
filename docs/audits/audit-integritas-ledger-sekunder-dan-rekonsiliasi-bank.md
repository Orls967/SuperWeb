# Audit Integritas Ledger Sekunder & Rekonsiliasi Rekening Escrow

## 1. Landasan Integritas Buku Besar Finansial
Setiap transaksi yang terjadi pada pasar sekunder tiket melibatkan perpindahan dana nyata antar rekening perbankan pengguna. Untuk menjamin akuntabilitas audit finansial setara perbankan (*bank-grade financial auditability*), modul Venue mencatat setiap mutasi ke dalam tabel `ven_secondary_ledger_entries`.

Buku besar ini beroperasi dengan prinsip pembukuan ganda (*double-entry balancing*):
1. **Penerimaan Dana Pembeli (Escrow Hold):** Merekam arus kas masuk ke rekening penampungan.
2. **Penyelesaian Transaksi (Waterfall Settlement):** Merekam distribusi terdistribusi ke pos kas daerah (PBJT), rekening operasional platform, rekening royalti promotor, dan pencairan bersih penjual.
3. **Pengembalian Dana (Escrow Refund):** Merekam arus kas keluar kembali ke rekening pembeli bila transaksi gagal diverifikasi.

---

## 2. Parameter Audit & Keseimbangan Buku Besar
Perintah audit `php artisan venue:audit-secondary` secara deterministik memverifikasi:
1. **Total Saldo Transaksi:**
   $$\sum \text{gross\_amount\_idr} \equiv \sum \text{pbjt\_tax\_idr} + \sum \text{platform\_fee\_idr} + \sum \text{promoter\_royalty\_idr} + \sum \text{seller\_net\_idr}$$
2. **Kesesuaian Saldo Penampungan (Escrow Bank Balancing):**
   Saldo fisik rekening giro penampungan escrow pada bank mitra wajib sama dengan jumlah order berstatus `ESCROW_HOLD` ditambah kewajiban penyaluran PBJT yang belum disetorkan ke kas daerah.
3. **Ketiadaan Transaksi Menggantung (Dangling Orders):**
   Tidak ada order yang berstatus `ESCROW_HOLD` lebih dari 24 jam tanpa penyelesaian (`SETTLED`) atau pengembalian (`REFUNDED`).

Audit ini menjamin ketiadaan celah manipulasi dana dan memberikan rasa aman bagi seluruh pemangku kepentingan industri pertunjukan musik.

