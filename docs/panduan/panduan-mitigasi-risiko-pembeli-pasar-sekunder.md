# Panduan Mitigasi Risiko Pembeli Pasar Sekunder

## 1. Hak & Jaminan Keamanan Konsumen
Pembelian tiket konser musik dan festival melalui jalur tidak resmi di luar sistem perantara resmi (*unofficial secondary channels*) sarat dengan risiko penipuan:
1. Tiket palsu (*counterfeit tickets*),
2. Tiket ganda yang dijual ke banyak orang (*double selling*),
3. Harga tidak masuk akal yang dinaikkan ribuan persen oleh tengkulak,
4. Ketiadaan penggantian dana bila konser dibatalkan oleh pihak promotor.

Superweb Venue menghadirkan bursa pasar sekunder terverifikasi (*verified fan-to-fan resale*) dengan empat pilar perlindungan konsumen terintegrasi.

---

## 2. Empat Pilar Perlindungan Konsumen
### Pilar 1: Jaminan Tiket Resmi & Barcode Baru 100%
Setiap transaksi yang berhasil diselesaikan di pasar sekunder Superweb secara instan membatalkan barcode tiket lama yang dipegang penjual. Sistem menerbitkan barcode token baru yang hanya dapat diakses melalui aplikasi resmi pembeli. Penjual lama dipastikan tidak memiliki akses fisik ke gedung konser dengan tiket tersebut.

### Pilar 2: Plafon Harga Anti-Scalping Legal 120%
Konsumen dilindungi dari harga spekulatif berkat algoritma `AntiScalpingCapEngine`. Harga tiket sekunder tidak akan pernah melebihi $120\%$ dari harga asli yang ditetapkan oleh promotor. Tambahan biaya $20\%$ tersebut digunakan sepenuhnya untuk membayar pajak hiburan resmi ke kas daerah (10%), biaya keamanan transaksi (8%), dan royalti artis/promotor (2%).

### Pilar 3: Rekening Penampungan Aman (Escrow Hold)
Uang yang dibayarkan oleh pembeli tidak langsung diteruskan ke rekening penjual. Dana ditahan secara aman di dalam rekening penampungan pihak ketiga (*escrow account*). Dana baru dicairkan setelah sistem memverifikasi bahwa transfer tiket telah tuntas dan integritas data tiket terjamin.

### Pilar 4: Jaminan Pengembalian Dana Pembatalan Acara (Refund Guarantee)
Jika acara konser dibatalkan oleh promotor atau terjadi situasi kahar (*force majeure*):
- Alur `EventCancellationSaga` secara otomatis mengembalikan $100\%$ dana pembelian sekunder kepada pembeli sah saat ini.
- Hak pengembalian dana melekat pada pembeli baru, bukan pada pemilik tiket pertama yang telah menjual tiketnya.

---

## 3. Langkah-Langkah Verifikasi Mandiri oleh Pembeli
Sebelum melakukan pembayaran tiket sekunder:
1. Pastikan transaksi dilakukan di dalam portal resmi bursa sekunder Superweb (`/venue/secondary/marketplace`).
2. Periksa badge keaslian tiket bertuliskan `TERVERIFIKASI RESMI`.
3. Tinjau rincian transparansi biaya pada kartu rincian harga (*Price Breakdown Card*):
   - Harga Asli (*Face Value*),
   - Harga Penawaran (*Resale Price*),
   - Pajak Hiburan PBJT (10%),
   - Biaya Layanan Platform (8%),
   - Royalti Promotor (2%).
4. Selesaikan pembayaran dalam jendela waktu yang ditentukan untuk mengunci hak kepemilikan tiket Anda.

