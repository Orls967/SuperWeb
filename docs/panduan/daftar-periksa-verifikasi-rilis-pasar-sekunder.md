# Daftar Periksa Verifikasi Rilis Pasar Sekunder (Fase 116)

## 1. Ikhtisar Verifikasi Pra-Rilis
Sebelum membuka bursa pasar sekunder tiket dan penjualan paket dynamic bundling ke lingkungan produksi (*production release*), manajer rilis dan staf penjamin mutu wajib mengonfirmasi seluruh butir pemeriksaan di bawah ini:

---

## 2. Matriks Pemeriksaan Fungsional & Kepatuhan

- [ ] **1. Integritas Skema Basis Data:** Seluruh tabel `ven_ticket_resales`, `ven_ticket_waitlists`, `ven_dynamic_bundle_packages`, `ven_waitlist_reservations`, `ven_scalper_blacklist_logs`, dan `ven_secondary_ledger_entries` telah termigrasi dengan foreign key constraint yang valid.
- [ ] **2. Batas Keras Anti-Scalping 120%:** Pengujian percobaan pendaftaran tiket dengan harga $121\%$ dari nilai nominal tertolak seketika oleh `AntiScalpingCapEngine`.
- [ ] **3. Aturan Single Transfer Limit:** Tiket yang telah sukses berstatus `SETTLED` ditolak jika didaftarkan kembali ke bursa sekunder untuk kedua kalinya.
- [ ] **4. Keabsahan Token Barcode Berotasi:** Kolom `new_ticket_hash` terisi string hex 64 karakter valid, dan barcode tiket asli dinonaktifkan pada mesin pemindai gerbang.
- [ ] **5. Proteksi Batas Bawah Dynamic Bundling:** Paket bundling dengan diskon ekstrem tidak pernah dijual di bawah nilai `floor_price_idr`.
- [ ] **6. Jendela Waktu Kedaluwarsa Antrean Waitlist:** Penawaran tiket antrean otomatis kedaluwarsa setelah tepat 15 menit (900 detik) dan dialihkan ke kandidat berikutnya.
- [ ] **7. Konservasi Akuntansi Tertutup:** Total alokasi PBJT (10%), platform fee (8%), royalti promotor (2%), dan pencairan penjual (80%) identik dengan nilai transaksi bruto tanpa selisih pecahan rupiah.
- [ ] **8. Kesiapan Perintah Audit:** Eksekusi `php artisan venue:audit-secondary` berhasil dengan status hijau (`Exit 0`).
- [ ] **9. Responsivitas Tampilan Pengguna (P-UI):** Tampilan bursa sekunder, penjualan tiket, pesanan pembeli, dynamic bundle builder, antrean waitlist, dan laporan ekonomi tampil sempurna pada lebar layar ponsel 375 px dan desktop.
- [ ] **10. Ketiadaan Pelanggaran Arsitektur:** Pemindaian `php artisan arch:scan` mengonfirmasi 0 Pelanggaran Baru dan 0 Pelanggaran Basi.

