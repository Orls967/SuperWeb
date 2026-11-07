# Pedoman Lengkap Integrasi Katalog Voucher Belanja Mall & F&B Ekosistem — Fase 112

## Bab 1: Sinergi Ritel & Gaya Hidup Modern (Mall & Lifestyle Hub)
Program Global Travel Pass melampaui batas perhotelan konvensional dengan menghadirkan katalog voucher belanja yang dapat digunakan di jaringan pusat perbelanjaan (modul Mall) dan gerai makanan dan minuman ekosistem. Tamu yang menginap dapat menukarkan poin reward mereka menjadi kupon diskon instan atau voucher belanja elektronik.

### Kategori Voucher Ritel yang Tersedia:
1. **Voucher Belanja Department Store Resor:**
   Dapat digunakan di butik-butik fesyen terpilih di area arcade hotel bintang lima.
2. **Kupon Santap Restoran Fine Dining:**
   Diskon potongan tagihan langsung sebesar 20% hingga 50% di restoran mitra ternama.
3. **Voucher Tiket Wahana Hiburan Keluarga:**
   Akses tiket masuk taman rekreasi air, bioskop IMAX, dan arena bowling di pusat perbelanjaan rekanan.

---

## Bab 2: Mekanisme Verifikasi & Validasi Kasir Ritel
1. Kasir gerai mall memindai kode QR digital pada aplikasi ponsel tamu.
2. Sistem gateway memeriksa keabsahan voucher melalui panggilan API aman ke endpoint `/api/v1/hotel/vouchers/verify`.
3. Bila voucher valid:
   - Nilai voucher langsung dipotong dari total belanja kotor.
   - Status voucher diubah menjadi `REDEEMED`.
   - Struk kasir mencetak nomor referensi unik dan rincian pemotongan loyalitas.

---

## Bab 3: Penyelesaian Keuangan Antar-Entitas (Inter-Company Settlement)
Penebusan voucher di gerai mitra menimbulkan kewajiban penggantian dana (reimbursement) dari kas entitas hotel kepada penyewa gerai mall (tenant):
- Nilai voucher yang ditebus ditampung pada akun kliring `clearing:external:IDR`.
- Setiap tanggal 15 dan akhir bulan, laporan rekapitulasi penukaran diterbitkan secara otomatis dan dana ditransfer ke rekening bank penyewa melalui modul Perbankan.

