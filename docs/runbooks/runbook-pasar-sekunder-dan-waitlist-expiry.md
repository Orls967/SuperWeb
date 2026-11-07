# Runbook Pemeliharaan Pasar Sekunder & Kedaluwarsa Waitlist

## 1. Ikhtisar Operasional
Runbook ini memandu staf rekayasa keandalan situs (*Site Reliability Engineer* / SRE) dan tim operasional venue dalam menangani pemeliharaan terjadwal, pemulihan insiden, dan siklus kedaluwarsa otomatis daftar tunggu (*waitlist expiry queue*).

---

## 2. Pemrosesan Terjadwal Kedaluwarsa Antrean (Scheduled Cron Job)
### 2.1. Jadwal Eksekusi
- **Perintah:** `php artisan venue:expire-waitlist` (dijalankan setiap 1 menit via penjadwal kernel Laravel).
- **Target Operasi:** Memeriksa seluruh baris pada tabel `ven_ticket_waitlists` yang memiliki status `OFFERED` dan stempel waktu `offer_expires_at <= now()`.

### 2.2. Prosedur Otomatis
1. Ambil seluruh penawaran yang telah melewati batas 15 menit (900 detik).
2. Perbarui status antrean dari `OFFERED` menjadi `EXPIRED`.
3. Nonaktifkan token reservasi pada tabel `ven_waitlist_reservations` dengan menandai status pembatalan.
4. Identifikasi tiket yang batal diambil (`offered_ticket_id`).
5. Jalankan `WaitlistQueueDispatcherService::dispatchNextCandidate()` untuk mengalokasikan tiket ke kandidat dengan skor prioritas tertinggi berikutnya.
6. Kirim pemberitahuan instan ke nomor ponsel pembeli baru.

---

## 3. Prosedur Penanganan Lonjakan Lalu Lintas (Flash-Sale Traffic Spike)
Ketika bursa sekunder atau penjualan paket bundle dinamis dibuka untuk artis internasional berstatus *tier-1*:
1. **Peningkatan Skala Worker:**
   Tingkatkan jumlah *queue worker* untuk antrean `venue-secondary` minimal menjadi 8 instance konkurensi.
2. **Pemantauan Cache Redis:**
   Pastikan latensi pembacaan kunci pemicu fingerprint bot `ven_scalper_fp_*` tetap di bawah 2 milidetik.
3. **Verifikasi Kunci Idempotensi:**
   Pastikan seluruh permintaan alokasi bundle menyertakan header `Idempotency-Key` untuk mencegah pemotongan ganda kartu debit/kredit pembeli.

---

## 4. Runbook Pemulihan Kegagalan Escrow (Escrow Reconciliation Incident)
Jika terjadi pemadaman koneksi gateway pembayaran mitra saat dana telah ditarik namun tiket belum berpindah status ke `SETTLED`:
1. SRE memeriksa tabel `ven_ticket_resales` untuk listing dengan status `ESCROW_HOLD` yang berdurasi $> 30$ menit.
2. Bandingkan ID transaksi penampungan dengan log transaksi gateway pembayaran.
3. Bila dana telah terbukti masuk ke rekening penampungan Superweb namun barcode baru gagal diterbitkan:
   - Jalankan `php artisan venue:reconcile-escrow --resale-id=<ID> --force-settle`.
4. Bila pembayaran di gateway gagal atau dibatalkan sepihak oleh bank penerbit:
   - Jalankan perintah kompensasi pembatalan:
     `php artisan venue:reconcile-escrow --resale-id=<ID> --release-to-listed`.
   - Tiket secara otomatis kembali aktif sebagai listing yang tersedia untuk pembeli lain.

