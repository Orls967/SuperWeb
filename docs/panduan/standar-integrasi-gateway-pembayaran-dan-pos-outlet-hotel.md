# Standar Integrasi Gateway Pembayaran & Terminal POS Outlet Hotel — Fase 112

## Bab 1: Topologi Jaringan & Protokol Sinkronisasi Kasir
Setiap outlet di dalam properti hotel (Restoran Utama, Bar Tepi Kolam, Gerai Spa, Lounge Atap, dan Butik Suvenir) dilengkapi dengan terminal Point of Sale (POS) yang terhubung ke modul Hotel dan ekosistem Travel Pass melalui gateway middleware terdistribusi.

```
+--------------------+        +-----------------------+        +----------------------+
| Terminal Kasir POS | <----> | Middleware POS Driver | <----> | Travel Pass Gateway  |
|  (Resto / Spa)     |  TCP   |   (Local Node Proxy)  | HTTPS  | (Modules\Hotel PMS)  |
+--------------------+        +-----------------------+        +----------------------+
                                                                          |
                                                                          v
                                                               +----------------------+
                                                               | Akrual Poin & Ledger |
                                                               | (Double-Entry Engine)|
                                                               +----------------------+
```

---

## Bab 2: Alur Transaksi Kasir Menggunakan Poin (Pay-with-Points)
1. **Langkah 1 - Pemindaian Identitas:**
   Kasir meminta tamu menunjukkan kode QR Travel Pass pada aplikasi ponsel tamu. Kasir memindai kode QR menggunakan pemindai barcode 2D optik.
2. **Langkah 2 - Pengecekan Saldo & Pilihan Pembayaran:**
   Layar kasir menampilkan nama tamu, tier keanggotaan, dan saldo poin yang dapat dibelanjakan.
   Kasir menawarkan tiga mode penyelesaian:
   - *Penuh dengan Poin (100% Points):* Tagihan lunas dipotong dari poin.
   - *Kombinasi Kas & Poin (Split Cash + Points):* Sebagian dipotong poin, sisanya dibayar via kartu/tunai.
   - *Penuh Tunai dengan Akrual Poin (Earn Points Only):* Tamu membayar tunai/kartu dan menerima poin akrual baru.
3. **Langkah 3 - Eksekusi Transaksi Atomik:**
   Middleware POS mengirimkan permintaan ke endpoint `/hotel/travel-pass/pos-redeem`.
   Sistem mengeksekusi pemotongan saldo FIFO, mencetak nomor referensi audit pada struk pembayaran, dan menyinkronkan data ke buku besar dalam waktu kurang dari 800 milidetik.

---

## Bab 3: Penanganan Kegagalan Jaringan & Mode Offline Kasir (Store-and-Forward)
Bila koneksi internet hotel terputus sementara:
- Terminal POS beralih ke mode darurat **Store-and-Forward**.
- Transaksi akrual poin baru dicatat secara lokal pada basis data terenkripsi kasir dengan tanda tangan hash perangkat.
- Penukaran poin (redemption) dibatasi maksimum senilai Rp 200.000 per transaksi offline untuk mencegah risiko saldo negatif.
- Saat jaringan pulih, middleware POS secara otomatis menyinkronkan seluruh antrean transaksi lokal ke server pusat secara berurutan sesuai timestamp.

