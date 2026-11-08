# Definition of Ready (DoR) — Fase 178: Airline Network, Yield, Codeshare & IRROPS (Lini 26)

## 1. Metadata & Identitas Fase
- **ID Fase:** 178
- **Pilar / Lini:** Pilar 5 / Lini 26 (Aviasi, Jaringan Rute, Yield Management & Disrupsi Operasional / IRROPS)
- **Modul Pemilik:** `Modules\Aviation` (`avi_`)
- **Status:** 🔵 DIKERJAKAN
- **Prasyarat:** Fase 177 ✅ (Merge commit `4c930e93ae0af82c97ee348daf8e233aaf2d02a8`), G7-K26 `SeatInventoryReserver`
- **Katalog Kontrak Baru:** G7-K29 `Modules\Insurance\Contracts\ParametricTriggerRecorder` (di zona Insurance)
- **Keputusan Arsitektur:** G7-D48 (Proration Matrix & Multilateral Interline Agreement), G7-D49 (IRROPS Passenger Rights & Care Dispatch Automation PM 89/2015)

---

## 2. Rencana Anggaran Baris (RAB) — Standar W8 T-2
Target Keras B1: **≥ 7.000 baris logika murni** (tidak termasuk Blade, migrasi, seeder, test).
Target Keras B3: **≥ 1.500 baris dokumentasi** di `docs/**/*.md` (kecuali `CODEBASE.md` dan `PROGRESS.md`).

| Lapisan Arsitektur | Rencana B1 | Rincian Komponen Utama |
|---|---|---|
| **Algoritma Bersertifikat & Value Objects** | 1.650 | A6 `FareBucketYieldOptimizer` (Littlewood/EMSR-b diskret), A7 `DisruptedPassengerReaccommodator` (Priority queue multi-leg rebooking), A8 `CodeshareMileageAccrualEngine` (Anti-double dip miles operating vs marketing), A9 `PassengerCareObligationCalculator` (PM 89/2015 delayed flight care obligation) |
| **State Machine & Enums** | 850 | `ScheduleVersionStateMachine` (7 status), `CodeshareAgreementStateMachine` (7 status), `DisruptionCaseStateMachine` (8 status) |
| **Aplikasi (Actions, Services & Sagas)** | 2.800 | Actions: `OptimizeFareBucketsAction`, `ProcessDisruptionCaseAction`, `DispatchPassengerCareAction`, `ReaccommodatePassengersAction`, `SettleCodeshareProrationAction`, `AccrueFrequentFlyerMilesAction`; Sagas: `CodeshareSettlementSaga`, `LoyaltyMilesAccrualSaga`, `DisruptionIrropsSaga` |
| **Integrasi Lintas Modul** | 850 | Hotel (`RoomBlockReserver`), Insurance (`EventCoverBinder` & G7-K29 `ParametricTriggerRecorder`), Logistics (`CustodyVerifier`), Finance Ledger Posting |
| **Console Commands & Exporters** | 800 | `avi:irrops-process`, `avi:yield-rebalance`, `avi:codeshare-prorate`, `avi:disruption-audit`, CSV Exporters: `IrropsManifestCsvExporter`, `CodeshareClearingCsvExporter`, `YieldPerformanceCsvExporter` |
| **HTTP (Controllers, Requests, Policies, ViewModels)** | 950 | Web Controllers, FormRequests, Disruption Policies anti-IDOR, ViewModels responsif P-UI (375px mobile ready) |
| **Total Rencana B1** | **7.900** | **Ambang batas minimal: 7.000 baris (Margin aman: +900 baris)** |

---

## 3. Skema Database Tambahan (Prefix `avi_`)
1. `avi_schedule_versions`: Versi jadwal penerbangan musiman (W/S season IATA) dengan status draft hingga active.
2. `avi_codeshare_agreements`: Perjanjian codeshare antar-maskapai (free-sale, block-space, revenue split bps).
3. `avi_codeshare_flights`: Pemetaan nomor penerbangan marketing carrier ke operating carrier.
4. `avi_disruption_cases`: Kasus disrupsi penerbangan (delay teknis, cuaca, NOTAM, erupsi vulkanik VONA).
5. `avi_disruption_passengers`: Manifest penumpang terdampak beserta penugasan kompensasi & rebooking.
6. `avi_care_vouchers`: Voucer digital kompensasi PM 89/2015 (kategori makan, penginapan hotel, kompensasi tunai Rp300k).
7. `avi_loyalty_accounts`: Akun frequent flyer program penumpang (tier: Classic, Silver, Gold, Platinum).
8. `avi_loyalty_transactions`: Riwayat transaksi akrual dan penukaran miles.
9. `avi_yield_buckets`: Alokasi dinamis inventaris kursi per kelas tarif (Y, B, M, H, Q, V, dll.).
10. `avi_parametric_insurance_claims`: Pencatatan klaim asuransi parametrik delay otomatis.

---

## 4. Kriteria Keberhasilan & Pagar Kualitas
1. B1 logika murni ≥ 7.000 baris (diukur dari `<sha-awal-fase>` = `4c930e93ae0af82c97ee348daf8e233aaf2d02a8`).
2. B3 dokumentasi ≥ 1.500 baris di direktori `docs/`.
3. Metrik Anti-Isian: M-2 ≥ 55%, M-3 ≤ 20%, M-4 duplikasi 10 baris = 0, M-5 = 0.
4. 4 Algoritma A6..A9 lulus unit test dan bersertifikat deterministik.
5. Skenario disrupsi erupsi DPS (Gunung Agung VONA red) teruji di acceptance test: 12 penerbangan dibatalkan, alokasi hotel via `RoomBlockReserver`, klaim asuransi parametrik tercatat via G7-K29.
