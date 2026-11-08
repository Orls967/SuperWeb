# Pedoman Penanganan Sengketa & Klaim Poin Hilang (Missing Points Resolution) — Fase 112

## Bab 1: Penyebab Terjadinya Klaim Poin Hilang
Dalam operasional harian berskala besar lintas 17 lini usaha, terdapat kemungkinan transaksi pelanggan tidak langsung terhubung ke akun Global Travel Pass akibat:
1. Tamu lupa menyebutkan nomor Travel Pass saat memesan makanan di gerai restoran atau kasir festival.
2. Kesalahan ketik nomor paspor atau nama tamu pada sistem reservasi agen perjalanan daring (OTA).
3. Gangguan konektivitas sementara antara kasir outlet POS dan server pusat saat pemrosesan pembayaran offline.

---

## Bab 2: Alur Pengajuan & Verifikasi Klaim Mandiri
Tamu dapat mengajukan klaim susulan poin hilang secara mandiri melalui portal anggota dalam kurun waktu maksimal 180 hari sejak tanggal transaksi:
1. **Langkah 1: Pengisian Formulir Klaim:**
   Tamu memasukkan nomor kode referensi struk pembayaran (receipt reference number), tanggal transaksi, nama outlet/hotel, dan estimasi nilai transaksi kotor dalam rupiah.
2. **Langkah 2: Pemeriksaan Duplikasi Deterministik:**
   Sistem secara otomatis memeriksa tabel transaksi untuk memastikan referensi struk tersebut belum pernah diklaim sebelumnya (anti-double claim).
3. **Langkah 3: Pencocokan Otomatis (Auto-Matching Engine):**
   Sistem mencocokkan struk dengan data log penjualan dari modul terkait (Resto, Hotel Folio, atau Venue Ticket):
   - Bila data cocok 100%: poin langsung dikreditkan ke Travel Pass tamu secara instan, dan event akrual diterbitkan ke buku besar.
   - Bila data tidak ditemukan dalam log: klaim dialihkan ke antrean investigasi manual tim Layanan Pelanggan.

---

## Bab 3: Prosedur Koreksi Saldo & Penyesuaian Audit (Manual Adjustment)
Bila petugas layanan pelanggan menyetujui klaim manual:
- Koreksi saldo wajib disertai alasan terstandarisasi (misal: `RETROACTIVE_DINING_ACCRUAL`, `CHECKIN_DISCONNECT_RECOVERY`).
- Setiap penyesuaian manual di atas 5.000 PTS memerlukan persetujuan otorisasi digital (dual-control sign-off) oleh Manajer Loyalitas.
- Sistem membukukan penyesuaian ke buku besar dengan tipe posting `PTS_MANUAL_ADJUSTMENT`, menjaga integritas saldo liabilitas poin.

