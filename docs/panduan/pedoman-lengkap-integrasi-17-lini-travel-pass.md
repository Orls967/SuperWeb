# Pedoman Lengkap Integrasi 17 Lini Ekosistem pada Program Global Travel Pass — Fase 112

## Bab 1: Filosofi & Landasan Ekosistem Terpadu
Program Global Travel Pass dirancang untuk menjembatani 17 lini bisnis dalam konglomerasi perhotelan dan gaya hidup modern. Tidak seperti program loyalitas maskapai atau hotel konvensional yang berdiri sendiri dalam silo tertutup, Global Travel Pass beroperasi sebagai mata uang loyalitas tunggal (PTS) yang mengikat setiap interaksi pelanggan di seluruh portofolio usaha:

1. **Lini Perhotelan (Hotel & Resort):** Reservasi kamar, suite presidensial, vila pribadi, spa, dan fasilitas rekreasi.
2. **Lini MICE & Konvensi (Venue & Convention Center):** Penyewaan ruang pertemuan, ballroom, pesta pernikahan, dan pameran dagang internasional.
3. **Lini Restoran & Kuliner (Resto & Fine Dining):** Gerai makan mewah, kafe kasual, lounge atap (rooftop), dan bar tepi pantai.
4. **Lini Hiburan & Acara (Entertainment & Nightlife):** Klub malam festival (modul Venue), tiket konser musik, dan lounge VIP.
5. **Lini Transportasi & Logistik (Aviation & Mobility):** Penjemputan limusin bandara, tiket penerbangan aliansi mitra, dan armada antar-jemput kota.
6. **Lini Kesehatan & Wellness (Hospital & Medical Tourism):** Pemeriksaan kesehatan preventif, paket medical tourism, terapi pemulihan, dan retret kebugaran.
7. **Lini Ritel & Gaya Hidup (Retail & Mall):** Belanja barang mewah di butik resor, suvenir eksklusif, dan produk kerajinan kurasi.
8. **Lini Olahraga & Petualangan (Sports & Marina):** Lapangan golf kejuaraan, penyewaan kapal pesiar (yacht), instruktur menyelam, dan tur helikopter.
9. **Lini Properti & Timeshare (Fractional Ownership):** Hak inap tahunan vila fraksional, penyewaan pool sewa bersama, dan dividen token.
10. **Lini Keuangan & Fintech (Banking & Dompet Digital):** Pembayaran nirsentuh QRIS, konversi mata uang asing, dan kartu co-brand platinum.
11. **Lini Edukasi & Konferensi Akademik (Education & Masterclass):** Kursus memasak masterclass chef, lokakarya sommelier anggur, dan retret kepemimpinan eksekutif.
12. **Lini Agrikultur & Farm-to-Table (Agri):** Kunjungan kebun organik resor, santapan langsung dari kebun ke meja, dan kebun teh pegunungan.
13. **Lini Maritim & Kapal Pesiar (Marine Cruise):** Pelayaran pulau-ke-pulau, tur lumba-lumba, dan makan malam matahari terbenam di atas kapal katamaran.
14. **Lini Energi & Ramah Lingkungan (Clean Energy & EV):** Pengisian daya kendaraan listrik di resor, kredit karbon hijau terverifikasi, dan inisiatif tanpa emisi.
15. **Lini Seni & Galeri Budaya (Art & Media):** Pameran lukisan kontemporer di lobi hotel, lelang karya seni rupa, dan hak karya digital.
16. **Lini Co-working & Bisnis Nomaden (Co-working & Business Club):** Ruang kerja fleksibel di pusat bisnis resor, akses ruang rapat privat, dan internet satelit berkecepatan tinggi.
17. **Lini Komunitas & Keberlanjutan (ESG & Charity):** Donasi poin untuk konservasi terumbu karang lokal, penanaman mangrove pesisir, dan beasiswa pariwisata pemuda desa.

---

## Bab 2: Mekanisme Perolehan Poin (Earning Engine)
Setiap transaksi yang terjadi pada salah satu dari 17 lini ekosistem di atas memicu event terstandarisasi `PointsAccruedFromEcosystemEvent`. Parameter perhitungan earn points mengikuti rumus dasar terpusat:
$$\text{PTS Earned} = \left\lfloor \frac{\text{Gross Spend (IDR)}}{1.000} \cdot \beta_{line} \cdot \gamma_{tier} \right\rfloor$$
di mana:
- $\beta_{line}$: koefisien bobot lini usaha (tabel di bawah).
- $\gamma_{tier}$: pengali tier anggota (Silver = 1.0, Gold = 1.25, Platinum = 1.5, Black = 2.0).

### Tabel Koefisien Bobot Lini Usaha ($\beta_{line}$)
| No | Kode Lini | Kategori Bisnis | Koefisien $\beta$ | Keterangan Aturan Bisnis |
|---|---|---|---|---|
| 1 | `HTL_ROOM` | Kamar Hotel & Resor | 1.00 | Tarif kamar dasar sebelum pajak & service charge |
| 2 | `HTL_FNB` | Makanan & Minuman Inap | 1.20 | Konsumsi restoran hotel & room service |
| 3 | `VEN_TICKET` | Tiket Festival & Venue | 1.50 | Tiket konser, beach club, tiket masuk festival |
| 4 | `VEN_VIP` | Meja VIP & Botol Klub | 1.75 | Paket sofa VIP dan pesanan botol di lounge |
| 5 | `RST_DINE` | Restoran Rekanan Ekosistem | 1.10 | Santapan di luar properti hotel utama |
| 6 | `SPA_TREAT` | Perawatan Spa & Kebugaran | 1.30 | Paket pijat tradisional, hidroterapi, sauna |
| 7 | `MED_CHECK` | Wisata Medis & Checkup | 1.25 | Pemeriksaan laboratorium ringan & konsultasi |
| 8 | `MOB_TRANS` | Antar-Jemput & Rental | 1.00 | Transportasi bandara berlisensi ekosistem |
| 9 | `RET_BOUT` | Butik Ritel & Suvenir | 0.80 | Pembelian barang fesyen & kerajinan lokal |
| 10 | `GOLF_REC` | Golf, Marina & Yacht | 1.40 | Green fee, sewa stik golf, sewa kapal harian |
| 11 | `TIME_RENT` | Sewa Fraksional Timeshare | 1.10 | Poin atas biaya pengelolaan pemeliharaan vila |
| 12 | `CRUISE_ISL`| Wisata Kapal Pesiar | 1.35 | Tur katamaran & pelayaran senja |
| 13 | `MICE_CONF` | Konvensi & Banquet B2B | 0.90 | Pembayaran tagihan MICE skala korporat |
| 14 | `AGRI_TOUR` | Tur Kebun Organik | 1.20 | Tiket workshop perkebunan & petik buah |
| 15 | `EV_CHARGE` | Pengisian Daya Mobil EV | 1.50 | Pengisian daya listrik di SPKLU hotel |
| 16 | `WORK_DESK` | Sewa Meja Co-working | 1.00 | Jam akses hot-desk dan bilik telekonferensi |
| 17 | `ESG_DONAT` | Donasi Konservasi Alam | 2.00 | Apresiasi ganda atas kontribusi keberlanjutan |

---

## Bab 3: Matriks Penukaran Terpusat (Centralized Redemption Matrix)
Poin loyalitas PTS yang telah terkumpul dapat dibelanjakan secara bebas di seluruh lini tanpa diskriminasi lokasi perolehan. Matriks penukaran menjamin bahwa nilai setiap 1 poin PTS memiliki daya beli minimum sebesar Rp 100 ketika ditukarkan:

1. **Penukaran Kamar Bebas Biaya (Dynamic Award Nights):**
   - Menghitung tarif poin secara instan dengan algoritma `AwardNightPricer` (H11).
   - Memasukkan preferensi kamar bebas asap rokok, lantai tinggi, dan pilihan bantal secara otomatis.
2. **Penebusan Pengalaman Makan (Dining Voucher):**
   - Voucher Rp 100.000 dapat ditebus dengan 1.000 PTS.
   - Tanpa blackout date pada restoran milik jaringan.
3. **Penebusan Tiket Festival & Akses Masuk VIP:**
   - Akses antrean cepat (fast-track lane) di festival musik: 2.500 PTS.
   - Penebusan tiket konser festival: sesuai nilai konversi nominal tiket.
4. **Paket Spa & Pemulihan Stamina:**
   - Aromatherapy Massage 60 Menit: 4.500 PTS.
   - Detox Body Scrub & Herbal Bath: 3.500 PTS.
5. **Transfer Miles Aliansi Maskapai:**
   - Dikonversi via algoritma `PartnerPointConverter` (H12) dan saga transaksi atomik.

---

## Bab 4: Manajemen Liabilitas & Risiko Inflasi Poin
Program loyalitas skala besar rentan terhadap hiperinflasi bila poin dicetak tanpa batasan liabilitas keuangan. Sistem Fase 112 menerapkan protokol pengendalian ketat:
1. **Penerbitan Batch Bertanggal (Dated Batching):** Setiap poin terikat pada batch yang memiliki stempel waktu akrual dan masa aktif tepat 24 bulan (730 hari kalender).
2. **Pencegahan Akumulasi Tak Terbatas:** Sistem mengirimkan notifikasi pengingat kedaluwarsa kepada tamu pada H-60, H-30, dan H-7 sebelum batch hangus.
3. **Pencatatan Liabilitas Berpasangan:** Setiap penambahan 1.000 PTS langsung mendebit akun beban operasional dan mengkredit akun liabilitas poin di neraca keuangan, sehingga tidak ada kewajiban tak tercatat (unfunded liability).
4. **Pengakuan Breakage yang Konsisten:** Poin kedaluwarsa dibukukan secara transparan sebagai pendapatan lain-lain breakage, memberikan kejelasan laba-rugi bagi pemegang saham.

