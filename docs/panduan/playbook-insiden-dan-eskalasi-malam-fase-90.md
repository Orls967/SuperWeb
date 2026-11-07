# Playbook Operasional, Mitigasi Risiko & Eskalasi Insiden Night Economics (Fase 90)

**Domain:** Venue & Entertainment (`modules/Venue/`, sub-item 90.1 s/d 90.9)  
**Terkait Modul:** Hotel (`modules/Hotel/`), HCM (`modules/Hcm/`), Trade (`modules/Trade/`), WMS  
**Persona Target:** Venue General Manager, Night Operations Director, Bar Captain, Head of Security, Controller Keuangan  
**Klasifikasi:** Standar Operasional Tanggap Darurat & Mitigasi Risiko Finansial  
**Versi:** 1.0.0-PROD  

---

## 1. Pendahuluan & Filosofi Penanganan Insiden

Operasi hiburan malam skala masif (*high-volume beach clubs* dan *multi-day music festivals*) memiliki dinamika volatilitas tinggi di mana gangguan fisik, kegagalan telemetri, sengketa artis, atau lonjakan pengunjung dapat berdampak langsung pada kerugian moneter dan reputasi brand.

Filosofi tata kelola insiden Fase 90 berlandaskan pada tiga prinsip utama:
1. **Preservasi Keselamatan Jiwa (*Life Safety First*):** Tidak ada aset moneter, target sponsor, atau performa artis yang boleh mengorbankan kapasitas aman dan rute evakuasi tamu.
2. **Integritas Konservasi Moneter (*Zero Financial Leakage*):** Setiap penyesuaian darurat, diskon goodwill, atau kompensasi wajib memiliki jejak audit kriptografis dan berpasangan seimbang di buku besar akrual.
3. **Pemisahan Otoritas (*Dual Control & Four-Eyes Principle*):** Eskalasi yang melibatkan penyesuaian nominal di atas Rp 25.000.000 wajib melibatkan otorisasi ganda antara Operational Lead dan Financial Controller.

---

## 2. Matriks RACI Tanggap Darurat Night Economics

| Skenario Insiden | General Manager | Bar Captain | Head of Security | Financial Controller | Venue Lead Dev |
|---|---|---|---|---|---|
| **Sengketa Door Split Artis** | Accountable | Informed | Consulted | Responsible | Consulted |
| **Spillage Bar Kritis (>5%)** | Informed | Responsible | Consulted | Accountable | Informed |
| **Kegagalan Telemetri Sponsor** | Consulted | Informed | Informed | Accountable | Responsible |
| **Kekurangan Staf & Lonjakan Tamu** | Accountable | Responsible | Consulted | Informed | Informed |
| **Kegagalan Verifikasi Membership** | Informed | Consulted | Consulted | Informed | Responsible |
| **Overbooking Hotel Paket Festival** | Accountable | Informed | Informed | Responsible | Consulted |

---

## 3. Prosedur Operasional Standar (SOP) Penanganan Skenario Insiden

### Skenario 1: Sengketa Bagi Hasil Pintu Artis (*Artist Door Split Conflict*)
- **Pemicu:** Manajer artis menolak hasil kalkulasi bersih dan mengklaim potongan biaya produksi panggung (*stage production deduction*) terlalu besar.
- **Langkah Aksi Segera:**
  1. Jalankan verifikasi sertifikat matematis V5:
     ```bash
     php artisan venue:audit-economy --focus=artist
     ```
  2. Buka tampilan laporan pernyataan artis (`artists/statement.blade.php`) dan bandingkan nilai penjualan kotor tiket pintu (*gross door receipts*) terhadap data riil pemindaian gerbang turnstile.
  3. Periksa bukti invoice biaya produksi yang telah diunggah ke subledger rider (`ven_artist_riders`).
  4. Jika terdapat perselisihan biaya rider tak terduga, pisahkan pembayaran menjadi dua termin:
     - Termin A (*Undisputed Amount*): Minimum guaranteed fee dan porsi bagi hasil tak bersengketa ditransfer seketika melalui `SettleArtistEngagementAction`.
     - Termin B (*Disputed Retainer*): Nominal sengketa ditahan pada akun escrow `ven:artist_retention_payable:IDR` selama maksimal 5 hari kerja mediasi.

---

### Skenario 2: Anomali Spillage & Kebocoran Stok Bar Kritis (*Severe Bar Shrinkage*)
- **Pemicu:** Rekapitulasi pergantian shift jam 03:00 pagi menunjukkan persentase kehilangan cairan spirit spirit premium melampaui $5,00\%$ pada Main Beach Bar.
- **Langkah Aksi Segera:**
  1. Bar Captain segera membekukan terminal POS bar bersangkutan dan mencatat pembacaan fisik botol terbuka.
  2. Eksekusi action audit darurat:
     ```bash
     php artisan venue:audit-economy --focus=bar
     ```
  3. Petugas sekuriti memeriksa rekaman CCTV stasiun bartender selama jam sibuk (23:00–02:00) untuk mendeteksi *free-pouring*, unauthorized complimentary drinks, atau botol pecah tak tercatat.
  4. Bila terbukti terjadi kelalaian penanganan staf:
     - Biaya spillage di atas toleransi wajar $3,00\%$ dialihkan dari akun beban operasional ke piutang ganti rugi karyawan via modul HCM.
     - Botol spirit pengganti diminta dari mini-warehouse utama menggunakan modul WMS cold-chain.

---

### Skenario 3: Kerusakan Videotron & Telemetri Tayang Sponsor (*Sponsor Display Outage*)
- **Pemicu:** Layar LED panggung utama mengalami gangguan sinyal HDMI selama 45 menit saat penampilan musisi pembuka, menyebabkan iklan sponsor eksklusif tidak tayang.
- **Langkah Aksi Segera:**
  1. Teknisi panggung segera mengalihkan feed visual ke backup controller darurat.
  2. Lead Engineer mencatat *downtime window* presisi dalam detik pada log kejadian sistem.
  3. Algoritma V8 (`SponsorExposureValuator`) secara otomatis mendeteksi penurunan impresi terbobot pada evaluasi milestone termin sponsorship.
  4. Tim Kemitraan Merek mengeksekusi rencana kompensasi (*Make-Good Plan*):
     - Menambah frekuensi slot tayang sponsor sebesar $150\%$ pada panggung festival kedua.
     - Menyediakan push notification sponsor promosi gratis pada Guest Portal Membership SuperWeb.
     - Menerbitkan memo audit eksposur revisi yang ditandatangani bersama perwakilan sponsor.

---

### Skenario 4: Lonjakan Kerumunan Mendadak & Kebutuhan Shift Bounty HCM (*Crowd Surge & Staff Bounty*)
- **Pemicu:** Arus kedatangan tamu melonjak hingga $180\%$ dari kapasitas proyeksi awal akibat promosi viral di media sosial, memicu antrean panjang pada gerbang dan stasiun bar.
- **Langkah Aksi Segera:**
  1. Venue Manager mengaktifkan protokol eskalasi tenaga kerja darurat via kontrak `ShiftBountyPublisher` (G7-K18):
     - Membuka 15 slot barista dan floor staff cadangan dengan insentif *bounty surge* $1,5x$ tarif normal.
     - Notifikasi darurat dikirim otomatis ke seluruh staf off-duty bersertifikasi di modul HCM.
  2. Gate Governor secara otomatis membatasi laju masuk (*throttling*) pada $95\%$ kapasitas maksimum zona untuk mencegah bahaya desak-desakan (*crowd crush*).
  3. Staf keamanan mengarahkan kelebihan antrean ke holding lounge terpisah dengan sajian welcome drink gratis untuk meredakan ketegangan pengunjung.

---

## 4. Checklist Penutupan Shift Malam & Handover Keuangan Pagi

Sebelum tim operasional malam meninggalkan lokasi dan serah terima kepada tim pagi dilakukan, 7 item verifikasi wajib diselesaikan:

1. [ ] Seluruh terminal POS bar telah di-close batch dan settlement pembayaran non-tunai telah sesuai $100\%$ dengan log clearing bank/e-wallet.
2. [ ] Formulir `BarShiftLog` telah diisi lengkap dengan pembacaan milimeter sisa cairan spirit per botol terbuka.
3. [ ] Botol penitipan tamu VIP telah disegel lak tamper-evident dan terdaftar di rak loker `AssignBottleLockerAction`.
4. [ ] Seluruh artis pengisi acara telah menandatangani lembar konfirmasi performa (*performance delivery voucher*).
5. [ ] Telemetri footfall turnstile gerbang telah diunggah lengkap ke cloud subledger sponsor.
6. [ ] Command `php artisan venue:audit-economy` dieksekusi dengan output: **0 discrepancies (HEALTHY)**.
7. [ ] Berita Acara Rekonsiliasi Kas & Subledger ditandatangani oleh Bar Captain dan Night Auditor Keuangan.

Playbook ini berlaku efektif di seluruh unit properti hiburan SuperWeb dan wajib dilatihkan secara berkala dalam simulasi meja (*tabletop exercise*) setiap kuartal.
