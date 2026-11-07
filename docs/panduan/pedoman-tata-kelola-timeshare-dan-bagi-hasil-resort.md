# Pedoman Tata Kelola Hak Kepemilikan Fraksional (Timeshare) & Bagi Hasil Rental Pool

## 1. Maksud & Tujuan Pedoman
Pedoman ini mengatur hak, kewajiban, tata cara penggunaan alokasi minggu menginap (*Owner Allocation Weeks*), partisipasi dalam program sewa kolektif (*Rental Pool Yield Distribution*), serta pemotongan iuran pemeliharaan properti (*Sinking Fund & Maintenance Fee*) bagi para pemilik unit vila resort fraksional.

---

## 2. Struktur Tokenisasi Unit & Alokasi Minggu Hak Guna
Setiap unit vila mewah dipecah menjadi **100 lembar token fraksional (*Fractional Token Shares*)**:
- 1 lembar token merepresentasikan hak kepemilikan 1.0% atas nilai aset dan pendapatan sewa.
- Kalender operasional tahunan dibagi menjadi **52 minggu giliran (Week 1 s/d Week 52)**.
- Setiap minggu diklasifikasikan ke dalam musim kunjungan:
  - **Musim Puncak (Peak Season):** Libur Idul Fitri, Libur Natal & Tahun Baru, serta Juli-Agustus (Total 14 Minggu).
  - **Musim Liburan (Shoulder Season):** Akhir pekan panjang dan libur sekolah (Total 16 Minggu).
  - **Musim Reguler (Regular Season):** Hari kerja di luar musim liburan (Total 22 Minggu).

```
+-------------------------------------------------------------------------------+
|               KLASIFIKASI MUSIM & RASIO HAK PENGGUNAAN VILA                   |
+--------------------+---------------------+------------------+-----------------+
| Kategori Musim     | Rentang Minggu      | Rasio Poin Tukar | Peminatan Sewa  |
+--------------------+---------------------+------------------+-----------------+
| PEAK SEASON        | W26-34, W51-52      | 1.50x Poin       | Sangat Tinggi   |
| SHOULDER SEASON    | W14-25, W35-42      | 1.00x Poin       | Menengah        |
| REGULAR SEASON     | W01-13, W43-50      | 0.75x Poin       | Moderat         |
+--------------------+---------------------+------------------+-----------------+
```

---

## 3. Pilihan Pemanfaatan Hak Guna Pemilik
Paling lambat 60 hari kalender sebelum permulaan kuartal berjalan, setiap pemilik fraksional dapat memilih satu dari tiga opsi pemanfaatan:

1. **Pemakaian Pribadi (Owner Occupied):**
   - Pemilik menempati unit vila secara eksklusif bersama keluarga tanpa dikenakan biaya sewa kamar harian.
   - Pemilik hanya membayar biaya pembersihan harian (*Housekeeping Turnover Surcharge*).
2. **Penyertaan ke Kolam Sewa Hotel (Rental Pool Program):**
   - Hak menginap diserahkan kepada pihak manajemen hotel untuk disewakan kepada tamu publik dengan tarif komersial harian.
   - Hasil sewa kotor yang diperoleh dikumpulkan ke dalam rekening penampungan *Rental Pool Gross Yield*.
3. **Penukaran Pasar Sekunder (Secondary Exchange Pool):**
   - Pemilik menukarkan jatah minggunya dengan pemilik vila lain pada jaringan resort terafiliasi melalui mekanisme swap cerdas.

---

## 4. Formula Perhitungan Dividen & Biaya Pemeliharaan
Bagi unit yang didaftarkan ke dalam *Rental Pool*, dividen bersih dibagikan secara bulanan mengikuti algoritma H8 (*FractionalUsageScheduler*):

$$\text{Gross\_Income} = \sum (\text{Tarif\_Sewa\_Harian} \times \text{Jumlah\_Malam\_Tersewa})$$

$$\text{Resort\_Management\_Fee} = \text{Gross\_Income} \times 15.0\% \text{ (1500 bps)}$$

$$\text{Net\_Distributable\_Pool} = \text{Gross\_Income} - \text{Resort\_Management\_Fee}$$

$$\text{Dividen\_Investor}_i = \text{Net\_Distributable\_Pool} \times \left( \frac{\text{Token\_Shares}_i}{100} \right)$$

### 4.1. Pencatatan ke Buku Besar Keuangan
Setiap nilai dividen bersih yang dihasilkan langsung dipindahbukukan ke buku besar liabilitas investor bersangkutan:
- **Debit:** `htl:revenue:rental_pool_clearing:IDR` (Penyisihan Pendapatan Pool)
- **Kredit:** `htl:revenue:management_fee:IDR` (Hak Fee Manajemen Resort 15%)
- **Kredit:** `investor:dividend:{uuid}:IDR` (Hak Bersih Dividen Pemegang Saham)
