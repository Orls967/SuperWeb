# Definition of Ready — Fase 126 (Lini 13: Genco, Grid & Smart Metering)

#### DoR Fase 126
- **Disetujui pemilik:** instruksi eksplisit pemilik pada prompt [G7-P3] (2026-11-06) — lingkup sub-item 126.1–126.5, daftar perluasan wajib, vektor algoritma E1–E3, kontrak G7-K8, dan keputusan G7-D9..D12 serta D126-1..D126-7 ditetapkan di prompt.
- **Tujuan bisnis:** implementasi lengkap sistem smart grid & genco: registri smart meter multi-utility tunggal 15-menit idempoten terhubung aset pembangkit; penghitungan tagihan time-of-use dan demand charge bersertifikat E1 dengan state machine tagihan 8 status; simulasi merit-order dispatch per jam bersertifikat E2 dengan batasan min-stable unit commitment dan pencatatan curtailment; settlement kontrak PPA dan net metering bersertifikat E3; serta penjadwalan beban fleksibel EV charging (G7-K8) ke jendela off-peak dengan bagi reward demand response (LargestRemainder) dan pencatatan manfaat emisi.
- **Modul pemilik & prefiks:** `Egy` / `egy_` (KONSEP §A1 baris 105). Desain & bukti: `docs/design/fase-126.md`.
- **Prasyarat:** Fase 76 ✅ (meter), R1–R4 ✅, Asset (`AssetRegistryLookupInterface`), Contract (`ContractLookupInterface`), Esg (`EmissionBenefitRecorder`), Mobility (`EvChargingSessionSource` G7-K8).
- **Item dikerjakan:** 126.1–126.5 + DAFTAR PERLUASAN WAJIB (dashboard beban vs prakiraan, papan dispatch per jam, profil beban per properti, laporan curtailment, ekspor CSV tagihan). **Ditunda:** tidak ada.
- **Entitas/tabel + state machine:**
  - `egy_parameters`: parameter berversi tarif TOU, feed-in, demand charge, emisi (label S1).
  - `egy_generation_assets`: aset pembangkit (PLTU, gas, surya, baterai, peaker).
  - `egy_grid_nodes`: topologi gardu dan node transmisi/distribusi.
  - `egy_smart_meters`: registri meter tunggal (listrik, air, gas, termal) terhubung node dan properti.
  - `egy_meter_readings`: telemetri 15-menit append-only dengan unique (meter_id, interval_start).
  - `egy_utility_consolidated_invoices`: invoice utilitas dengan 8 status state machine: `draft` -> `validated` -> `issued` -> `partially_paid` -> `paid` -> `overdue` -> `disputed` -> `adjusted`.
  - `egy_utility_invoice_lines`: rincian komponen tagihan (TOU, demand charge, net metering, feed-in).
  - `egy_dispatch_runs` & `egy_dispatch_orders`: keputusan dispatch per jam dan curtailment.
  - `egy_ppa_contracts`: kontrak jual beli listrik jangka panjang dengan produsen independen.
  - `egy_demand_response_events`: event penggeseran beban fleksibel EV dan pembagian insentif.
- **Posting ledger:** tabel posting D126-6 (tagihan listrik, beli dari pembangkit/PPA, feed-in surplus pengurang AR, reward demand response, pembayaran tagihan).
- **Event:** `egy.meter.read.v1`, `egy.bill.issued.v1`, `egy.dispatch.executed.v1`, `egy.ppa.settled.v1`.
- **Role & rute:** `grid_operator`, `energy_billing`, `genco_trader`, `customer`; rute `/energy/grid`, `/energy/dispatch`, `/energy/meters`, `/energy/bills`, `/energy/ppa`, `/customer/energy`.
- **Audit dua-sumber:** `egy:audit` diperluas + `EgyAuditCorruptionFixture` di app/Quality/Audit/Fixtures + AuditCommandRegistry.
- **Tingkat simulasi:** S1 (parameter berversi egy_parameters, label S1, fixture ditandai). **Tier skala:** T1 (20.000 meter × 7 hari interval 15 menit, 12 unit pembangkit, 50 PPA, 2.000 sesi EV).
- **Kontrak lintas modul:** G7-K8 `Modules\Mobility\Contracts\EvChargingSessionSource` (dibuat di Mobility), `AssetRegistryLookupInterface` (Asset), `ContractLookupInterface` (Contract), `EmissionBenefitRecorder` (Esg).
- **Keputusan D-x:** G7-D9, G7-D10, G7-D11, G7-D12, D126-1..D126-7 (DECISIONS).
