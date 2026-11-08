# Desain Arsitektur & Spesifikasi Teknis — Fase 178: Airline Network, Yield, Codeshare & IRROPS

## 1. Ikhtisar Eksekutif & Domain Konsep
Fase 178 mengimplementasikan pilar operasional lanjutan dari industri penerbangan niaga (*commercial aviation*), berfokus pada empat subsistem kritis:
1. **Revenue Management & Yield Optimization (A6 `FareBucketYieldOptimizer`)**:
   Implementasi algoritma penetapan harga dinamis berbasis teori Littlewood dan Expected Marginal Seat Revenue versi b (EMSR-b) diskret. Sistem mengelola proteksi kursi (*seat protection levels*) dan ambang batas ketersediaan (*booking limits*) pada setiap kelas tarif (*fare buckets* Y, B, M, H, Q, V, dll.) berdasarkan probabilitas permintaan terdistribusi normal, deviasi historis, dan faktor muat (*load factor*).
2. **Operational Disruption Management / IRROPS (A7 `DisruptedPassengerReaccommodator`)**:
   Orkestrasi terotomatisasi ketika terjadi gangguan operasional masif (seperti badai tropis, penutupan bandara akibat erupsi gunung berapi VONA Red, atau *grounding* teknis armada). Algoritma menyusun antrean berbobot (*weighted priority queue*) berdasarkan status tier penumpang (Platinum > Gold > Silver > Classic), kelas kabin, urgensi koneksi lanjutan, dan kondisi penumpang khusus (anak tanpa pendamping/UM, lansia, disabilitas) untuk rebooking otomatis serta pemesanan blok kamar hotel darurat.
3. **Codeshare & Proration Clearing (A8 `CodeshareMileageAccrualEngine`)**:
   Mekanisme pembagian pendapatan (*revenue proration*) dan akrual program loyalitas lintas maskapai mitra aliansi (Operating Carrier vs Marketing Carrier). Mencegah masalah *double-dipping* poin loyalitas dan mengalokasikan penerimaan tiket multi-leg berbasis IATA Multilateral Interline Proration Rule (MPA) atau persentase basis-poin yang disepakati.
4. **Passenger Care Obligations PM 89/2015 (A9 `PassengerCareObligationCalculator`)**:
   Otomasi kewajiban maskapai sesuai regulasi Kementerian Perhubungan Republik Indonesia PM 89/2015 tentang Penanganan Keterlambatan Penerbangan (*Delay Management*):
   - Kategori 1 (30–60 menit): Penyediaan minuman ringan (*refreshment*).
   - Kategori 2 (61–120 menit): Penyediaan makanan ringan (*heavy snack*) dan minuman.
   - Kategori 3 (121–180 menit): Penyediaan makanan berat (*heavy meal*) dan minuman.
   - Kategori 4 (181–240 menit): Penyediaan makanan berat, snack, dan kompensasi pengalihan penerbangan lain.
   - Kategori 5 (> 240 menit): Ganti rugi uang tunai sebesar Rp300.000,- per penumpang atau penawaran *full refund*.
   - Kategori 6 (Pembatalan Penerbangan / *Cancellation*): Pengalihan ke penerbangan berikutnya, akomodasi hotel bintang 3+ dengan transportasi bandara-hotel pp, atau pengembalian dana tiket penuh 100% tanpa potongan.

---

## 2. Struktur Modul & Tata Letak File
Modul `Aviation` (`Modules\Aviation`) diperluas dengan struktur folder berstandar enterprise:
```
modules/Aviation/
├── Actions/
│   ├── OptimizeFareBucketsAction.php
│   ├── ProcessDisruptionCaseAction.php
│   ├── DispatchPassengerCareAction.php
│   ├── ReaccommodatePassengersAction.php
│   ├── SettleCodeshareProrationAction.php
│   └── AccrueFrequentFlyerMilesAction.php
├── Application/
│   ├── Services/
│   │   ├── AviationYieldManagementService.php
│   │   ├── AviationIrropsService.php
│   │   ├── AviationCodeshareService.php
│   │   ├── AviationLoyaltyProgramService.php
│   │   ├── AviationPassengerRightsService.php
│   │   ├── AviationFlightReschedulingService.php
│   │   └── AviationParametricInsuranceService.php
│   └── Sagas/
│       ├── CodeshareSettlementSaga.php
│       ├── LoyaltyMilesAccrualSaga.php
│       └── DisruptionIrropsSaga.php
├── Contracts/
│   ├── YieldOptimizerInterface.php
│   ├── IrropsPassengerReaccommodatorInterface.php
│   ├── CodeshareClearingInterface.php
│   └── LoyaltyPointExchangeInterface.php
├── Domain/
│   ├── Algorithms/
│   │   ├── FareBucketYieldOptimizer.php
│   │   ├── DisruptedPassengerReaccommodator.php
│   │   ├── CodeshareMileageAccrualEngine.php
│   │   └── PassengerCareObligationCalculator.php
│   ├── Models/
│   │   ├── AviationScheduleVersion.php
│   │   ├── AviationCodeshareAgreement.php
│   │   ├── AviationCodeshareFlight.php
│   │   ├── AviationDisruptionCase.php
│   │   ├── AviationDisruptionPassenger.php
│   │   ├── AviationCareVoucher.php
│   │   ├── AviationLoyaltyAccount.php
│   │   ├── AviationLoyaltyTransaction.php
│   │   ├── AviationYieldBucket.php
│   │   └── AviationParametricClaim.php
│   └── StateMachines/
│       ├── ScheduleVersionStateMachine.php
│       ├── CodeshareAgreementStateMachine.php
│       └── DisruptionCaseStateMachine.php
├── Http/
│   ├── Controllers/
│   │   ├── AviationYieldController.php
│   │   ├── AviationIrropsController.php
│   │   ├── AviationCodeshareController.php
│   │   └── AviationLoyaltyController.php
│   ├── Requests/
│   │   ├── StoreDisruptionCaseRequest.php
│   │   ├── DispatchCareVoucherRequest.php
│   │   ├── ReaccommodatePassengerRequest.php
│   │   └── CalculateYieldBucketsRequest.php
│   ├── Policies/
│   │   └── AviationDisruptionPolicy.php
│   └── ViewModels/
│       ├── IrropsDashboardViewModel.php
│       ├── YieldManagementViewModel.php
│       └── CodeshareAgreementViewModel.php
├── Exporters/
│   ├── IrropsManifestCsvExporter.php
│   ├── CodeshareClearingCsvExporter.php
│   └── YieldPerformanceCsvExporter.php
├── Console/Commands/
│   ├── AviationIrropsProcessCommand.php
│   ├── AviationYieldRebalanceCommand.php
│   ├── AviationCodeshareProrateCommand.php
│   └── AviationDisruptionAuditCommand.php
└── Integrations/
    ├── HotelIrropsRoomReservationService.php
    ├── InsuranceParametricTriggerService.php
    └── FinanceAviationLedgerService.php
```

---

## 3. Kontrak Lintas Modul Baru: G7-K29
Kontrak baru diletakkan di namespace `Modules\Insurance\Contracts\ParametricTriggerRecorder.php` untuk memfasilitasi pencatatan pemicu klaim asuransi keterlambatan/pembatalan penerbangan secara deterministik:
```php
namespace Modules\Insurance\Contracts;

interface ParametricTriggerRecorder
{
    /**
     * Catat kejadian parametrik yang memicu klaim asuransi otomatis.
     *
     * @param string $policyNumber Nomor polis asuransi penumpang
     * @param string $flightNumber Nomor penerbangan
     * @param string $triggerType Jenis pemicu (e.g. DELAY_OVER_180MIN, VOLCANIC_ERUPTION, CANCELLATION)
     * @param int $durationMinutes Durasi disrupsi dalam menit
     * @param int $payoutAmountMinorUnit Nilai pencairan ganti rugi (minor unit / sen)
     * @param array<string, mixed> $telemetryData Bukti data telemetri NOTAM/radar
     * @return string ID referensi klaim parametrik
     */
    public function recordTrigger(
        string $policyNumber,
        string $flightNumber,
        string $triggerType,
        int $durationMinutes,
        int $payoutAmountMinorUnit,
        array $telemetryData
    ): string;
}
```

---

## 4. Rincian Algoritma Bersertifikat

### 4.1. A6: `FareBucketYieldOptimizer`
- **Tujuan**: Menghitung batas perlindungan (*protection level*) dan batas pemesanan (*booking limit*) untuk $k$ kelas tarif diskret ($Y_1, Y_2, \dots, Y_m$) menggunakan metode EMSR-b.
- **Formulasi Matematis**:
  Untuk kelas tarif $j < m$, total permintaan agregat kelas $1 \dots j$ berdistribusi dengan rerata $\mu = \sum_{i=1}^j \mu_i$ dan variansi $\sigma^2 = \sum_{i=1}^j \sigma_i^2$. Rerata tertimbang tarif:
  $$\bar{r}_j = \frac{\sum_{i=1}^j r_i \mu_i}{\mu}$$
  Tingkat proteksi $S_j$ ditentukan oleh nilai kritis Littlewood:
  $$P(D \le S_j) = 1 - \frac{r_{j+1}}{\bar{r}_j}$$
  $$S_j = \mu + z \cdot \sigma$$
  di mana $z = \Phi^{-1}\left(1 - \frac{r_{j+1}}{\bar{r}_j}\right)$.
  Batas pemesanan untuk kelas $j+1$ adalah $BL_{j+1} = \max(0, C - S_j)$, di mana $C$ adalah kapasitas kursi pesawat yang tersisa.
- **Sertifikasi**: Menghasilkan sertifikat deterministik dengan hash SHA-256 yang memverifikasi integritas alokasi kuota per ember tarif (*fare bucket*).

### 4.2. A7: `DisruptedPassengerReaccommodator`
- **Tujuan**: Menentukan alokasi kursi alternatif pada penerbangan pengganti tercepat ketika penerbangan utama mengalami pembatalan atau keterlambatan parah.
- **Skor Bobot Penumpang**:
  $$Score = (W_{tier} \times 1000) + (W_{cabin} \times 500) + (W_{connection} \times 300) + (W_{special} \times 400) - (T_{booking\_order} \times 0.1)$$
  - $W_{tier}$: Platinum = 4, Gold = 3, Silver = 2, Classic = 1.
  - $W_{cabin}$: First = 3, Business = 2, Economy = 1.
  - $W_{connection}$: Memiliki penerbangan sambungan internasional = 3, domestik = 2, *point-to-point* = 1.
  - $W_{special}$: Unaccompanied Minor / Kursi Roda = 5, Normal = 0.
- **Strategi Rebooking**: Multi-leg forward graph traversal. Jika tidak ada kursi langsung dalam rentang 6 jam ke depan, penumpang diarahkan ke alokasi akomodasi hotel via `RoomBlockReserver` hingga keberangkatan hari berikutnya.

### 4.3. A8: `CodeshareMileageAccrualEngine`
- **Tujuan**: Menghitung perolehan poin miles loyalitas pada tiket codeshare antara Operating Carrier (penyelenggara penerbangan) dan Marketing Carrier (penjual tiket) berdasarkan aturan prorata jarak (*distance proration*) dan pembagian revenue split basis-poin.
- **Pencegahan Anti-Double Dip**:
  Menggunakan *cryptographic nonce composite key* `hash(ticket_no, segment_id, passenger_id)` yang didaftarkan pada buku besar loyalti terdistribusi sebelum akrual disetujui. Bila tiket yang sama diajukan ke dua program loyalitas maskapai mitra yang berbeda, transaksi kedua ditolak dengan status `DUPLICATE_CLAIM_REJECTED`.

### 4.4. A9: `PassengerCareObligationCalculator`
- **Tujuan**: Menentukan paket kewajiban pelayanan penumpang (*passenger care obligations*) berdasarkan durasi keterlambatan penerbangan (menit) dan alasan disrupsi (Teknis Operasional Maskapai vs Cuaca Buruk / Force Majeure).
- **Aturan PM 89/2015**:
  | Durasi Delay | Kategori PM 89 | Paket Care | Tunai / Kompensasi | Hotel |
  |---|---|---|---|---|
  | 30 - 60 mnt | Kategori 1 | Refreshment (Air mineral & teh/kopi) | - | Tidak |
  | 61 - 120 mnt | Kategori 2 | Heavy Snack + Minuman | - | Tidak |
  | 121 - 180 mnt | Kategori 3 | Heavy Meal Box + Minuman | - | Tidak |
  | 181 - 240 mnt | Kategori 4 | Heavy Meal + Snack Box | Pengalihan jika diminta | Tidak |
  | > 240 mnt | Kategori 5 | Heavy Meal + Refreshment | Rp300.000,- / pax | Ya (bila menginap) |
  | Dibatalkan | Kategori 6 | Full Hospitality | Full refund atau re-route | Ya (Hotel Bintang 3+) |

---

## 5. Alur State Machine

### 5.1. `ScheduleVersionStateMachine` (7 Status)
```
[draft] ──(submit_review)──> [review] ──(approve)──> [published]
                                │                         │
                                │(reject)                 │(activate_season)
                                v                         v
                             [withdrawn] <──(suspend)── [active]
                                                          │
                                                          │(supersede)
                                                          v
                                                     [superseded]
                                                          │
                                                          │(archive)
                                                          v
                                                     [suspended]
```

### 5.2. `CodeshareAgreementStateMachine` (7 Status)
```
[proposed] ──(negotiate)──> [negotiating] ──(sign)──> [signed]
                                │                         │
                                │(reject)                 │(inaugurate)
                                v                         v
                             [expired] <──(suspend)──── [active]
                                                          │
                                                          │(request_amendment)
                                                          v
                                                  [amendment_pending]
```

### 5.3. `DisruptionCaseStateMachine` (8 Status)
```
[detected] ──(classify)──> [classified] ──(dispatch)──> [care_dispatched]
                                │                              │
                                │(severe_impact)               │(start_reaccommodation)
                                v                              v
                      [reaccommodating] ──(confirm)──> [rebooked]
                                                          │
                                                          │(settle_compensation)
                                                          v
                                               [compensation_settled]
                                                 │              │
                                   (all_resolved)│              │(passenger_contest)
                                                 v              v
                                              [closed]      [disputed]
```

---

## 6. Skenario Simulasi T1: Erupsi Gunung Api (DPS VONA Red)
- Lokasi: Bandara Internasional I Gusti Ngurah Rai (DPS / WADD).
- Pemicu: Erupsi Gunung Agung melepaskan abu vulkanik pada ketinggian FL320. NOTAM diterbitkan, ruang udara ditutup selama 18 jam.
- Dampak:
  - 12 Penerbangan dibatalkan (DPS-CGK, DPS-SUB, DPS-SIN, DPS-SYD, dll.).
  - 1.800 Penumpang tertahan di bandara.
- Respons Sistem Terotomasi:
  1. `ProcessDisruptionCaseAction` mendeteksi NOTAM erupsi dan membuat `AviationDisruptionCase` dengan kategori `FORCE_MAJEURE_VOLCANIC_ERUPTION`.
  2. `DispatchPassengerCareAction` menghasilkan 1.800 e-voucher Kategori 6 (Meal Box + Hotel Voucher).
  3. `ReaccommodatePassengersAction` menggunakan `DisruptedPassengerReaccommodator` untuk mengantrekan penumpang ke slot hari berikutnya dan memanggil `HotelIrropsRoomReservationService` (`RoomBlockReserver`) untuk mengamankan 900 kamar hotel di Denpasar & Kuta.
  4. `AviationParametricInsuranceService` mencatat kejadian ke `Modules\Insurance\Contracts\ParametricTriggerRecorder` (G7-K29) untuk pembayaran klaim instan tanpa friksi.


---

## 7. Verifikasi Integritas & Matriks Pengujian Mandiri

1. **Uji EMSR-b Littlewood**: Memvalidasi kepatuhan monotonic nesting booking limit ($BL_Y \ge BL_B \ge BL_M \ge BL_Q$).
2. **Uji Anti-Double Dipping**: Memverifikasi token nonce klaim miles menolak pendaftaran kedua untuk e-ticket & kupon identik.
3. **Uji Kepatuhan PM 89/2015**: Memverifikasi kompensasi tunai Rp300.000 terbit untuk delay teknis > 240 menit dan dikecualikan untuk force majeure cuaca/letusan gunung api.
4. **Uji Simulasi Erupsi DPS VONA Red**: Memverifikasi penutupan NOTAM memicu alokasi e-voucher makan, pemesanan 900 kamar hotel mitra via `RoomBlockReserver`, dan pencatatan asuransi parametrik otomatis via G7-K29.
