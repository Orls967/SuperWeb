# Manual Integrasi: Katalog Hadiah & Tiket Festival Venue Terhubung Travel Pass — Fase 112

## Bab 1: Integrasi Lintas Modul Hotel dan Venue
Modul Hotel berkolaborasi erat dengan modul Venue (`Modules\Venue`) untuk menyajikan katalog hadiah eksklusif. Tamu yang memiliki saldo poin Travel Pass dapat menukarkan poin mereka secara instan menjadi tiket konser musik, tiket festival EDM, akses sofa VIP beach club, dan gelang festival RFID.

### Titik Kontak Integrasi (Integration Touchpoints):
1. **Sinkronisasi Katalog Event:** Modul Hotel membaca katalog acara aktif dari modul Venue melalui antarmuka event atau query repository tanpa melanggar batas isolasi arsitektur (Rule A1/A2).
2. **Kunci Kursi / Kuota Tiket Instan:** Saat tamu memilih tiket festival, sistem melakukan hold inventori tiket sementara selama 10 menit untuk memberi waktu verifikasi saldo poin.
3. **Penyelesaian Transaksi Berkelanjutan:** Poin tamu didebit, liabilitas hotel dipindahkan ke akun kliring pendapatan tiket festival venue.

---

## Bab 2: Alur Penukaran Tiket di Portal Anggota
1. Tamu membuka tab **Venue & Festival Rewards** pada dasbor Travel Pass.
2. Memilih konser festival yang diinginkan (misal "Bali Summer Sunset Festival 2026").
3. Memilih kategori tiket (General Admission 5.000 PTS, VIP Standing 12.000 PTS, VVIP Table 50.000 PTS).
4. Konfirmasi penukaran: sistem mengeksekusi pemotongan poin FIFO, menerbitkan kode QR tiket terenkripsi, dan mencatat nomor tiket unik gapless ke sistem tiket Venue.
5. Tamu dapat langsung memindai kode QR tiket di pintu gerbang festival modul Venue untuk masuk tanpa antre.

---

## Bab 3: Kebijakan Pembatalan & Pengembalian Tiket Hadiah
- Tiket festival yang ditukarkan dengan poin dapat dibatalkan hingga H-7 sebelum acara dengan pengembalian 100% poin ke akun tamu.
- Pembatalan antara H-6 hingga H-2 dikenakan biaya penalti pembatalan sebesar 25% dari total poin yang dipotong.
- Pembatalan dalam 48 jam sebelum acara berstatus hangus (non-refundable) kecuali jika penyelenggara acara membatalkan konser secara sepihak.

