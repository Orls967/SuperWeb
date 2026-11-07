# Katalog Galat Finansial, Integritas Algoritma & Operasional Ekonomi Venue (Fase 90)

**Domain:** Venue & Entertainment (`modules/Venue/`, sub-item 90.1 s/d 90.9)  
**Terkait Modul:** Hotel (`modules/Hotel/`), HCM (`modules/Hcm/`), Trade (`modules/Trade/`), Shared Algorithms  
**Tujuan Dokumen:** Menetapkan taksonomi lengkap 20 kode galat operasional dan finansial pada sistem ekonomi hiburan malam, kontrak artis, alokasi bundle festival, valuasi sponsor, dan stok bar, beserta mekanisme kompensasi otomatis dan pemulihannya.  
**Klasifikasi:** Standar Operasional Sistem (SOP) & Disaster Recovery Runbook  

---

## 1. Taksonomi Kode Galat

Sistem ekonomi venue Fase 90 mengelompokkan galat ke dalam 5 kategori hierarkis:
- `ERR-VEN-9001` s/d `ERR-VEN-9003`: Kontrak & Bagi Hasil Artis (*Artist Engagement & Door Split*)
- `ERR-VEN-9004` s/d `ERR-VEN-9006`: Alokasi Pendapatan Bundle Festival (*Hamilton-Hare SSP Allocation*)
- `ERR-VEN-9007` s/d `ERR-VEN-9009`: Tier Keanggotaan & Loyalty (*Membership & Tiering Engine*)
- `ERR-VEN-9010` s/d `ERR-VEN-9012`: Valuasi Sponsor & KPI Telemetri (*Sponsor Exposure Valuation*)
- `ERR-VEN-9013` s/d `ERR-VEN-9020`: Bar, Cukai, RevPAT, Loker Botol, Ledger & Integrasi Eksternal

---

## 2. Rincian Katalog 20 Kode Galat

### ERR-VEN-9001: `ARTIST_SPLIT_NEGATIVE_NET_DOOR`
- **Pemicu:** Total penjualan tiket pintu (*gross door receipts*) lebih kecil daripada biaya produksi dan rider artis yang disepakati untuk dipotong sebelum pembagian.
- **Dampak Finansial:** Net door receipts bernilai negatif ($< 0$), yang jika diproses secara naif akan menghasilkan tagihan balik ilegal kepada artis.
- **Kompensasi Otomatis:** Algoritma V5 (`ArtistDoorSplitCalculator`) menetapkan *net door receipts* ke batas aman Rp 0. Artis hanya berhak atas *guaranteed minimum* tanpa pembagian porsi pintu.
- **Tindakan Pemulihan:** Event Manager memeriksa kembali nota biaya produksi panggung; verifikasi kesepakatan klausul kontrak termin rider.

### ERR-VEN-9002: `ARTIST_ADVANCE_OVERPAYMENT_BREACH`
- **Pemicu:** Uang muka (*advance payment*) yang telah ditransfer kepada agensi artis melebihi total hak akhir artis (*guaranteed fee* + *door split entitlement*).
- **Dampak Finansial:** Terjadinya piutang lebih bayar artis yang tidak dapat ditutup secara otomatis dari kas event malam berjalan.
- **Kompensasi Otomatis:** State machine `ArtistEngagementStateMachine` menolak transisi ke `settled`. Status engagement dialihkan ke `in_dispute`. Akun piutang `ven:artist_advance_receivable:IDR` dibekukan.
- **Tindakan Pemulihan:** Divisi Legal menerbitkan nota sengketa *clawback* atau mengompensasikan kelebihan bayar ke jadwal penampilan artis berikutnya dalam tur.

### ERR-VEN-9003: `ARTIST_STATE_INVALID_TRANSITION`
- **Pemicu:** Permintaan penyelesaian finansial (*settlement action*) diajukan pada saat engagement artis masih berstatus `draft`, `proposed`, atau `cancelled`.
- **Dampak Finansial:** Penjurnalan fiktif atau premature disbursement pada kas operasional.
- **Kompensasi Otomatis:** `SettleArtistEngagementAction` melempar `IllegalStateTransitionException` dan membatalkan transaksi DB atomik.
- **Tindakan Pemulihan:** Pastikan checklist pertunjukan telah ditandatangani oleh Stage Manager dan status berada pada `completed`.

### ERR-VEN-9004: `SSP_BUNDLE_SUM_INVARIANT_VIOLATION`
- **Pemicu:** Jumlah total alokasi komponen hasil perhitungan alokator Hamilton-Hare ($\sum a_i$) tidak persis sama dengan total harga transaksi paket ($P$).
- **Dampak Finansial:** Pelanggaran berat konservasi moneter dan kegagalan audit IFRS 15 / PSAK 72. Selisih sen/rupiah menciptakan ketidakseimbangan neraca.
- **Kompensasi Otomatis:** `BundleSspVerifier` melempar `MonetaryConservationException`. Transaksi order bundle di-rollback seketika.
- **Tindakan Pemulihan:** Jalankan unit test determinisme kuota terbesar Hamilton-Hare; periksa penanganan residu pembulatan integer.

### ERR-VEN-9005: `SSP_ZERO_STANDALONE_PRICE_ERROR`
- **Pemicu:** Salah satu komponen vendor dalam paket festival dimasukkan dengan Standalone Selling Price (SSP) senilai Rp 0 atau bernilai negatif.
- **Dampak Finansial:** Pembagian dengan nol (*division by zero*) pada pembagian proporsional kuota harga.
- **Kompensasi Otomatis:** Validasi input `BundleSspAllocationInput` menolak payload sebelum masuk kalkulasi.
- **Tindakan Pemulihan:** Vendor wajib menyediakan harga jual eceran wajar (*observable market price*) atau estimasi biaya ditambah margin (*expected cost-plus-margin*).

### ERR-VEN-9006: `SSP_BUNDLE_STATE_ALREADY_SETTLED`
- **Pemicu:** Permintaan alokasi ulang atau perubahan vendor diajukan pada order bundle festival yang telah berstatus `settled` atau `completed`.
- **Dampak Finansial:** Potensi *double-settlement* atau mutasi pendapatan sepihak yang mendistorsi buku besar vendor.
- **Kompensasi Otomatis:** `FestivalBundleStateMachine` mengunci entitas order dari mutasi alokasi.
- **Tindakan Pemulihan:** Jika terjadi kesalahan alokasi pasca-settlement, buat jurnal koreksi manual melalui memo penyesuaian akuntansi resmi.

### ERR-VEN-9007: `MEMBERSHIP_TIER_NEGATIVE_POINTS`
- **Pemicu:** Penukaran poin loyalitas membership (*points burn*) melebihi saldo poin aktif yang dimiliki anggota.
- **Dampak Finansial:** Saldo poin anggota menjadi negatif, merugikan nilai cadangan liabilitas loyalitas (`ven:membership_liability:IDR`).
- **Kompensasi Otomatis:** Action penukaran membatalkan transaksi dan mengembalikan kode status HTTP 422 *Unprocessable Entity*.
- **Tindakan Pemulihan:** Guest Portal memvalidasi saldo poin terkini sebelum menampilkan opsi penukaran F&B / tiket.

### ERR-VEN-9008: `MEMBERSHIP_TIER_INVALID_DISCOUNT_BPS`
- **Pemicu:** Pengaturan persentase diskon tier keanggotaan dimasukkan di luar batas wajar ($> 5.000\text{ bps}$ atau $> 50\%$).
- **Dampak Finansial:** Pengikisan margin kotor venue yang membahayakan kelangsungan operasional bisnis.
- **Kompensasi Otomatis:** `MembershipTierEngine` memotong (*clamp*) nilai diskon maksimal pada batas keamanan $5.000\text{ bps}$.
- **Tindakan Pemulihan:** Admin Membership memperbarui konfigurasi tier melalui persetujuan otorisasi ganda (*four-eyes principle*).

### ERR-VEN-9009: `MEMBERSHIP_CARD_SUSPENDED_REVOCATION`
- **Pemicu:** Tamu mencoba memindai kartu akses atau menukarkan benefit menggunakan profil membership yang berstatus `suspended` atau `revoked` (akibat sanksi *chargeback* atau kerusuhan di venue).
- **Dampak Finansial:** Akses ilegal ke area VIP dan konsumsi gratis tanpa hak.
- **Kompensasi Otomatis:** Pintu turnstile membunyikan alarm penolakan dan mengirim telemetri darurat ke tablet sekuriti.
- **Tindakan Pemulihan:** Staf Guest Relations mengarahkan tamu ke loket pengaduan manajemen untuk klarifikasi status suspensi.

### ERR-VEN-9010: `SPONSOR_TARGET_IMPRESSIONS_ZERO`
- **Pemicu:** Kontrak sponsorship dibuat dengan target komitmen impresi bernilai nol atau negatif.
- **Dampak Finansial:** Ketidakmampuan menghitung persentase pencapaian kinerja sponsor.
- **Kompensasi Otomatis:** Konstruktor `SponsorExposureInput` menolak inisialisasi objek.
- **Tindakan Pemulihan:** Masukkan estimasi target impresi berdasarkan proyeksi footfall dan durasi tayang videotron.

### ERR-VEN-9011: `SPONSOR_EXPOSURE_ZONE_DWELL_OUT_OF_BOUNDS`
- **Pemicu:** Bobot lama tinggal (*dwell weight*) zona dimasukkan melebihi batas batas maksimum 50.000 bps ($5.0x$).
- **Dampak Finansial:** Penggembungan semu impresi tayang sponsor yang memicu penagihan tidak valid kepada mitra merek.
- **Kompensasi Otomatis:** `SponsorExposureValuator` menolak pemrosesan dan menerbitkan peringatan telemetri.
- **Tindakan Pemulihan:** Kalibrasi ulang matriks dwell time zona berdasarkan data rata-rata durasi sensor RFID/IoT.

### ERR-VEN-9012: `SPONSOR_MILESTONE_EXPOSURE_DEFICIT`
- **Pemicu:** Total impresi terbobot yang dikirimkan pada akhir periode milestone kurang dari batas bawah pencairan ($< 80\%$ target).
- **Dampak Finansial:** Penalti kontrak klausul *under-delivery*; termin sponsorship tidak dapat dicairkan penuh.
- **Kompensasi Otomatis:** Algoritma V8 secara otomatis menerapkan pemotongan pro-rata atau penahanan pencairan 100% sesuai jenis penalti kontrak.
- **Tindakan Pemulihan:** Tim Kemitraan menyusun proposal penambahan slot impresi kompensasi (*make-good inventory*) pada acara mendatang.

### ERR-VEN-9013: `BAR_STOCK_NEGATIVE_VOLUME_ERROR`
- **Pemicu:** Pengurangan volume spirit pada botol melebihi sisa volume cairan yang tercatat di database subledger.
- **Dampak Finansial:** Terjadinya *phantom stock* dan kekacauan akurasi HPP F&B.
- **Kompensasi Otomatis:** Database constraint menolak nilai negatif; action memicu audit darurat stok botol.
- **Tindakan Pemulihan:** Bar Captain melakukan penimbangan fisik botol dan mencatat koreksi melalui berita acara spillage.

### ERR-VEN-9014: `BAR_SPILLAGE_EXCEEDS_HARD_CEILING`
- **Pemicu:** Tingkat penyusutan cairan spirit pada satu shift bar melampaui ambang batas keras 15,00%.
- **Dampak Finansial:** Indikasi kuat penggelapan botol, pencurian cairan, atau kebocoran pipa dispenser berskala besar.
- **Kompensasi Otomatis:** `AuditBarShiftStockAction` mengunci shift log, membekukan sistem POS bar tersebut, dan mengirimkan notifikasi audit investigasi ke F&B Director.
- **Tindakan Pemulihan:** Tim Internal Audit melakukan pemeriksaan rekaman CCTV stasiun bar dan audit fisik menyeluruh.

### ERR-VEN-9015: `BAR_CUSTOMS_TARIFF_INVALID_ABV`
- **Pemicu:** Input persentase alkohol (ABV) berada di luar batas valid ($< 0\%$ atau $> 100\%$) saat simulasi landed cost impor.
- **Dampak Finansial:** Kesalahan penetapan Golongan Cukai (A, B, atau C) yang mengakibatkan kurang bayar atau lebih bayar bea cukai ke kas negara.
- **Kompensasi Otomatis:** Service `ImportLandedCostQuoterService` melempar `InvalidArgumentException`.
- **Tindakan Pemulihan:** Masukkan nilai ABV resmi sesuai Certificate of Analysis (CoA) dari produsen luar negeri.

### ERR-VEN-9016: `REVPAT_AVAILABLE_HOURS_ZERO`
- **Pemicu:** Perhitungan Revenue per Available Table (RevPAT) dipanggil dengan parameter jam buka meja senilai nol.
- **Dampak Finansial:** Kesalahan *division by zero* pada metrik efisiensi meja.
- **Kompensasi Otomatis:** `CalculateRevpatNightEconomicsAction` mengembalikan nilai metrik Rp 0 tanpa crash.
- **Tindakan Pemulihan:** Periksa waktu pembukaan dan penutupan zona operasional meja.

### ERR-VEN-9017: `BOTTLE_LOCKER_VAULT_EXPIRED`
- **Pemicu:** Tamu mencoba mengambil botol titipan loker VIP setelah melewati batas waktu 90 hari kalender.
- **Dampak Finansial:** Sengketa kepemilikan botol yang statusnya telah diputihkan menjadi hak venue (*forfeited*).
- **Kompensasi Otomatis:** Sistem loker menolak verifikasi QR code dan menampilkan status `forfeited`.
- **Tindakan Pemulihan:** Supervisor VIP dapat memberikan diskon goodwill jika keterlambatan disebabkan alasan darurat yang terbukti.

### ERR-VEN-9018: `LEDGER_UNEARNED_DEFICIT_POSTING`
- **Pemicu:** Pengakuan pendapatan tiket bundle festival melebihi saldo akun kewajiban pendapatan diterima di muka (`ven:bundle_unearned:IDR`).
- **Dampak Finansial:** Pelanggaran aturan pembukuan ganda di mana akun liabilitas menjadi negatif tanpa izin otorisasi.
- **Kompensasi Otomatis:** `LedgerService` melempar `InsufficientFundsException`.
- **Tindakan Pemulihan:** Periksa riwayat pencatatan pembelian bundle awal dan pastikan uang muka telah diakui dengan benar.

### ERR-VEN-9019: `HOTEL_ROOM_BLOCK_INSUFFICIENT_INVENTORY`
- **Pemicu:** Permintaan pemesanan blok kamar hotel untuk paket bundle festival (via kontrak `RoomBlockReserver`, G7-K16) melebihi kuota kamar yang tersedia di modul Hotel.
- **Dampak Finansial:** *Overbooking* kamar hotel yang menimbulkan penalti ganti rugi akomodasi kepada tamu festival.
- **Kompensasi Otomatis:** `RoomBlockReserverService` mengembalikan status penolakan ketersediaan kamar secara atomik.
- **Tindakan Pemulihan:** Paket bundle secara otomatis menonaktifkan varian glamping/hotel dan hanya menawarkan varian tiket masuk reguler.

### ERR-VEN-9020: `HCM_SHIFT_BOUNTY_BUDGET_EXCEEDED`
- **Pemicu:** Penerbitan insentif shift kilat untuk staf bar tambahan (via kontrak `ShiftBountyPublisher`, G7-K18) melampaui plafon anggaran darurat tenaga kerja event.
- **Dampak Finansial:** Pembengkakan biaya operasional tenaga kerja langsung (*over-budget labor cost*).
- **Kompensasi Otomatis:** `ShiftBountyPublisherService` membatasi pengumuman bounty pada batas alokasi anggaran tersisa.
- **Tindakan Pemulihan:** Venue General Manager mengajukan otorisasi penambahan pagu anggaran tenaga kerja darurat sebelum mempublikasikan shift tambahan.

---

Dokumen katalog galat ini menjadi pedoman operasional bagi tim Support Engineering, Financial Controller, dan Venue Auditor dalam penanganan insiden produksi.
