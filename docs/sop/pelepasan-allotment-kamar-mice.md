# SOP: Prosedur Pelepasan Allotment Kamar Blok MICE & Wedding

## 1. Tujuan
Memastikan utilisasi maksimal inventaris kamar hotel dan meminimalkan kerugian kamar kosong akibat keterlambatan atau pembatalan delegasi acara, dengan menerapkan tahapan pelepasan kuota secara otomatis dan transparan kepada pihak penyelenggara acara.

## 2. Definisi Istilah
- **Contracted Allotment:** Jumlah kamar yang dipesan dan ditahan untuk kelompok acara tertentu berdasarkan SPK.
- **Rooming List:** Daftar nama tamu delegasi beserta preferensi kamar yang diserahkan oleh penyelenggara acara.
- **Pickup Count:** Jumlah kamar yang telah berhasil dialokasikan kepada tamu tertentu yang terdaftar di sistem PMS.
- **Cutoff Date:** Tanggal batas akhir pengumpulan nama tamu sebelum sisa kuota yang tidak terisi dilepaskan kembali ke penjualan publik.

## 3. Tahapan & Jadwal Pelepasan Bertingkat
1. **Peringatan Pra-Cutoff (H-35, H-16, H-9):**
   - Sistem otomasi CRM hotel mengirimkan email dan notifikasi resmi kepada Event Organizer berisi rekapitulasi kuota terisi vs sisa kamar, serta tanggal eksekusi pelepasan terdekat.
2. **Tahap 1: Cutoff H-30 (Pelepasan 25%):**
   - Tepat pada H-30 pukul 23:59 waktu setempat, sistem menghitung sisa kamar unpicked $U_{30} = K_{30} - P_{30}$.
   - Sebesar $\lfloor 0.25 \times U_{30} \rfloor$ kamar dilepaskan secara instan ke sistem reservasi pusat (*CRS*) untuk dijual kepada tamu reguler / online travel agents.
   - Kuota kamar blok dikurangi sebesar jumlah kamar yang dilepas.
3. **Tahap 2: Cutoff H-14 (Pelepasan 50%):**
   - Pada H-14 pukul 23:59, sistem menghitung sisa kamar unpicked yang masih tersisa $U_{14} = K_{14} - P_{14}$.
   - Sebesar $\lfloor 0.50 \times U_{14} \rfloor$ kamar dilepaskan ke inventori publik.
4. **Tahap 3: Final Cutoff H-7 (Pelepasan 100% Sisa):**
   - Pada H-7 pukul 23:59, seluruh sisa kamar blok yang tidak memiliki nama tamu definitif ($U_7 = K_7 - P_7$) dilepaskan 100% ke inventori publik.
   - Blok pemesanan dikunci (*finalized allotment*). Penambahan kamar setelah H-7 tunduk pada ketersediaan kamar hotel saat itu dan berlaku tarif harian terbaik (*Best Available Rate / BAR*), bukan tarif kontrak grup.

## 4. Otorisasi Penundaan Pelepasan (Allotment Extension)
1. Penyelenggara acara dapat mengajukan permohonan penundaan pelepasan kamar maksimal 7 hari kalender dengan syarat:
   - Pembayaran uang muka tambahan sebesar 10% dari total nilai kamar yang ingin diperpanjang masa penahanannya.
   - Persetujuan tertulis dari Director of Revenue Management.
2. Petugas reservasi mencatat perpanjangan cutoff di sistem dengan memilih opsi **Extend Cutoff Date with Guarantee Deposit**.
