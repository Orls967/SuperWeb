# Definition of Ready (DoR) — Fase 93: Mine Planning, Fleet Dispatch & Fuel Management

**Modul Pemilik:** Mining (`min_`)  
**Status Eksekusi:** [🔵] Siap Verifikasi  
**Basis Master:** abf89eba  
**Standar Rekayasa:** Protokol G7 + Tambahan Ketat W8  

---

## 1. Rencana Anggaran Baris (RAB) B1 Sesuai T-2

Target Ambang Keras B1: $\ge 6.000$ baris logika bersih (di luar migrasi, seeder, factory, Blade, test).

| Lapisan Arsitektur | Rencana Baris B1 | Target Realisasi | Keterangan Komponen |
|---|---|---|---|
| Algoritma & Value Object (T-4) | 900–1.400 | 1.150 | M1 WeightedTimePhasing, M2 NeedTimeTruckDispatcher, M3 LinearBaselineRegression, VO Sertifikat & DispatchResult |
| State Machine (Enum, Transisi, Guard, Riwayat) | 500–900 | 850 | MinePlan (8 status), HaulCycle (8 status), Equipment (8 status), FuelAnomalyCase (8 status) |
| Aplikasi (Action & Service Bisnis) | 1.800–2.800 | 2.450 | BlockPlanning, ShiftTarget, FleetDispatch, TelemetryIngestion, FuelAnomalyAudit, ReplanTarget, ShiftClose |
| Integrasi (Kontrak, Adaptor, Saga Kompensasi) | 500–900 | 750 | PredictiveMaintenanceSaga, FuelAnomalyHoldSaga, AssetLookupAdapter, MrpRequisitionAdapter |
| Posting Ledger & Chart of Accounts | 300–500 | 450 | MiningAccounts registration, PostingHelper, ContractorPostingAction, FuelHoldPostingAction |
| HTTP (Controller, FormRequest, Policy, ViewModel) | 700–1.100 | 950 | MinePlanController, DispatchController, EquipmentController, FuelController, ContractorPortalController |
| Command Artisan & Laporan CSV | 500–900 | 650 | PhasePlanCommand, IngestTelemetryCommand, ShiftCloseCommand, FuelAnomalyScanCommand, CsvExportServices |
| Model Eloquent Domain (Append-Only Invariants) | 300–500 | 400 | MiningPlan, MiningPlanShift, MiningHaulCycle, MiningFuelAnomalyCase, MiningEquipmentTelemetry |
| **Total Rencana B1** | **5.000–8.900** | **~7.650** | **Memenuhi Ambang Keras $\ge 6.000$** |

---

## 2. Skema Tabel Database (`min_`)

Semua tabel menggunakan integer minor unit, FK nyata, dan constraint unik pencegah duplikasi:
1. `min_mine_plans`:
   - `id` (uuid, primary), `site_id` (foreignUuid -> min_sites), `plan_code` (string, unique), `period_month` (string, YYYY-MM), `commodity` (string), `target_cut_kg` (bigInteger), `target_fill_kg` (bigInteger), `target_ore_kg` (bigInteger), `target_grade_ppm` (bigInteger), `status` (string, default: draft), `version` (integer), `approved_by_user_id` (foreignId -> users, nullable), timestamps.
2. `min_mine_plan_shifts`:
   - `id` (uuid, primary), `mine_plan_id` (foreignUuid -> min_mine_plans), `pit_id` (foreignUuid -> min_pits), `shift_date` (date), `shift_code` (string, SHIFT_A/SHIFT_B), `target_tonnage_kg` (bigInteger), `actual_tonnage_kg` (bigInteger, default 0), `effective_hours_nominal` (integer), `effective_hours_adjusted` (integer), `rain_downtime_hours` (integer, default 0), `maintenance_downtime_hours` (integer, default 0), `variance_kg` (bigInteger, default 0), timestamps.
   - Unique: `['mine_plan_id', 'pit_id', 'shift_date', 'shift_code']`.
3. `min_haul_cycles`:
   - `id` (uuid, primary), `site_id` (foreignUuid -> min_sites), `pit_id` (foreignUuid -> min_pits), `truck_id` (foreignUuid -> min_equipment), `shovel_id` (foreignUuid -> min_equipment), `shift_date` (date), `shift_code` (string), `cycle_seq` (bigInteger), `status` (string), `target_payload_kg` (bigInteger), `actual_payload_kg` (bigInteger, default 0), `distance_meters` (integer), `fuel_consumed_ml` (bigInteger, default 0), `ton_km_scaled` (bigInteger, default 0), `is_override` (boolean, default false), `override_reason` (text, nullable), `override_by_user_id` (foreignId -> users, nullable), timestamps.
   - Unique: `['truck_id', 'shift_date', 'shift_code', 'cycle_seq']`.
4. `min_haul_cycle_histories`:
   - `id` (uuid, primary), `haul_cycle_id` (foreignUuid -> min_haul_cycles), `from_status` (string, nullable), `to_status` (string), `actor_id` (string), `notes` (text, nullable), `recorded_at` (timestamp), timestamps.
5. `min_equipment_telemetries`:
   - `id` (uuid, primary), `equipment_id` (foreignUuid -> min_equipment), `sequence_number` (bigInteger), `recorded_at` (timestamp), `engine_hours_total` (bigInteger), `fuel_level_percent` (integer), `instant_fuel_rate_ml_s` (integer), `payload_kg` (bigInteger), `vibration_rms_scaled` (integer), `latitude_scaled` (integer), `longitude_scaled` (integer), timestamps.
   - Unique: `['equipment_id', 'sequence_number']`.
6. `min_fuel_anomaly_cases`:
   - `id` (uuid, primary), `case_number` (string, unique), `site_id` (foreignUuid -> min_sites), `contractor_party_id` (string), `equipment_id` (foreignUuid -> min_equipment), `shift_date` (date), `shift_code` (string), `actual_fuel_ml` (bigInteger), `baseline_fuel_ml` (bigInteger), `excess_ml` (bigInteger), `excess_bps` (integer), `held_amount_idr` (bigInteger, default 0), `status` (string), `dispute_reason` (text, nullable), `resolution_notes` (text, nullable), timestamps.
7. `min_fuel_anomaly_histories`:
   - `id` (uuid, primary), `case_id` (foreignUuid -> min_fuel_anomaly_cases), `from_status` (string, nullable), `to_status` (string), `actor_id` (string), `notes` (text, nullable), timestamps.
8. `min_contractor_payment_holds`:
   - `id` (uuid, primary), `hold_reference` (string, unique), `contractor_party_id` (string), `case_id` (foreignUuid -> min_fuel_anomaly_cases), `hold_amount_idr` (bigInteger), `released_amount_idr` (bigInteger, default 0), `deducted_amount_idr` (bigInteger, default 0), `status` (string), `idempotency_key` (string, unique), timestamps.
9. `min_external_factors`:
   - `id` (uuid, primary), `factor_key` (string), `version` (integer), `effective_from` (date), `source` (string), `label` (string, regulasi/asumsi_simulasi/fixture), `numeric_value_scaled` (bigInteger), `unit` (string), `metadata_json` (text, nullable), timestamps.
   - Unique: `['factor_key', 'version']`.
10. `min_mining_sagas`:
    - `id` (uuid, primary), `saga_type` (string), `saga_reference` (string, unique), `current_step` (string), `status` (string), `payload_json` (text), `compensation_log_json` (text, nullable), `completed_at` (timestamp, nullable), timestamps.

---

## 3. Akun Buku Besar (Ledger Chart of Accounts) & Arah Posting

Sesuai tabel posting D93-7:
- Pemakaian BBM site:
  - Debit: `expense:min:fuel:IDR` (Beban BBM Operasional)
  - Kredit: persediaan BBM via Inventory StockMovement (`inv:fuel_stock:IDR` / voucher clearance)
- Tagihan Kontraktor Angkut:
  - Debit: `expense:min:hauling_contractor:IDR` (Beban Kontraktor Angkut)
  - Kredit: `ap:min:contractor:{party}:IDR` (Hutang Usaha Kontraktor Tambang)
- Penahanan Pembayaran (Anomali BBM):
  - Debit: `ap:min:contractor:{party}:IDR`
  - Kredit: `min:contractor_payment_hold:IDR` (Dana Kontraktor Ditahan)
- Rilis Penahanan (Kompensasi / Tidak Terbukti):
  - Debit: `min:contractor_payment_hold:IDR`
  - Kredit: `ap:min:contractor:{party}:IDR`
- Potongan Terbukti (Penyelesaian Deviasi BBM):
  - Debit: `min:contractor_payment_hold:IDR`
  - Kredit: `min:fuel_variance_recovery:IDR` (Pemulihan Selisih Biaya BBM)

---

## 4. Peran Pengguna (RBAC) & Endpoint Rute

| Rute Web | Method | Peran Berhak | Fungsi Bisnis |
|---|---|---|---|
| `/mining/plans` | GET, POST | `mine_planner` | Rencana tambang bulanan, kurva-S, alokasi pit-shovel |
| `/mining/plans/{plan}/replan` | POST | `mine_planner` | Penghitungan ulang jam efektif & target akibat curah hujan |
| `/mining/dispatch` | GET, POST | `dispatcher_mine` | Papan dispatch shift (Gantt siklus), penugasan truk M2, override |
| `/mining/dispatch/override` | POST | `dispatcher_mine` | Rekam override armada truk dengan alasan eksplisit |
| `/mining/equipment` | GET | `mine_manager` | Monitoring ketersediaan alat berat, OEE ranking, status telemetri |
| `/mining/fuel` | GET, POST | `finance_officer`, `hse_officer` | Laporan konsumsi BBM, deteksi anomali M3, penahanan pembayaran |
| `/mining/fuel/{case}/resolve` | POST | `finance_officer` | Penyelesaian klaim anomali BBM (rilis atau pemotongan tagihan) |
| `/contractor/mining` | GET | `contractor` | Portal kontraktor terisolasi: data siklus angkut, armada, status bayaran |
| `/contractor/mining/dispute` | POST | `contractor` | Pengajuan sanggahan kasus penahanan anomali BBM |
| `/mining/reports/export` | GET | `mine_manager`, `finance_officer` | Ekspor CSV resmi seluruh angka dasbor produksi & BBM |

---

## 5. Algoritma Bersertifikat Baru

1. **M1 `Modules\Shared\Domain\Algorithms\Scheduling\WeightedTimePhasing`**:
   - Memecah kuota target ke jendela waktu berbobot ketersediaan dengan pembulatan integer Hamilton Largest Remainder.
   - Vektor pengujian: $100.003\text{ ton}$, bobot $[5,5,5,5,4,4,2] \to [16.667, 16.667, 16.667, 16.667, 13.334, 13.334, 6.667]$.
   - Sertifikat: verifikasi total eksak dan toleransi kuota $< 1$.
2. **M2 `Modules\Mining\Domain\Algorithms\NeedTimeTruckDispatcher`**:
   - Menugaskan armada truk ke shovel berdasarkan waktu mulai muat paling awal dan match factor.
   - Vektor pengujian: Shovel S1 & S2, truk T1..T4 dengan kedatangan $t=0,1,2,4$, menghasilkan penugasan $T1 \to S2, T2 \to S1, T3 \to S2, T4 \to S1$.
   - Sertifikat: ketiadaan alternatif waktu mulai lebih awal dan kepatuhan unit armada aktif $\le$ tersedia.
3. **M3 `Modules\Shared\Domain\Algorithms\Stats\LinearBaselineRegression`**:
   - Menghitung garis dasar linier konsumsi bahan bakar terhadap tonase-jarak (ton-km).
   - Vektor pengujian: ton-km (ribu) $[10,20,30,40,50]$, liter $[230,410,620,800,1.010] \to b=39/2, a=29$. Titik baru $35 \to \text{kelebihan } 2.087\text{ bps} > 1.500\text{ bps}$ (Anomali).
   - Sertifikat: verifikasi rumus kuadrat terkecil dan kepatuhan ambang batas.

---

## 6. Audit Dua Sumber (`mining:audit`)

Memverifikasi invariant operasional terhadap fakta transaksi:
1. Total saldo akun `min:contractor_payment_hold:IDR` sama persis dengan akumulasi nilai kasus anomali aktif bertatus `payment_held`.
2. Jumlah penugasan truk pada setiap shift tidak melebihi unit alat berat yang berstatus `available` atau `operating`.
3. Deviasi payload ($\sum \text{aktual} - \sum \text{target}$) konsisten dengan agregat siklus angkut pada tabel fakta `min_haul_cycles`.
4. Work Order perawatan prediktif terbit tepat satu kali untuk setiap peristiwa pembacaan telemetri di atas ambang batas (idempoten).

---

## 7. Matriks Kepekaan Faktor Eksternal (T-5)

| Faktor Eksternal | Parameter Sumber | Elastisitas / Aturan Respons | Jeda Respon | Kelas Gerbang |
|---|---|---|---|---|
| Curah Hujan Harian | `weather_rainfall_mm` | Hujan $> 50\text{ mm}$ memotong jam kerja efektif sebesar $3\text{ jam/shift}$ | Real-time / Harian | `MiningExternalFactors` |
| Harga Acuan Batubara (HBA) | `hba_coal_usd_per_ton` | Penyesuaian batas kelayakan penambangan stripping ratio tinggi | Dwi-mingguan (tgl 1 & 15) | `MiningExternalFactors` |
| Kurs Valuta USD/IDR | `usd_idr_exchange_rate` | Konversi patokan biaya operasi dan acuan batas tagihan | Harian | `MiningExternalFactors` |

---

## 8. Katalog Kasus Pengujian & Seeder T1 (T-6)

Katalog 15 kasus bernama deterministik:
1. `CASE-93-01`: Alokasi standar pit utamanya di Pit Merah (variasi normal).
2. `CASE-93-02`: Alokasi batas kuota tepat pada batas pembulatan Largest Remainder.
3. `CASE-93-03`: Konflik dua shovel meminta truk yang sama secara bersamaan (tie-break idle time).
4. `CASE-93-04`: Shovel breakdown tiba-tiba di tengah shift, redistribusi ke shovel lain.
5. `CASE-93-05`: Hujan ekstrem 10 hari berturut-turut di Site Samarinda (kejutan eksternal).
6. `CASE-93-06`: Pembacaan getaran alat berat melewati 1.800 unit memicu WO perawatan prediktif.
7. `CASE-93-07`: Dugaan pencurian bahan bakar (fuel siphoning) deviasi 2.087 bps pada Kontraktor Borneo Transport.
8. `CASE-93-08`: Penahanan pembayaran kontraktor berhasil dibukukan ke double-entry ledger.
9. `CASE-93-09`: Sanggahan kontraktor diterima setelah pembuktian telemetri sensor elevasi tangki (kompensasi rilis).
10. `CASE-93-10`: Sanggahan kontraktor ditolak, dana dialihkan menjadi pemulihan biaya operasional.
11. `CASE-93-11`: Pembacaan sequence telemetri duplikat ditolak tanpa memicu komputasi ganda.
12. `CASE-93-12`: Dispatcher override armada truk dengan alasan tertulis resmi logistik darurat.
13. `CASE-93-13`: Operasi lintas pergantian shift (Shift A ke Shift B) pada tengah malam WITA.
14. `CASE-93-14`: Penolakan akses portal kontraktor luar terhadap data operasional kontraktor lain (anti-IDOR).
15. `CASE-93-15`: Penutupan shift harian membukukan seluruh tagihan kontraktor berdasarkan tonase terangkut.
