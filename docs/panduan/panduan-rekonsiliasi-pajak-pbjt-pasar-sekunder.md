# Panduan Rekonsiliasi Pajak PBJT Pasar Sekunder

## 1. Landasan Hukum Fiskal Daerah
Penyelenggaraan pertunjukan musik, kesenian, dan hiburan komersial di wilayah hukum Negara Kesatuan Republik Indonesia tunduk pada pemungutan **Pajak Barang dan Jasa Tertentu (PBJT) atas Jasa Kesenian dan Hiburan** sesuai amanat Undang-Undang Republik Indonesia Nomor 1 Tahun 2022 tentang Hubungan Keuangan antara Pemerintah Pusat dan Pemerintahan Daerah (UU HKPD) serta Peraturan Daerah (Perda) kabupaten/kota setempat.

Tarif umum yang berlaku bagi jasa hiburan reguler adalah **10% dari jumlah yang dibayarkan oleh konsumen**.

Pada bursa pasar sekunder tiket (*secondary ticket resale*), peredaran tiket seringkali menimbulkan ketidakjelasan subjek dan objek pajak apabila transaksi berlangsung di bawah tangan (*black market*). Superweb Venue menjamin kepatuhan fiskal penuh dengan memotong secara otomatis kewajiban pajak PBJT 10% pada saat transaksi sekunder berhasil diselesaikan (*settlement*).

---

## 2. Metodologi Perhitungan & Penyetoran Pajak Daerah
### 2.1. Dasar Pengenaan Pajak (DPP)
Dasar Pengenaan Pajak PBJT pada pasar sekunder adalah nilai nominal bruto transaksi penjualan kembali tiket yang dibayarkan oleh pembeli akhir:
$$\text{DPP} = \text{Harga Transaksi Bruto Pasar Sekunder (IDR)}$$

### 2.2. Formula Pemotongan Pajak PBJT
Pajak PBJT dihitung menggunakan pembagian bulat integer 1.000 basis points:
$$\text{Pajak PBJT IDR} = \left\lfloor \frac{\text{DPP} \times 1000}{10000} \right\rfloor$$

Contoh Perhitungan:
- Nilai Transaksi Penjualan Tiket Sekunder: Rp 1.650.000
- Nilai Pajak PBJT (10%): $\lfloor \frac{1.650.000 \times 1000}{10000} \rfloor = \text{Rp } 165.000$
- Nilai Komisi Platform (8%): $\lfloor \frac{1.650.000 \times 800}{10000} \rfloor = \text{Rp } 132.000$
- Nilai Royalti Promotor (2%): $\lfloor \frac{1.650.000 \times 200}{10000} \rfloor = \text{Rp } 33.000$
- Pencairan Bersih Penjual: $1.650.000 - (165.000 + 132.000 + 33.000) = \text{Rp } 1.320.000$

Jumlah seluruh komponen: $165.000 + 132.000 + 33.000 + 1.320.000 = \text{Rp } 1.650.000$ (Konservasi Sempurna).

### 2.3. Perbandingan Tarif Pasar Sekunder Global vs Indonesia
Sebagai perbandingan komparatif dengan regulasi internasional:
- **Pasar Sekunder Inggris & Uni Eropa:** Menerapkan pembatasan harga maksimal $110\%$ hingga $120\%$ dari nilai nominal dan mewajibkan pelaporan Pajak Pertambahan Nilai (PPN / VAT).
- **Pasar Sekunder Amerika Serikat:** Sebagian besar negara bagian menerapkan lisensi khusus calo tiket dan pengenaan pajak penjualan daerah (*sales tax*).
- **Pendekatan Superweb Venue Indonesia:** Menggabungkan batasan harga ketat ($120\%$), perlindungan hak cipta promotor ($2\%$), dan pemungutan langsung pajak daerah PBJT ($10\%$).

---

## 3. Prosedur Pelaporan SPT Masa Pajak Daerah (Bapenda)
Tim akuntansi dan kepatuhan fiskal venue menjalankan prosedur bulanan berikut:
1. Buka konsol pelaporan via menu **Pasar Sekunder > Laporan Ekonomi**.
2. Pilih periode bulan dan tahun pelaporan target.
3. Jalankan rekonsiliasi data: sistem mengeksekusi `SecondaryMarketTaxReconciliationService::reconcilePeriod()`.
4. Sistem memverifikasi ketiadaan varians pembulatan (`is_conservation_balanced = true` dan `checksum_variance_idr = 0`).
5. Unduh rekapitulasi Surat Pemberitahuan Pajak Daerah (SPTPD) Masa Hiburan berformat CSV/PDF resmi.
6. Lakukan pembayaran billing e-PBB/e-PBJT ke rekening Kas Umum Daerah Pemerintah Kabupaten/Kota tempat venue beroperasi.
