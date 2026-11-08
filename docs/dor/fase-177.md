# Definition of Ready (DoR) — Fase 177: Aviation Core (Lini 26)

## 1. Metadata & Identitas Fase
- **ID Fase:** 177
- **Pilar / Lini:** Pilar 5 / Lini 26 (Aviasi, Airport Services & Air Cargo)
- **Modul Pemilik:** `Aviation` (`avi_`)
- **Status:** 🔵 DIKERJAKAN
- **Prasyarat:** R1–R4 ✅, G7-P4 (#85) ✅, G7-P5 (#89) ✅, G7-P6 (#93) ✅
- **Katalog Kontrak:** G7-K26 `SeatInventoryReserver`
- **Keputusan Terkait:** G7-D46..G7-D53, D177-1..D177-9, ADR-0177

---

## 2. Rencana Anggaran Baris (RAB) — Standar W8 T-2
Target Keras B1: **≥ 7.000 baris logika** (Blade, migrasi, seeder, test dikecualikan dari B1).

| Lapisan Arsitektur | Rencana B1 | Rincian Komponen Utama |
|---|---|---|
| **Algoritma & Value Object** | 1.150 | A1 `SlotCoordinationAllocator`, A2 `AirworthinessDispatchGate`, A3 `LoadAndBalanceChecker`, A4 `FareRefundCalculator`, A5 `DangerousGoodsEligibilityChecker`, verifier sertifikat, DTO input/output |
| **State Machine & Enums** | 850 | `SlotRequestStateMachine` (8), `FlightStateMachine` (8), `TicketStateMachine` (8), `CargoConsignmentStateMachine` (7), `MaintenanceVisitStateMachine` (7), Guard transisi, transition history listener |
| **Aplikasi (Actions, Services & Sagas)** | 2.550 | Actions master bandara/pesawat/rute, `AllocateSeasonalSlotsAction`, `DispatchFlightGateAction`, `CalculateWeightAndBalanceAction`, `HoldSeatInventoryAction`, `BookPassengerTicketAction`, `ProcessFareRefundAction`, `AcceptAirCargoConsignmentAction`, `ScheduleMaintenanceVisitAction`, Sagas: `PassengerBookingSaga`, `AirCargoAcceptanceSaga`, `FlightCancellationMassRefundSaga` |
| **Integrasi Lintas Modul** | 750 | Kontrak G7-K26 `SeatInventoryReserverService`, Asset K9 `MaintenanceWorkOrderRequester`, Logistics `CustodyVerifier` & `ShipmentBooking`, Party KYC/Sanctions, Hotel `RoomBlockReserver`, Payment gateway adaptor |
| **Posting Ledger** | 420 | `AviationAccounts` (8 akun inti), `AviationLedgerPostingService`, kunci idempotensi `{prefiks}:{aksi}:{id_bisnis}` |
| **HTTP (Controllers, Requests, Policies, ViewModels)** | 920 | 7 Web Controllers, FormRequests dengan autorisasi & validasi strict, Policies anti-IDOR, ViewModels |
| **Console Commands & CSV** | 720 | 5 command terjadwal idempoten, 1 command audit dua sumber `avi:audit`, 4 CSV Exporters |
| **Domain Models & Observers** | 520 | 12 Eloquent Models (aircraft, airport, route, slot, flight, ticket, cargo, maintenance, dll.), immutable facts protection |
| **Total Rencana B1** | **7.880** | **Ambang batas minimal: 7.000 baris (Margin aman: +880 baris)** |

---

## 3. Skema Database (Prefix `avi_`)
Semua tabel memakai foreign key nyata dan constraint integritas unik:
1. `avi_operators`: Operator penerbangan (IATA/ICAO code unique).
2. `avi_airports`: Master bandara (IATA, ICAO unique, koordinat x10000, status koordinasi slot).
3. `avi_aircraft`: Master armada pesawat (registrasi tail-number unique, ICAO type, MTOW, OEW, CG limits, maintenance counters).
4. `avi_routes`: Rute penerbangan (origin airport, destination airport, scheduled duration, distance km).
5. `avi_slots`: Alokasi slot bandara per season (historic flag, usage rate bps).
6. `avi_slot_requests`: Permintaan slot (SM: submitted..expired, 8 status).
7. `avi_slot_request_transitions`: Riwayat transisi slot request append-only.
8. `avi_flights`: Jadwal dan status penerbangan (SM: planned..cancelled, 8 status).
9. `avi_flight_transitions`: Riwayat transisi status penerbangan.
10. `avi_seat_inventories`: Kuota kabin Y dan J per penerbangan, kapasitas, hold, booked, oversell margin.
11. `avi_seat_holds`: Hold kursi TTL 15 menit ber-idempotency key.
12. `avi_tickets`: Tiket penumpang terbit (SM: held..no_show, 8 status, PII blind index).
13. `avi_ticket_transitions`: Riwayat status tiket append-only.
14. `avi_refunds`: Pencatatan pengembalian dana tiket idempoten dengan kalkulasi A4.
15. `avi_cargo_consignments`: Konsinyasi kargo udara (SM: booked..rejected, 7 status, DG classification A5).
16. `avi_cargo_transitions`: Riwayat status kargo udara.
17. `avi_weight_balance_records`: Lembar timbang dan titik berat CG (A3) per penerbangan.
18. `avi_maintenance_visits`: Kunjungan perawatan pesawat (SM: planned..closed, 7 status, link Asset K9).
19. `avi_maintenance_transitions`: Riwayat status kunjungan perawatan.
20. `avi_external_factors`: Parameter kepekaan aviasi berversi (harga avtur, penutupan abu vulkanik).
21. `avi_saga_instances`: State tracker orkestrasi saga terdistribusi lintas modul.

---

## 4. Akun Buku Besar (Ledger `avi:*`)
Didaftarkan di `Modules\Aviation\Ledger\AviationAccounts` dan `LedgerAccountsSeeder`:
- `avi:passenger_unearned:IDR` (Liability): Penjualan tiket sebelum penerbangan berangkat.
- `avi:ticket_revenue:IDR` (Revenue): Pengakuan pendapatan saat penerbangan departed/flown.
- `avi:tax_payable:IDR` (Liability): Pajak bandara/penerbangan yang wajib disetor ke otoritas.
- `avi:refund_payable:IDR` (Liability): Kewajiban refund tiket yang dibatalkan.
- `avi:cargo_receivable:IDR` (Asset): Piutang pendapatan pengiriman kargo udara.
- `avi:cargo_revenue:IDR` (Revenue): Pendapatan angkutan kargo udara.
- `avi:maintenance_expense:IDR` (Expense): Beban operasional pemeliharaan & overhaul pesawat.
- `avi:maintenance_payable:IDR` (Liability): Hutang usaha ke bengkel MRO/Asset.

---

## 5. Algoritma Bersertifikat (A1–A5)
1. **A1 `SlotCoordinationAllocator`**: Alokasi slot musiman IATA WASG 80/20 & reservasi pendatang baru 50%.
2. **A2 `AirworthinessDispatchGate`**: Verifikasi kelaikan terbang multi-faktor (kalender sertifikat, margin jam A-Check, margin siklus, batas MEL deferral).
3. **A3 `LoadAndBalanceChecker`**: Pusat gravitasi (CG) dan amplop berat MTOW pesawat.
4. **A4 `FareRefundCalculator`**: Perhitungan persentase pengembalian dana tiket per kelas tarif dan pita waktu.
5. **A5 `DangerousGoodsEligibilityChecker`**: Validasi kelayakan muatan barang berbahaya (DG) IATA dan holdover time suhu dingin.

---

## 6. Audit Dua Sumber (`avi:audit`)
Memverifikasi 6 dimensi integritas:
1. Okupansi kursi terjual <= kapasitas fisik + kebijakan oversell.
2. Seluruh penerbangan berstatus departed/arrived memiliki sertifikat lolos kelaikan A2 dan timbang A3.
3. Kargo udara memiliki hash chain bukti kustodi yang valid.
4. Jumlah pengembalian dana tiket pada tabel mutasi sama persis dengan jurnal buku besar `avi:refund_payable:IDR`.
5. Pendapatan tiket yang diakui sama persis dengan agregat tiket berstatus flown.
6. Alokasi slot terkonfirmasi tidak melebihi kapasitas jam bandara yang ditetapkan.

Fixture sabotase: `app/Quality/Audit/Fixtures/AviationAuditCorruptionFixture.php` (exit != 0 saat dideteksi ketidaksesuaian).

