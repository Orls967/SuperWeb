# PANDUAN OPERASIONAL: ARMADA KAPAL, VOYAGE, CHARTER & MARINE SERVICES (FASE 180)

## 1. Ikhtisar Panduan

Dokumen ini merupakan petunjuk pelaksanaan teknis dan operasional untuk operator pelabuhan (*Port Operators*), agen perkapalan (*Shipping Line Agents*), inspektur kelaiklautan (*Marine Surveyors*), serta akuntan maritim (*Maritime Financial Controllers*) dalam mengoperasikan modul armada kapal `Port` (`prt_fleet_*`).

---

## 2. Prosedur Pendaftaran Armada Kapal & Paspor Digital

Setiap kapal ocean-going yang beroperasi di bawah naungan SuperWeb wajib didaftarkan melalui antarmuka atau service `PortFleetService`:

### 2.1 Pendaftaran Kapal Baru
```bash
# Contoh pemanggilan via Tinker atau API Controller
$fleetService = app(\Modules\Port\Application\Services\PortFleetService::class);
$vessel = $fleetService->registerVessel([
    'imo_number' => '9876543',                  # Wajib 7 digit angka dengan validasi check-digit IMO
    'vessel_name' => 'MV Nusantara Perdana',
    'call_sign' => 'YDKP',
    'mmsi' => '525012345',                       # MMSI 9 digit maritim
    'flag_state' => 'ID',
    'home_port' => 'Tanjung Priok',
    'vessel_type' => 'BULK_CARRIER',
    'fleet_class_code' => 'BKI',
    'built_year' => 2021,
    'deadweight_tonnage' => 58000,
    'gross_tonnage' => 34500,
    'net_tonnage' => 19800,
    'length_overall_cm' => 19990,                # Satuan dalam sentimeter (LOA 199.9 m)
    'beam_cm' => 3226,                           # Lebar kapal (32.26 m)
    'design_draft_cm' => 1300,                   # Sarat kapal desain (13.0 m)
    'main_engine_power_kw' => 8800,
    'design_speed_knots' => 14.20,
    'sfoc_design_grams_kwh' => 172.0,            # Spesific Fuel Oil Consumption (ISO 8217)
    'fuel_types_supported' => 'VLSFO,LSMGO',
]);
```

### 2.2 Penambahan Sertifikat Statutori
Setiap kapal wajib memiliki sertifikat statutori yang aktif:
1. `SOLAS_SAFETY_CONSTRUCTION` (Sertifikat Konstruksi Keselamatan Jiwa di Laut)
2. `SOLAS_SAFETY_EQUIPMENT` (Sertifikat Perlengkapan Keselamatan)
3. `MARPOL_IOPP` (Pencegahan Pencemaran Minyak Internasional)
4. `STCW_CREW_COMPLEMENT` (Sertifikat Minimum Awak Kapal Aman)
5. `ISM_SMC` (Safety Management Certificate)

Jika ada salah satu sertifikat yang telah melewati masa berlaku (*expired*), gerbang otomatis `AuditVesselStatutoryComplianceAction` akan memblokir penjadwalan pelayaran kapal yang bersangkutan.

---

## 3. Alur Kerja Komersial: Charter Party & Eksekusi Voyage

### 3.1 Pembentukan Kontrak Sewa Kapal (Charter Party)
1. Buka antarmuka `/port/charter` atau panggil `CommercialCharterService::createCharterParty`.
2. Tentukan jenis sewa:
   - `VOYAGE`: Sewa per perjalanan dengan tarif freight per metrik ton.
   - `TIME`: Sewa berbasis durasi harian dengan tarif sewa tetap per hari (*daily hire rate*).
   - `BAREBOAT`: Sewa kapal kosong tanpa awak dan perlengkapan.
3. Tetapkan parameter laytime:
   - Jam yang diizinkan (*Allowed Laytime Hours*), misal 72 jam.
   - Tarif denda keterlambatan (*Demurrage Rate*), misal \$18.000 / hari.
   - Klausula hari libur (`SHINC` atau `SHEX`).

### 3.2 Eksekusi Saga Pelayaran Maritim
Eksekusi sewa dan alokasi dermaga diorkestrasi oleh `VoyageCharterExecutionSaga`:
```php
$saga = app(\Modules\Port\Application\Sagas\VoyageCharterExecutionSaga::class);
$result = $saga->execute([
    'vessel_id' => $vessel->id,
    'charterer_name' => 'PT Semen Indonesia Logistik',
    'charter_type' => 'VOYAGE',
    'freight_rate_usd' => 14.50,
    'cargo_description' => 'Bulk Clinker Cement',
    'cargo_quantity_mt' => 50000.0,
    'demurrage_rate_daily_usd' => 18000.0,
    'allowed_laytime_hours' => 72.0,
    'origin_port' => 'IDTKG',      # Pelabuhan Panjang Tarahan
    'destination_port' => 'IDSUB', # Pelabuhan Tanjung Perak Surabaya
    'departure_time' => now()->addDays(2),
    'estimated_arrival' => now()->addDays(6),
    'berth_code' => 'BERTH-SUB-01',
    'pda_budget_usd' => 16400.0,
]);
```
Jika terjadi kegagalan pada saat pemesanan dermaga atau penguncian ledger, saga otomatis membatalkan pemesanan dermaga (*berth booking cancel*) dan membalikkan jurnal pencatatan transaksi.

---

## 4. Pelaporan Telemetri Harian (Noon Report) & Pemantauan Emisi

Nahkoda atau Kepala Kamar Mesin (KKM) wajib mengirimkan laporan telemetri tengah hari (*Noon Report*) setiap 24 jam sekali:
- Posisi koordinat lintang & bujur (*latitude/longitude*).
- Jarak tempuh dalam 24 jam terakhir (*steaming distance*).
- Kecepatan rata-rata kapal (*average speed through water*).
- Arah dan kekuatan angin skala Beaufort ($0-12$).
- Tinggi gelombang laut dan arah arus (*sea state / swell*).
- Konsumsi bahan bakar harian mesin induk dan mesin bantu (*fuel consumption metric tons*).
- Sisa cadangan bahan bakar di tangki bunker (*ROB - Remaining on Board*).

Sistem secara otomatis menghitung *weather margin penalty* dan memperbarui estimasi peringkat CII tahun berjalan.

---

## 5. Prosedur Penyelesaian Laytime & Sengketa Demurrage

1. **Pengunggahan Dokumen SOF (*Statement of Facts*)**:
   Setelah kapal selesai bongkar, agen pelabuhan mengunggah riwayat jam SOF termasuk jam tender NOR, jam sandar dermaga (*all fast*), jam mulai bongkar kargo, jeda hujan, dan jam pelepasan selang/kargo selesai.
2. **Kalkulasi Otomatis via Algoritma A11**:
   Sistem mengevaluasi total durasi waktu kerja bersih yang digunakan.
3. **Penerbitan Invoice / Nota**:
   - Jika waktu terpakai melampaui alokasi laytime: Sistem menerbitkan `FleetDemurrageInvoice` dan mencatat piutang di buku besar.
   - Jika waktu terpakai lebih hemat dari alokasi laytime: Sistem menerbitkan nota kredit *Despatch Money* sebesar $50\%$ tarif harian demurrage.

---

## 6. Prosedur Penanganan Kedaruratan Maritim & General Average

Dalam situasi darurat di laut (kebakaran, kandas, badai ekstrem, kerusakan kemudi):
1. **Deklarasi General Average**:
   Nahkoda mendeklarasikan status GA dan mencatat waktu resmi di buku harian kapal (*deck log book*).
2. **Pencatatan Tindakan Pengorbanan**:
   - Pencatatan pembuangan muatan (*jettison*) dengan nomor kontainer atau palka kargo.
   - Pencatatan kerusakan mesin/badan kapal yang timbul akibat upaya penyelamatan bersama.
3. **Pencatatan Pengeluaran Bersama**:
   - Kontrak sewa kapal tunda penyelamat (*Emergency Tug / Salvage Agreement LOF*).
   - Biaya masuk dan berlabuh di pelabuhan perlindungan darurat (*Port of Refuge*).
4. **Distribusi Kontribusi York-Antwerp Rules**:
   Panggil `GeneralAverageAdjustmentService::settleGeneralAverageCase` untuk mendistribusikan total pengeluaran kepada Pemilik Kapal, Pemilik Kargo, dan Penikmat Freight sesuai proporsi nilai kontributifnya.
5. **Pelepasan Kargo**:
   Kargo tidak boleh diserahkan kepada penerima (*consignee*) sebelum diterimanya *Average Bond* yang sah dan jaminan asuransi (*Underwriter Guarantee*).

---

## 7. Runbook Audit Mandiri & Verifikasi Integritas Data

Jalankan perintah audit berkala melalui CLI artisan:

```bash
# Menjalankan audit rutin 6-dimensi
php artisan prt:fleet-audit

# Menjalankan uji sabotase korupsi data (Fraud Resistance Verification)
php artisan prt:fleet-audit --corrupt
```

### Indikator Keberhasilan Audit:
- `Total balanced ledger groups verified`: Harus menampilkan angka transaksi berimbang tanpa selisih (0 difference).
- `SHA-256 hash chains verified`: $100\%$ valid, tidak ada manipulasi baris.
- `Statutory certificates checked`: $0$ sertifikat aktif yang kedaluwarsa.
- `Exit code`: `0` saat kondisi normal bersih, dan `1` saat korupsi data terdeteksi.

---

## 8. Panduan Pengujian & Verifikasi Kelaikan Maritim

Untuk memastikan kualitas implementasi kode maritim memenuhi standar enterprise, tim pengembang dan tim QA dapat menjalankan suite pengujian maritim mandiri:

```bash
# Menjalankan seluruh pengujian unit algoritma & state machine maritim
./vendor/bin/pest tests/Unit/Port/

# Menjalankan pengujian skenario penerimaan Badai Selat Malaka & General Average
./vendor/bin/pest tests/Acceptance/Port/MalaccaStraitStormAndGeneralAverageAcceptanceTest.php

# Menjalankan pengujian endpoint HTTP dan verifikasi audit command
./vendor/bin/pest tests/Acceptance/Port/PortFleetHttpAndAuditAcceptanceTest.php
```

### Parameter Ambang Verifikasi Otomatis:
1. **Pencegahan Over-Dispersion Diskaun**: Seluruh perhitungan despatch dibatasi maksimal $50\%$ dari nilai klaim demurrage harian.
2. **Kepatuhan Nilai Kontribusi**: Jumlah seluruh kontribusi General Average ($\sum \text{Kontribusi}$) wajib persis sama dengan total biaya kerugian laut bersama tanpa pembulatan residual.
3. **Penyelarasan Ledger Zero-Difference**: Seluruh buku besar double-entry `prt:fleet-audit` wajib mencatatkan saldo selisih tepat 0.
