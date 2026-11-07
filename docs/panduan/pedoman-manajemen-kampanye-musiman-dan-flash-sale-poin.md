# Pedoman Manajemen Kampanye Musiman & Flash Sale Penukaran Poin — Fase 112

## Bab 1: Strategi Kampanye Musiman (Seasonal Promotional Campaigns)
Untuk meningkatkan partisipasi anggota dan mengoptimalkan tingkat okupansi hotel pada periode sepi (low season), Manajer Loyalitas memiliki wewenang untuk merancang kampanye promosi musiman dan penjualan kilat (flash sale) penukaran poin reward.

### Bentuk-Bentuk Kampanye Promosi:
1. **Flash Sale Award Night 50% Points:**
   Penawaran kamar hotel tertentu dengan diskon tarif poin hingga 50% selama jendela pemesanan terbatas (misal 24 jam). Proteksi batas bawah floor rate 115% tetap dijaga oleh subsidi anggaran promosi pemasaran kantor pusat.
2. **Poin Ganda Musim Liburan (Double Points Summer Holiday):**
   Penggandaan perolehan poin menjadi 2x lipat untuk seluruh transaksi kamar dan santapan yang diselesaikan selama periode bulan Juni hingga Agustus.
3. **Bonus Transfer Aliansi Maskapai (Airline Transfer Bonus):**
   Bonus tambahan 20% miles penerbangan untuk setiap transfer poin hotel yang dilakukan pada pekan kemitraan aliansi.

---

## Bab 2: Parameter Konfigurasi & Batasan Kuota
Setiap kampanye promosi wajib dikonfigurasi dengan batas pengaman finansial yang jelas:
- **Batas Kuota Total Poin Diskon:** Total potongan poin yang diberikan tidak boleh melampaui plafon anggaran promosi yang telah disetujui (misal maksimal 5.000.000 PTS per kampanye).
- **Batas Maksimal per Anggota:** Setiap anggota dibatasi menukarkan maksimal 2 kamar promosi untuk mencegah penimbunan (hoarding) oleh calo atau akun perantara.
- **Jadwal Pelaksanaan Ketat:** Penjualan dimulai dan diakhiri secara otomatis oleh cron scheduler pada waktu yang telah ditentukan (WITA/WIB).

---

## Bab 3: Evaluasi Efektivitas Kampanye (ROI Analysis)
Setelah kampanye berakhir, modul Hotel menghasilkan laporan analisis kinerja:
- Jumlah anggota yang berpartisipasi dan perincian tier (Silver, Gold, Platinum, Black).
- Tambahan okupansi kamar yang berhasil diisi di luar estimasi baseline organik.
- Rata-rata belanja non-kamar (ancillary spend) yang dihasilkan oleh tamu peserta kampanye selama menginap.

