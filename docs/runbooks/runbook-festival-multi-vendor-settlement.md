# Runbook Operasional & Panduan Penyelesaian Festival Multi-Vendor (Fase 90)

**Domain:** Venue & Entertainment (`modules/Venue/`, prefiks tabel `ven_`)  
**Target Pembaca:** Chief Financial Officer (CFO), General Manager Venue, Kepala Bar, dan Tim Operasional Acara  
**Klasifikasi:** Dokumen Prosedur Standar (SOP) Gelombang 7  
**Status:** AKTIF & DITERAPKAN  

---

## 1. Ikhtisar Alur Kerja Festival Multi-Vendor
Penyelenggaraan festival berskala besar melibatkan koordinasi teknis dan finansial antara penyelenggara venue (*host entity*) dan berbagai mitra bisnis eksternal:
1. **Mitra Hotel & Glamping Pesisir:** Penyediaan kamar dan tenda mewah bagi tamu paket VIP yang direservasi secara atomik melalui kontrak aditif `Modules\Hotel\Contracts\RoomBlockReserver` (G7-K16).
2. **Mitra Kuliner & Tenant Resto:** Penyediaan makanan dan minuman festival di mana transaksi penjualan dicatat secara real-time via kontrak `Modules\Resto\Contracts\OutletSaleRecorder` (G7-K17).
3. **Penyedia Tenaga Kerja Lepas & Keamanan:** Perekrutan cepat staf bartender dan kru turnstile lembur melalui kontrak aditif `Modules\Hcm\Contracts\ShiftBountyPublisher` (G7-K18).
4. **Pemasok Minuman Impor Beralkohol:** Pengadaan spirit dan champagne impor berbea cukai melalui kontrak aditif `Modules\Trade\Contracts\ImportLandedCostQuoter` (G7-K19).

Runbook ini memandu langkah demi langkah yang harus dilakukan mulai dari fase pra-acara (H-7), hari pelaksanaan (H-Day), hingga penutupan buku pasca-acara (H+1).

---

## 2. Fase Pra-Acara (H-7 hingga H-1)

### 2.1 Konfigurasi Paket Bundling Festival & Alokasi SSP (H-7)
1. Buka antarmuka perancang paket di `/venue/economy/bundles`.
2. Masukkan kode paket (misal: `FEST-GLAMP-VIP-2026`) dan harga jual transaksi yang ditawarkan ke konsumen (misal: Rp 2.999.999).
3. Tentukan komponen harga jual berdiri sendiri (*Standalone Selling Price* / SSP):
   - Tiket Masuk 3 Hari: Rp 1.800.000 (Vendor: Internal Venue).
   - Kamar Glamping Mitra: Rp 1.500.000 (Vendor: Mitra Hotel K16).
   - Saldo Konsumsi F&B: Rp 400.000 (Vendor: Mitra Resto K17).
   - Layanan Shuttle VIP Bandara: Rp 300.000 (Vendor: Mitra Transportasi).
4. Tentukan potongan komisi platform venue (standar 500 bps atau 5%).
5. Klik **Simpan & Alokasikan via V6**. Sistem akan mengeksekusi `AllocateFestivalBundleSspAction`, mencatat pesanan, dan membukukan kas masuk ke akun kewajiban `ven:bundle_unearned:IDR`.

### 2.2 Reservasi Blok Kamar Hotel Mitra via G7-K16 (H-5)
1. Sistem secara otomatis memanggil `RoomBlockReserverService::reserveBlock()` untuk setiap transaksi paket bundling yang berhasil dibayar.
2. Parameter panggilan:
   - `propertyId`: ID Resort mitra di Badung / Canggu.
   - `roomId`: Tipe kamar Glamping Suite.
   - `checkInDate` & `checkOutDate`: Rentang tanggal festival (3 malam).
   - `dailyRateIdr`: Tarif kamar harian yang disepakati.
3. Layanan modul Hotel mengunci ketersediaan kamar secara pesimistik (*pessimistic row lock*) dan menerbitkan nomor konfirmasi reservasi (`reservation_code`).

### 2.3 Pengadaan Spirit Bar & Kuotasi Cukai MMEA via G7-K19 (H-3)
1. Kepala gudang bar menghitung kebutuhan botol spirit golongan C (whisky, vodka, gin, tequila).
2. Eksekusi aksi replenishment via `ReplenishBarInventoryWithCustomsAction`:
   - Sistem memanggil `ImportLandedCostQuoterService::quoteMmeaLandedCost()` untuk menghitung rincian Bea Masuk (5%), Cukai Spesifik MMEA (Rp 150.000 per liter untuk Golongan C), PPN Impor (11%), dan PPh 22 Impor (2,5%).
   - Stok botol di tabel `ven_bar_inventory_items` bertambah secara atomik, dan harga pokok perolehan botol (*landed cost*) diperbarui pada subledger.

### 2.4 Pembayaran Uang Muka Artis (*Artist Advance Payment*) (H-2)
1. Buka formulir kontrak artis di `/venue/economy/artists`.
2. Input data kontrak: nama artis, manajemen, nilai jaminan (*guarantee*), porsi bagi hasil pintu (*share bps*), dan persentase pajak PPh 21/26.
3. Eksekusi pencairan uang muka. Layanan `VenueEconomyLedgerService::postArtistAdvance()` mendebit akun aset uang muka `ven:artist_advance:IDR` dan mengkredit rekening artis `ven:creator_payable:IDR` serta hutang pajak `ven:wht_payable:IDR`.

---

## 3. Fase Hari-H Pelaksanaan (H-Day)

### 3.1 Pembukaan Shift Bar & Perekrutan Kru Lembur via G7-K18
1. Pada pukul 16.00 WITA, supervisor bar membuka shift via antarmuka `/venue/economy/bar`.
2. Sistem mencatat stok awal botol fisik (*opening bottles*) untuk setiap stasiun bar.
3. Bila terjadi lonjakan pengunjung yang tidak terduga pada gerbang masuk, manajer operasional memicu penambahan staf darurat melalui `ShiftBountyPublisherService::publishBounty()`:
   - Sistem mempublikasikan tawaran kerja lembur berinsentif (*shift bounty*) ke modul HCM dengan tarif per jam IDR yang kompetitif dan jaminan asuransi kecelakaan kerja otomatis.

### 3.2 Pemantauan Real-Time Impresi Sponsor di Layar LED Panggung (V8)
1. Petugas komersial memantau dasbor sponsor di `/venue/economy/sponsors`.
2. Sensor gerbang turnstile terus mengirimkan jumlah pemindaian masuk (*footfall*) per menit.
3. Algoritma V8 secara berkala menghitung capaian impresi terbobot (*weighted impressions*) berdasarkan rotasi penayangan konten sponsor pada videotron panggung utama.
4. Bila capaian impresi mencapai ambang batas milestone, termin pembayaran sponsor berstatus `milestone_due` dan siap ditagihkan.

### 3.3 Penukaran Komponen Bundling (Redemption)
1. Saat tamu menikmati makanan di tenant festival, kasir memindai gelang RFID cashless tamu.
2. Sistem mengeksekusi `RedeemFestivalBundleComponentAction`:
   - Saldo pendapatan diterima di muka pada `ven:bundle_unearned:IDR` diamortisasi (di-debit).
   - Hak bersih vendor dikreditkan ke `ven:organizer_payable:IDR`.
   - Komisi platform venue dikreditkan ke `ven:platform_fee_revenue:IDR`.

---

## 4. Fase Pasca-Acara & Penutupan Buku (H+1)

### 4.1 Audit Fisik Botol Bar & Rekonsiliasi Tumpahan
1. Pada pukul 05.00 WITA setelah acara berakhir, supervisor bar menghitung sisa botol fisik (*closing physical bottles*).
2. Input angka closing aktual ke dalam modal audit shift di `/venue/economy/bar`.
3. Aksi `AuditBarShiftStockAction` membandingkan angka fisik terhadap rekaman penjualan POS dan volume tumpahan (*spillage*).
4. Bila varians sama dengan 0, status shift berubah menjadi `reconciled`. Bila terdapat selisih, supervisor wajib mengisi formulir berita acara investigasi.

### 4.2 Final Settlement Artis & Penerbitan Sertifikat V5
1. Manajer venue membuka lembar penyelesaian kontrak artis di `/venue/economy/artists`.
2. Masukkan total penjualan kotor pintu (*door gross sales*) dan biaya operasional langsung yang disetujui.
3. Klik **Simpan & Posting Buku Besar**.
4. Algoritma V5 `ArtistDoorSplitCalculator` mengeksekusi logika versus deal, menerbitkan `ArtistDoorSplitCertificate` ber-HMAC, dan memposting pelunasan akhir:
   - Debit: `ven:artist_fee_expense:IDR` (sebesar Gross Pay).
   - Kredit: `ven:artist_advance:IDR` (membalik saldo uang muka awal).
   - Kredit: `ven:wht_payable:IDR` (mencatat kewajiban setor PPh).
   - Kredit: `ven:creator_payable:IDR` (sisa bersih yang ditransfer ke rekening artis).

### 4.3 Rekonsiliasi Akhir Konsistensi Dua Sumber
Langkah pamungkas penutupan buku adalah menjalankan perintah konsol verifikasi mandiri:
```bash
php artisan venue:audit-economy
```
Pastikan seluruh baris pemeriksaan berstatus `HEALTHY` dan total diskrepansi bernilai 0 sebelum laporan keuangan malam diserahkan kepada Direksi.

---

## 5. Prosedur Penanganan Situasi Darurat & Force Majeure

### 5.1 Pembatalan Artis Akibat Cuaca Ekstrem atau Gangguan Penerbangan
Bila artis headliner membatalkan penampilan secara resmi:
1. Status kontrak diubah menjadi `cancelled_force_majeure` melalui `ArtistEngagementStateMachine`.
2. Klaim asuransi kerugian pembatalan acara diajukan ke modul Insurance via kontrak `EventCoverBinder` (Fase 89).
3. Uang muka artis ditahan di akun escrow atau ditagih kembali (*clawback*) sesuai klausul kontrak wanprestasi.
4. Tamu pemegang tiket diberikan opsi pengembalian dana penuh (*full refund*) atau tiket pengganti untuk pertunjukan berikutnya.

### 5.2 Sengketa Hak Eksklusivitas Merek Sponsor
Bila sponsor resmi memprotes adanya materi promosi kompetitor di area festival:
1. Status kesepakatan diubah ke `disputed` di `SponsorDealStateMachine`.
2. Tim keamanan venue segera menertibkan materi promosi yang tidak sah.
3. Setelah mediasi selesai dan kompensasi penayangan slot tambahan diberikan, status dikembalikan ke `activated` atau `invoiced` dengan persetujuan tertulis perwakilan sponsor.
