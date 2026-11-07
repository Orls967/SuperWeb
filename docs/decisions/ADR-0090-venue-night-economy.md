# ADR-0090: Artist Contracts, Bar Supply Chain, Night Economics, Membership & Festival Economy

## Status
Diterima (Accepted) — 2026-11-07

## Konteks Bisnis & Masalah
Ekosistem hiburan malam, beach club premium, dan festival multi-hari melibatkan model bisnis multi-pihak yang kompleks:
1. **Kontrak Artis & DJ Internasional:** Negosiasi fee berbasis kesepakatan versus (*guarantee vs door split percentage*), komitmen rider teknis dan hospitality yang harus dilacak realisasinya, pemotongan pajak penghasilan pasal 21/26 (WHT), serta penanganan escrow deposit dengan klausul pinalti wanprestasi atau pembatalan sepihak.
2. **Rantai Pasok Bar & Minuman Beralkohol (MMEA):** Inventaris botol bernilai tinggi (spirit & champagne premium) rawan terhadap *shrinkage*, *spillage*, dan *free-pouring*. Regulasi importasi dan cukai (PMK 160/2023) menuntut pencatatan *landed cost* akurat dengan integrasi modul Trade/Wms, serta rekonsiliasi konsinyasi minuman premium dengan distributor luar.
3. **Ekonomi Malam & Profitabilitas Real-Time (RevPAT):** Kinerja beach club dan lounge tidak cukup diukur dari gross revenue semata, melainkan *Revenue Per Available Table-Hour* (RevPAT), marjin kontribusi F&B per zona waktu malam, dan penyesuaian dinamis *minimum spend* berdasarkan cuaca, jam puncak, dan kepadatan kerumunan.
4. **Keanggotaan (Membership) & Loyalitas Lintas Ekosistem:** Pengelolaan tier eksklusif (Sun, Moon, Infinity) dengan fasilitas VIP line bypass, loker penitipan botol (*bottle locker storage*), dan masa tenggang (*grace period*) evaluasi pengeluaran tahunan.
5. **Ekonomi Festival & Alokasi Bundling Multi-Vendor:** Penjualan paket kombo (tiket festival + kamar hotel mitra + saldo F&B + merchandise) wajib dialokasikan secara matematis sesuai standar akuntansi IFRS 15 / PSAK 72 berbasis *Standalone Selling Price* (SSP) menggunakan Hamilton-Hare Largest Remainder Method tanpa selisih pembulatan 1 rupiah pun.
6. **Sponsor & Aktivasi Merek:** Kontrak sponsor berbasis jaminan eksposur terbobot (*weighted impressions*) pada LED screen dan gerbang turnstile dengan pelunasan termin milestone terverifikasi.

## Keputusan Arsitektur

### 1. Standar Moneter & Presisi Akuntansi (G7-D32, D90-8)
- Seluruh nilai nominal mata uang IDR disimpan dalam integer rupiah penuh (minor unit sen tidak berlaku pada IDR); USD/SGD dalam minor integer (cents).
- Komponen bagi hasil, komisi platform, dan tarif pajak disimpan dalam basis poin integer (bps, di mana 100 bps = 1%).
- Seluruh perhitungan alokasi proporsional (alokasi SSP bundling dan pembagian gross ticket) mengimplementasikan algoritma pembagian berulang berbasis sisa terbesar (*Largest Remainder / Hamilton-Hare Method*) untuk menjamin invarian:
  $$\sum \text{Allocated Components} = \text{Bundle Price}$$

### 2. Algoritma Bersertifikat Baru (V5 s/d V8) (T-4)
- **V5 `ArtistDoorSplitCalculator` (`Modules\Venue\Domain\Algorithms`)**:
  Menghitung penyelesaian kontrak artis dengan logika versus:
  $$\text{Gross Pay} = \max\left(\text{guarantee}, \frac{\text{share\_bps}}{10.000} \times (\text{door\_gross} - \text{approved\_expenses})\right)$$
  Menghitung WHT PPh 21/26, memotong uang muka (*advance payment*), dan mengonversi sisa hak artis ke valuta asing (jika artis luar negeri) menggunakan kurs spot terverifikasi. Dilengkapi dengan `ArtistDoorSplitVerifier` dan penerbitan `AlgorithmCertificate`.
- **V6 `BundleSspAllocator` (`Modules\Shared\Domain\Algorithms\Settlement`)**:
  Menghitung alokasi harga transaksi paket bundling festival ke masing-masing item kewajiban pelaksanaan (*performance obligation*) berdasarkan rasio SSP masing-masing tanpa residu fraksional. Menghitung platform take-rate fee serta memvalidasi keutuhan invariant akuntansi IFRS 15. Dilengkapi `BundleSspVerifier`.
- **V7 `MembershipTierEngine` (`Modules\Venue\Domain\Algorithms`)**:
  Mengevaluasi status kualifikasi tier keanggotaan tahunan beach club (Sun, Moon, Infinity) berdasarkan belanja bergulir 365 hari dan frekuensi kunjungan. Menghitung poin loyalitas terbobot tier multiplier dan memberlakukan aturan *grace period* 1 tingkat saat evaluasi tahunan. Dilengkapi `MembershipTierVerifier`.
- **V8 `SponsorExposureValuator` (`Modules\Venue\Domain\Algorithms`)**:
  Menghitung impresi terbobot (*weighted impressions*) aktivasi sponsor di setiap zona venue, mengonversi ke nilai eksposur ekuivalen CPM, dan menentukan rasio pencairan termin pembayaran (*payout ratio*) berbasis persentase pencapaian KPI kontrak. Dilengkapi `SponsorExposureVerifier`.

### 3. Empat State Machine Terisolasi
- **`ArtistEngagementStateMachine`**: Menjaga siklus hidup kontrak artis:
  `offered` $\to$ `contracted` $\to$ `advance_paid` $\to$ `performed` $\to$ `settled` (jalur normal); cabang anomali `disputed` dan `cancelled_force_majeure`.
- **`MembershipStateMachine`**: Menjaga status keanggotaan tamu VIP:
  `applied` $\to$ `kyc_pending` $\to$ `active` $\to$ `grace` $\to$ `downgraded` $\to$ `suspended` $\to$ `cancelled`.
- **`FestivalBundleStateMachine`**: Menjaga pemenuhan paket festival multi-vendor:
  `quoted` $\to$ `reserved` $\to$ `paid` $\to$ `fulfilled_partial` $\to$ `fulfilled` $\to$ `settled` (jalur pembatalan `refunded_partial` dan `cancelled`).
- **`SponsorDealStateMachine`**: Menjaga siklus kesepakatan sponsorship:
  `proposed` $\to$ `signed` $\to$ `activated` $\to$ `milestone_due` $\to$ `invoiced` $\to$ `paid` $\to$ `closed` (jalur sengketa `disputed`).

### 4. Kontrak Aditif Lintas Modul (G7-K16 s/d G7-K19)
- **`G7-K16` (`Modules\Hotel\Contracts\RoomBlockReserver`)**:
  Didefinisikan di `Modules\Hotel\Contracts\RoomBlockReserver` dan diimplementasikan oleh `Modules\Hotel\Application\Services\RoomBlockReserverService` untuk mengunci dan mereservasi blok kamar hotel/glamping festival mitra secara atomik, membungkus `ReserveRoomWithInventoryNightLockAction`.
- **`G7-K17` (`Modules\Resto\Contracts\OutletSaleRecorder`)**:
  Kontrak yang sudah ada di modul Resto untuk merekam transaksi POS bar, konsumsi botol VIP, dan pesanan banquet.
- **`G7-K18` (`Modules\Hcm\Contracts\ShiftBountyPublisher`)**:
  Didefinisikan di `Modules\Hcm\Contracts\ShiftBountyPublisher` dan diimplementasikan oleh `Modules\Hcm\Application\Services\ShiftBountyPublisherService` membungkus `InternalGigEconomyService::postBounty` untuk perekrutan cepat staf bartender dan kru keamanan lembur pada malam festival.
- **`G7-K19` (`Modules\Trade\Contracts\ImportLandedCostQuoter`)**:
  Didefinisikan di `Modules\Trade\Contracts\ImportLandedCostQuoter` dan diimplementasikan oleh `Modules\Trade\Application\Services\ImportLandedCostQuoterService` membungkus `CalculateIncotermsLandedCostAction` beserta tabel tarif bea masuk dan cukai MMEA impor berdasarkan PMK 160/2023.

### 5. Buku Besar Akrual & Settlement Venue Lanjutan (D90-8)
- Pembukuan akrual dilakukan secara double-entry menggunakan subledger `VenueLedger`:
  - `ven:artist_advance:IDR` (Aktiva / Uang Muka Artis)
  - `ven:artist_fee_expense:IDR` (Beban Operasional Acara)
  - `ven:creator_payable:IDR` (Hutang Manajemen Artis / Bank)
  - `ven:wht_payable:IDR` (Hutang PPh 21/26)
  - `ven:bundle_unearned:IDR` (Pendapatan Bundling Diterima di Muka)
  - `ven:sponsor_receivable:IDR` (Piutang Sponsor)
  - `ven:sponsorship_revenue:IDR` (Pendapatan Sponsorship)
  - `ven:beverage_inventory:IDR` (Persediaan Minuman Bar)
  - `ven:customs_duty_payable:IDR` & `ven:excise_tax_payable:IDR` (Hutang Cukai & Bea Masuk)

### 6. Audit Mandiri Ekonomi Venue (`venue:audit-economy`)
Perintah konsol mandiri `php artisan venue:audit-economy` menjalankan rekonsiliasi matematis dua arah:
1. Rekonsiliasi kesepakatan artis: total pembayaran artis = Gross Pay (V5) - PPh - Advance.
2. Rekonsiliasi bundling SSP: saldo unearned = alokasi belum terealisasi, dan total alokasi vendor = harga paket.
3. Rekonsiliasi sponsor: piutang dan pendapatan sponsor berbanding lurus dengan capaian impresi V8.
4. Rekonsiliasi saldo neraca: $\sum \text{Debit} - \sum \text{Kredit} = 0$ pada seluruh akun ekonomi venue.

## Konsekuensi
- **Positif:** Mengeliminasi celah kerugian sengketa artis, mencegah kebocoran margin bar akibat spillage/cukai tidak tercatat, menjamin kepatuhan audit IFRS 15 pada festival bundle multi-vendor, dan memberikan transparansi real-time atas profitabilitas operasional malam.
- **Mitigasi:** Integritas state dan kalkulasi diverifikasi dengan acceptance test terpadu, unit test golden vector algoritma bersertifikat, pengujian sabotase data korup, dan verifikasi harian `venue:audit-economy`.
