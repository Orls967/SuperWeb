# Pedoman Integrasi Aliansi Maskapai & Pertukaran Poin Hotel — Fase 112

## 1. Landasan Aliansi Bilateral
Program Global Travel Pass Hotel terhubung dengan ekosistem penerbangan dan transportasi global. Kerjasama ini memungkinkan pertukaran nilai dua arah antara Poin Hotel PTS dan Miles/Poin Mitra Aliansi.

## 2. Struktur Biaya & Spread Konversi
- **Rasio Konversi Standar:** Ditetapkan per perjanjian kemitraan (misal 10 PTS Hotel = 1 Airline Mile).
- **Biaya Pemrosesan Transfer (Platform Transfer Fee):** Dikenakan sebesar 3,5% dari nilai nominal transfer untuk membiayai infrastruktur jaringan dan biaya perbankan, dibukukan ke akun `htl:transfer_fee_revenue:IDR`.
- **Batas Minimum & Maksimum:**
  - Minimum transfer: 1.000 PTS.
  - Maksimum transfer harian: 100.000 PTS per akun anggota.

## 3. Protokol Kompensasi Resiliensi Transaksi
Transfer poin melibatkan panggilan API antar-organisasi yang rentan terhadap gangguan jaringan:
- Transaksi dikontrol oleh `HotelPartnerPointTransferSaga`.
- Apabila konfirmasi penerimaan tidak diperoleh dalam batas waktu 15 detik, saga membatalkan debit poin dan mengembalikan seluruh saldo ke akun tamu secara otomatis.
- Tidak ada kondisi saldo mengambang (orphaned points) di mana poin hilang dari hotel namun belum masuk ke sistem maskapai mitra.

