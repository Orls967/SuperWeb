# Definition of Ready (DoR) — Fase 91: PMS, Central Reservation, Rate Management & Smart Room

**Modul Pemilik:** Hotel (`htl_`)  
**Status Eksekusi:** [🔵] Siap Verifikasi  
**Basis Master:** `883516fd`  
**Standar Rekayasa:** Protokol Kereta G7 + Standar Ketat W8 (Poles UI 35%, Kedalaman Logika & Audit Dua Sumber)  

---

## 1. Rencana Anggaran Baris (RAB) Sesuai T-2

Target Ambang Keras B1: $\ge 6.000$ baris logika bersih (di luar migrasi, seeder, factory, Blade, test).
Target B2 (UI): $\ge 35\% \times \text{B1} \ge 2.100$ baris Blade/JS/CSS.
Target B3 (Dokumentasi): $\ge 1.500$ baris Markdown tulis-tangan.

| Lapisan Arsitektur | Rencana B1 (T-2) | Target Realisasi | Keterangan Komponen Fase 91 |
|---|---|---|---|
| Algoritma & Value Object (T-4) | 900–1.400 | 1.250 | H1 OverbookingLimitOptimizer, H2 BestAvailableRateCalculator, H3 HousekeepingRouteSequencer, H4 HvacSetbackController, H5 ServiceRecoveryCompensator, nilai sertifikat & verifier |
| State Machine (Enum, Transisi, Guard, Riwayat) | 500–900 | 850 | ReservationStateMachine (9 status: inquiry..walked), DigitalKeyStateMachine (7 status: pending_activation..reissued), ApoaReportStateMachine (7 status: pending..closed), RatePlanVersionStateMachine (7 status), HousekeepingTaskStateMachine (7 status) |
| Aplikasi (Action & Service Bisnis) | 1.800–2.800 | 2.500 | ReserveRoomCentralizedCrsAction, RelocateOverbookingWalkSagaAction, CheckInWithDigitalKeyAndApoaAction, ProcessEarlyCheckInLateCheckOutAction, RunSetbackScanAction, ExecuteHousekeepingPlanAction, ResolveServiceRecoveryAction, PublishRatePlanAction |
| Integrasi (Kontrak, Adaptor, Saga Kompensasi) | 500–900 | 750 | G7-K9 (Asset WO Requester), G7-K22 (Egy MeterIntervalReader), DigitalTwinInterface (Core), Esg EmissionBenefitRecorder, ApoaSimulationAdapter (S1), SmartLockAdapter (S1), OverbookingWalkRelocationSaga |
| Posting Ledger & Chart of Accounts | 300–500 | 450 | Night audit room revenue posting, early/late checkout charges, overbooking walk expenses & payable, service recovery contra-revenue |
| HTTP (Controller, FormRequest, Policy, ViewModel) | 700–1.100 | 950 | FrontDeskDashboardController, InventoryRateGridController, OverbookingSimulatorController, HousekeepingMobileController, EngineeringBoardController, ApoaQueueController, GuestStayPortalController |
| Command Artisan & Laporan CSV | 500–900 | 700 | hotel:rate-publish, hotel:overbooking-plan, hotel:housekeeping-plan, hotel:apoa-submit, hotel:setback-scan, hotel:audit (dua sumber) |
| Model Eloquent Domain (Append-Only Invariants) | 300–500 | 450 | HotelProperty, HotelRoomType, HotelRoom, HotelReservation, HotelDigitalKey, HotelApoaReport, HotelRatePlanVersion, HotelHousekeepingTask, HotelExternalFactor, HotelSagaState |
| **Total Rencana B1** | **5.000–8.900** | **~7.900** | **Memenuhi Ambang Keras $\ge 6.000$** |

---

## 2. Modul, Prefiks & Skema Tabel Database (`htl_`)

Setiap tabel memakai integer Rupiah minor, FK riil, dan unique constraints:
1. `htl_external_factors`: Parameter berversi (PBJT, Bali tourist levy, no-show distribution bps, occupancy surge, dsb.).
2. `htl_digital_keys`: Kunci kamar digital (token SHA-256 session, valid_from, valid_until, status SM 7).
3. `htl_apoa_reports`: Antrean kepatuhan tamu WNA UU 6/2011 Keimigrasian (guest_id, passport_hash blind index, deadline_at, status SM 7).
4. `htl_rate_plan_versions`: Versi tarif musiman dan event kota (base_rate, floor, ceiling, status SM 7).
5. `htl_housekeeping_tasks`: Tugas pembersihan harian kamar per petugas (priority, credit, status SM 7).
6. `htl_sagas`: Log saga terdistribusi kompensatif (saga_type, correlation_id, step, status, compensation_data).

---

## 3. Akun Ledger & Aturan Posting

Posting berpasangan melalui `Modules\Hotel\Ledger\HotelAccounts`:
- Night Audit: Debit `htl:guest_ledger:IDR`, Kredit `htl:room_revenue:IDR`, `htl:tax_payable:IDR`, `htl:service_charge_payable:IDR`.
- Early/Late Charge: Debit `htl:guest_ledger:IDR`, Kredit `htl:room_revenue:IDR` + pajak.
- Biaya Walk Relocation: Debit `htl:overbooking_expense:IDR`, Kredit `htl:walk_payable:IDR`.
- Service Recovery: Debit `htl:service_recovery_contra_revenue:IDR`, Kredit `htl:guest_ledger:IDR`.

---

## 4. Audit Dua Sumber (`hotel:audit`)

Membandingkan kebenaran faktual database operasional dengan general ledger:
1. Total pendapatan room-night terposting = Total saldo kredit akun `htl:room_revenue:IDR`.
2. Tidak ada room-night terjual melebihi kapasitas fisik + batas overbooking legal (3%).
3. Tidak ada kunci digital kamar aktif setelah checkout atau kedaluwarsa.
4. Tamu WNA memiliki laporan APOA tepat waktu atau tercatat `late`/`escalated`.
5. Voucher kompensasi gangguan kamar cocok dengan contra-revenue ledger.
