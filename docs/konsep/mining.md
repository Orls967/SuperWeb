# Konsep Domain Pertambangan (Mining Operations & Engineering)

Dokumen ini merupakan sumber kebenaran konsep bisnis, invarian teknis, dan permodelan domain ekosistem pertambangan pada platform Superweb, diawali dengan **Fase 93: Mine Planning, Fleet Dispatch & Fuel Management**.

---

## 1. Fase 93 — Mine Planning, Fleet Dispatch & Fuel Management

### 1.1 Masalah Bisnis & Latar Belakang
Operasi penambangan terbuka (open-pit mining) untuk komoditas nikel laterit dan batubara di Indonesia memiliki volatilitas operasional tinggi yang dipengaruhi oleh:
1. Ketidakpastian cuaca tropis (hujan lebat > 50 mm/hari menghentikan operasi hauling di jalan licin).
2. Ketidakseimbangan armada loader-truk (Match Factor menyimpang menyebabkan waktu tunggu berlebih di pit atau dump hopper).
3. Kerugian bahan bakar (fuel burn rate menyimpang dari ton-kilometer baseline akibat pencurian solar atau degradasi engine).
4. Sengketa penagihan kontraktor penambangan (hauling billing dispute) yang memerlukan pembuktian berbasis data telematika andal.

### 1.2 Aktor Operasional & Peran RBAC
- **`mine_planner`**: Bertanggung jawab membuat rencana tambang bulanan, permodelan blok voxel pit, dan alokasi time-phased target produksi ke hari dan shift kerja.
- **`dispatcher_mine`**: Mengawasi papan dispatch shift real-time, memonitor penugasan truk otomatis berbasis algoritma M2, dan melakukan override operasional bila terjadi breakdown shovel.
- **`mine_manager`**: Mengawasi ketersediaan armada berat, pemenuhan Six Big Losses OEE, dan menyetujui penyesuaian target akibat peristiwa cuaca ekstrem.
- **`finance_officer`**: Melakukan rekonsiliasi biaya hauling kontraktor, menahan dana (hold) atas anomali BBM terdeteksi, dan menyetujui pemotongan tagihan.
- **`hse_officer`**: Memastikan keselamatan armada, memantau batas kritis telemetri suhu dan tekanan ban haul truck (TKPH), serta mengevaluasi kelayakan jalan tambang.
- **`contractor`**: Pihak ketiga penyedia armada hauling yang mengakses portal terisolasi untuk memantau siklus angkut, data hold tagihan, dan mengajukan sanggahan (dispute).

### 1.3 Invarian Teknis Utama Fase 93
Setiap invarian ditegakkan secara permanen dalam kode produksi:
1. **INV-93-01 (Konservasi Target Phasing)**:
   Total alokasi tonase harian dari algoritma M1 Weighted Time Phasing wajib sama persis dengan target bulanan rencana blok pit: $\sum \text{shift\_target} = \text{monthly\_target}$.
   *Penegakan kode:* `Modules\Shared\Domain\Algorithms\Scheduling\WeightedTimePhasingCertificateVerifier::verify`
2. **INV-93-02 (Batasan Ketersediaan Armada Dispatch)**:
   Jumlah truk yang ditugaskan (ASSIGNED/HAULING) dalam satu shift tidak boleh melebihi jumlah unit truk yang berstatus `available` atau `assigned` di site tersebut.
   *Penegakan kode:* `Modules\Mining\Domain\Algorithms\NeedTimeTruckDispatcherCertificateVerifier::verify`
3. **INV-93-03 (Anti-Double-Dip Idempotensi Hold BBM)**:
   Setiap kasus deviasi bahan bakar hanya boleh memicu penahanan dana kontraktor tepat satu kali via kunci idempotensi `min:fuel_hold:{case_id}`.
   *Penegakan kode:* `Modules\Mining\Application\Sagas\FuelAnomalyHoldSaga::executeHold`
4. **INV-93-04 (Kesetimbangan Pembukuan Hold & Rilis)**:
   Saldo akun penahanan `min:contractor_payment_hold:IDR` di buku besar wajib sama persis dengan total akumulasi record status `HELD` pada tabel `min_contractor_payment_holds`.
   *Penegakan kode:* `Modules\Mining\Application\Services\MiningFleetDispatchService::auditMining`

### 1.4 Formula & Algoritma Bersertifikat
- **M1 `WeightedTimePhasing`**:
  Memecah kuota produksi bulanan ke dalam alokasi shift harian berdasarkan ketersediaan jam efektif ($W_i = \text{Jam Operasi} - \text{Jam Hujan} - \text{Jam Perawatan}$).
  $$Q_i = \left\lfloor Q_{total} \times \frac{W_i}{\sum W_j} \right\rfloor + R_i$$
  Sisa residu dibagikan menggunakan algoritma Hamilton (Largest Remainder).
- **M2 `NeedTimeTruckDispatcher`**:
  Menugaskan truk berikutnya ke loader/shovel yang memiliki waktu mulai pemuatan paling awal:
  $$T_{start} = \max(T_{truk\_tiba}, T_{shovel\_ready})$$
  Rasio Match Factor dihitung:
  $$MF = \frac{N_{truk} \times t_{loading}}{N_{shovel} \times t_{cycle}}$$
- **M3 `LinearBaselineRegression`**:
  Menghitung konsumsi BBM baseline menggunakan regresi kuadrat terkecil linier terhadap Ton-Kilometer ($TK$):
  $$\text{Baseline Liter} = a + b \times TK$$
  Kelebihan dinyatakan dalam basis points (bps) dan diklasifikasikan sebagai anomali bila $> 1.500\text{ bps}$ ($> 15\%$).
- **M-IDW `InverseDistanceWeightingInterpolator`**:
  Mengestimasi kadar nikel (% Ni) pada koordinat blok voxel tambang 3D dari borehole assays:
  $$Z(x) = \frac{\sum_{i=1}^n \frac{z_i}{d_i^2}}{\sum_{i=1}^n \frac{1}{d_i^2}}$$

### 1.5 Matriks Kepekaan Eksternal (T-5)
| Faktor Eksternal | Parameter Gerbang `MiningExternalFactors` | Aturan Respon & Tindakan Bisnis | Jeda Respons | Sumber Regulasi / Operasi |
| :--- | :--- | :--- | :--- | :--- |
| **Curah Hujan Ekstrem** | `rain_hours_per_day` ($> 4$ jam/hari) | Action `RePlanShiftTargetsAction`: Target shift dihitung ulang secara otomatis dan diturunkan sebanding jam hilang | Real-time shift | BMKG & Sensor Curah Hujan Otomatis Site |
| **Harga Solar Industri** | `diesel_fuel_price_idr` (Rp/Liter) | Action `DispenseFuelToEquipmentAction`: Valuasi beban BBM solar pada ledger debit `min:fuel_expense:IDR` disesuaikan per tanggal efektif | Harian | Kepmen ESDM / Pertamina Patra Niaga |
| **Batas Ambang Anomali BBM** | `fuel_anomaly_threshold_bps` (1.500 bps) | Action `EvaluateContractorFuelAnomalyAction`: Kasus di atas ambang otomatis memicu saga penahanan tagihan (hold) | Harian / Shift close | Standard Operational Procedure Fleet Dept |
| **Target Kadar Feed Smelter** | `target_feed_grade_ppm` (16.500 ppm) | Service `PitShortTermOptimizationService`: Penyesuaian rasio penggalian bench Saprolite vs Limonite | Mingguan | Kontrak Offtake Pabrik HPAL/RKEF |

### 1.6 Katalog Kasus Unik T-6 (Fase 93)
1. `CASE-93-01`: Alokasi shift normal cuaca cerah (21 shift mingguan).
2. `CASE-93-02`: Penyesuaian target mendadak akibat hujan lebat 6 jam di Pit Barat.
3. `CASE-93-03`: Dispatch truk pada rasio Match Factor optimal $MF = 1.00$.
4. `CASE-93-04`: Penanganan kondisi Undertrucked ($MF = 0.44$) dengan rekomendasi penambahan armada.
5. `CASE-93-05`: Kondisi Overtrucked ($MF = 1.60$) memicu pengalihan truk ke Pit Timur.
6. `CASE-93-06`: Deteksi deviasi solar kontraktor PT IndoHaul sebesar $+20.87\%$ (2.087 bps) memicu hold pembayaran.
7. `CASE-93-07`: Sanggahan kontraktor diterima karena rute hauling berlumpur $\to$ dana hold dirilis (RESOLVED_RELEASED).
8. `CASE-93-08`: Sanggahan kontraktor ditolak terbukti penyelewengan solar $\to$ pemotongan permanen (RESOLVED_DEDUCTED).
9. `CASE-93-09`: Telemetri suhu engine CAT 777D mencapai 104°C memicu otomatisasi Work Order perawatan prediktif (K9).
10. `CASE-93-10`: Kegagalan pengadaan suku cadang MRP memicu saga kompensasi pembatalan WO dan pemulihan target.
11. `CASE-93-11`: Pengisian BBM solar dari Fuel Bowser keliling ke Shovel EX-2000 di bench elevasi RL +45.
12. `CASE-93-12`: Rekonsiliasi sounding tangki timbun solar site menemukan evaporasi dalam batas wajar $0.2\%$.
13. `CASE-93-13`: Uji anti-IDOR portal kontraktor: Kontraktor A dilarang melihat atau menyanggah kasus Kontraktor B.
14. `CASE-93-14`: Kondisi batas pergantian shift lintas tengah malam (Shift 3: 23:00 - 07:00 WITA).
15. `CASE-93-15`: Kejutan Hujan Ekstrem 10 Hari berturut-turut (total jam kerja hilang 72 jam) memicu audit konsistensi buku besar.

### 1.7 Analisis Efektivitas Armada & Six Big Losses Pertambangan
Operasi alat berat pertambangan (Excavator, Dumptruck, Bulldozer, Grader) diukur menggunakan kerangka Overall Equipment Effectiveness (OEE) yang membedah kehilangan kapasitas produksi menjadi enam kategori kerugian (*Six Big Losses*):
1. **Unscheduled Breakdown (Kerusakan Tak Terencana)**: Kehilangan ketersediaan akibat kegagalan mekanikal engine, sistem hidrolik, atau transmisi yang memicu status `breakdown`.
2. **Setup and Pit Adjustments (Waktu Persiapan & Pindah Posisi)**: Waktu yang hilang saat shovel beralih antar bench, perapian lantai tambang, atau pergeseran posisi loading point.
3. **Idling and Minor Stoppages (Menganggur & Antrean Sesaat)**: Waktu tunggu truk di shovel saat rasio Match Factor tidak seimbang (Undertrucked/Overtrucked) atau saat menunggu antrean di dumping hopper.
4. **Reduced Speed (Kecepatan Operasi Rendah)**: Pengurangan laju tempuh truk akibat kondisi jalan tambang licin, bergelombang (IRI tinggi), tanjakan curam, atau kabut debu pekat.
5. **Payload Variance & Defects (Deviasi Muatan)**: Muatan yang kurang dari kapasitas nominal (*underloading* menyebabkan inefisiensi ritase) atau muatan berlebih (*overloading* meningkatkan keausan ban dan risiko kecelakaan).
6. **Rework & Spillage Cleanup (Pembersihan Tumpahan)**: Penanganan ceceran material bijih di jalur hauling yang menghambat laju konvoi truk angkut.

### 1.8 Struktur Biaya Penambangan: Stripping Ratio & Biaya per BCM
Biaya penambangan terbuka dikelompokkan ke dalam dua komponen utama:
1. **Pengupasan Tanah Penutup (Overburden Removal)**: Diukur dalam Bank Cubic Meter (BCM). Memiliki porsi sekitar 65–75% dari total pengeluaran operasional tambang.
2. **Penggalian & Pengangkutan Bijih (Ore Mining & Hauling)**: Diukur dalam Ton basah (WMT).

Rasio Kupas (*Stripping Ratio* / SR):
$$SR = \frac{\text{Volume Overburden (BCM)}}{\text{Tonase Bijih (Ton)}}$$
Nilai SR menjadi tolok ukur kelayakan ekonomi tambang; peningkatan SR akibat kelongsoran lereng pit akan menaikkan biaya per ton bijih secara drastis.

### 1.9 Landasan Regulasi Pertambangan (Good Mining Practice)
Penerapan sistem pada modul Pertambangan mengacu pada peraturan perundang-undangan Republik Indonesia yang berlaku:
- **UU No. 3 Tahun 2020** tentang Perubahan atas UU No. 4 Tahun 2009 tentang Pertambangan Mineral dan Batubara.
- **Kepmen ESDM No. 1827 K/30/MEM/2018** tentang Pedoman Pelaksanaan Kaidah Teknik Pertambangan yang Baik (*Good Mining Practice*), mencakup keselamatan operasi, pemantauan kestabilan lereng, dan pengelolaan bahan bakar.
- **PP No. 19 Tahun 2025** tentang Tarif PNBP Royalti Mineral dan Batubara (berlaku sejak April 2025).
- **Kepmen ESDM No. 72.K/MB.01/MEM.B/2025** tentang Penetapan Harga Batubara Acuan (HBA) dan Harga Mineral Acuan (HMA) sebagai acuan transaksi dan royalti.

---

### 1.10 Arsitektur Pembukuan Buku Besar Fase 93 (`MiningFase93LedgerPostingService`)
Seluruh mutasi finansial pada operasi pertambangan Fase 93 dikonsolidasikan di dalam layanan `Modules\Mining\Ledger\MiningFase93LedgerPostingService` yang membungkus `Modules\Banking\Application\Services\LedgerService` dan `Modules\Banking\Support\Posting`:

1. **Pengeluaran Bahan Bakar Solar (`postFuelDispense`)**
   - Dipanggil oleh `Modules\Mining\Application\Actions\DispenseFuelToEquipmentAction` saat operator stasiun bahan bakar (`fuelman`) mencatat pengeluaran solar dari tabel `min_fuel_storages` ke tabel `min_fuel_dispense_records`.
   - Kunci idempotensi: `min:fuel-dispense:{dispense_record_id}`.
   - Pasangan ayat jurnal:
     - **Debit**: `expense:min:fuel:IDR` (`MiningAccounts::fuelExpense()`) sebesar $\lfloor \text{volume\_ml} \times \text{harga\_per\_liter} / 1000 \rfloor$.
     - **Kredit**: `min:fuel_inventory:IDR` (`MiningAccounts::fuelInventory()`).

2. **Penagihan Hauling Kontraktor per Shift (`postContractorHaulingBilling`)**
   - Dipanggil oleh `Modules\Mining\Application\Actions\SettleShiftHaulingBillingAction` dan command `mining:shift-close {date} {shift}`.
   - Kunci idempotensi: `min:shift-close:{site_code}:{shift_date}:{shift_code}` (atau kunci dari header permintaan).
   - Pasangan ayat jurnal:
     - **Debit**: `expense:min:hauling_contractor:IDR`.
     - **Kredit**: `ap:min:contractor:{party_id}:IDR` (`MiningAccounts::contractorPayable($partyId)`).

3. **Penahanan Pembayaran Kontraktor Akibat Anomali BBM (`postContractorFuelHold`)**
   - Dipanggil pada Langkah 2 di dalam `Modules\Mining\Application\Sagas\FuelAnomalyHoldSaga` setelah algoritma `Modules\Shared\Domain\Algorithms\Stats\LinearBaselineRegression` mengonfirmasi deviasi $> 1.500\text{ bps}$.
   - Kunci idempotensi: `min:fuel-hold:{case_id}`.
   - Pasangan ayat jurnal:
     - **Debit**: `ap:min:contractor:{party_id}:IDR` (mengurangi utang lancar yang siap dibayar ke kontraktor).
     - **Kredit**: `min:contractor_payment_hold:IDR` (menampung liabilitas bersyarat selama investigasi berlangsung).

4. **Rilis Penahanan Pembayaran Kontraktor (`postContractorFuelHoldRelease`)**
   - Dipanggil oleh `FuelAnomalyHoldSaga::resolveReleased` ketika sanggahan kontraktor pada `MiningContractorPortalController::dispute` terbukti sah (misalnya akibat pengalihan jalur tanjakan darurat atau kondisi jalan berlumpur dengan IRI $> 4.500\text{ mm/km}$).
   - Kunci idempotensi: `min:fuel-release:{case_id}`.
   - Pasangan ayat jurnal:
     - **Debit**: `min:contractor_payment_hold:IDR`.
     - **Kredit**: `ap:min:contractor:{party_id}:IDR`.

5. **Pemotongan Definitif Varians BBM (`postContractorFuelHoldDeduction`)**
   - Dipanggil oleh `FuelAnomalyHoldSaga::resolveDeducted` apabila investigasi telematika membuktikan kehilangannya tidak terkait faktor geologi/cuaca.
   - Kunci idempotensi: `min:fuel-deduct:{case_id}`.
   - Pasangan ayat jurnal:
     - **Debit**: `min:contractor_payment_hold:IDR`.
     - **Kredit**: `min:fuel_variance_recovery:IDR`.

### 1.11 Layanan Analitika Geologi, Jalan Angkut, dan Keandalan Ban
Untuk mencegah keausan dini armada dan menjaga kontinuitas umpan bijih ke pabrik pengolahan, Fase 93 menyediakan lima layanan analitika terintegrasi:
- **Estimasi Cadangan Blok 3D (`EstimateBlockReserveAction`)**: Menggabungkan data lubang bor (`min_borehole_assays`) menggunakan `InverseDistanceWeightingInterpolator` dan `OrdinaryKriging3D` untuk mengklasifikasikan blok voxel (`min_block_model_voxels`) ke dalam kategori sumber daya `MEASURED`, `INDICATED`, atau `INFERRED` berdasarkan batas varians Kriging ($\le 500\text{ bps}$ untuk `MEASURED`).
- **Optimasi Blending Bench Jangka Pendek (`PitShortTermOptimizationService`)**: Menghitung porsi penggalian antara bench bijih kadar tinggi (*High-Grade Saprolite*) dan kadar rendah (*Low-Grade Limonite*) dalam satuan basis points (`portion_bps`) agar deviasi kadar campuran terhadap target umpan smelter tetap $\le 500\text{ ppm}$.
- **Kalkulator Biaya Kupasan per BCM (`MiningCostPerBcmCalculator`)**: Memisahkan struktur biaya pengupasan tanah penutup (*Overburden*, alokasi $7.000\text{ bps}$) dan ekstraksi bijih (*Ore*, alokasi $3.000\text{ bps}$) serta menghitung *Stripping Ratio* dalam skala basis points (`stripping_ratio_bps`).
- **Evaluasi Resistansi Gelinding Jalan Angkut (`HaulRoadFleetMaintenanceService`)**: Mengukur dampak kenaikan *International Roughness Index* (`roughnessIriMmPerKm`) terhadap kenaikan *Rolling Resistance* (`rolling_resistance_bps`), penalti pembakaran solar (`fuel_burn_penalty_bps`), batas kecepatan aman truk (`max_safe_speed_kmh`), serta penjadwalan unit *Motor Grader* dan *Water Truck* saat debu partikulat $\ge 150\text{ ppm}$.
- **Pemantauan Beban Termal Ban TKPH (`RecordTireTemperaturePressureAction`) dan Keandalan MTBF/MTTR (`EquipmentTelemetryAnalyticsService`)**: Menghitung indeks *Ton-Kilometer Per Hour* (`tkph_scaled`) untuk keenam roda truk angkut serta metrik *Mean Time Between Failures* (`mtbf_hours`) dan *Mean Time To Repair* (`mttr_hours`) dari riwayat eksekusi saga perawatan prediktif (`min_mining_sagas`).

### 1.12 Perlindungan Otorisasi & Anti-IDOR (`MiningFase93AccessPolicy`)
Setiap titik masuk HTTP pada Fase 93 dikawal oleh `Modules\Mining\Http\Policies\MiningFase93AccessPolicy`:
- Perencana tambang (`mine_planner`) hanya berwenang mengelola `/mining/plans` (`MiningPlanController`).
- Petugas dispatch (`dispatcher_mine`) mengoperasikan `/mining/dispatch` (`MiningDispatchController`), di mana setiap tindakan *override* shovel wajib menyertakan alasan tertulis minimal 5 karakter yang direkam pada `min_haul_cycle_histories`.
- Kontraktor (`contractor`) yang mengakses `/contractor/mining` (`MiningContractorPortalController`) diisolasi secara ketat berdasarkan `contractor_party_ref === (string) $user->id`. Upaya mengakses atau menyanggah nomor kasus `MiningFuelAnomalyCase` milik kontraktor lain langsung ditolak dengan status HTTP `403 Forbidden` oleh metode `MiningFase93AccessPolicy::assertContractorOwnsFuelCase`.
