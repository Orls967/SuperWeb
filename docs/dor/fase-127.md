# Definition of Ready — Fase 127 (Lini 13: Water, Waste & District Utilities)

#### DoR Fase 127
- **Disetujui pemilik:** instruksi eksplisit pemilik pada prompt [G7-P3] (2026-11-06) — lingkup sub-item 127.1–127.5, daftar perluasan wajib, algoritma numerik E5 (WaterBalance) & E6 (EscoMeasurementVerification), kontrak G7-K9 (MaintenanceWorkOrderRequester), keputusan G7-D13..G7-D16 dan D127-1..D127-7.
- **Tujuan bisnis:** implementasi terpadu utilitas distrik perkotaan & kawasan industri:
  1. Distribusi air bersih & non-potable, smart flow meter, District Metering Area (DMA), deteksi kebocoran pipa berbasis selisih debit/tekanan antar node.
  2. Pengolahan limbah perkotaan/industri, waste-to-energy (biogas m³ → kWh listrik), tipping fee per ton limbah masuk.
  3. District cooling system (chiller plant centralized, thermal kWh / ton-hour, COP monitoring, BTU meter telemetri, chiller staging).
  4. ESCO performance contracting (Energy Service Company): baseline vs actual consumption, guaranteed savings calculation, split-savings settlement schedule, clawback bila underperform (IPMVP Option C via E6).
  5. Tagihan multi-utilitas terpadu (listrik, air, pendingin, limbah) dalam 1 invoice konsolidasi dengan breakdown per meter dan rate tier.
  6. Integrasi maintenance work order via G7-K9 (`MaintenanceWorkOrderRequester`) saat anomali kebocoran air atau COP chiller drop di bawah ambang batas.
- **Modul pemilik & prefiks:** `Egy` / `egy_` (KONSEP §A1 baris 105). Desain & bukti: `docs/design/fase-127.md`.
- **Prasyarat:** Fase 126 🔵, Asset (`AssetRegistryLookupInterface`), Contract (`ContractLookupInterface`), Esg (`EmissionBenefitRecorder`), Maintenance (`MaintenanceWorkOrderRequester` G7-K9).
- **Item dikerjakan:** 127.1–127.5 + DAFTAR PERLUASAN WAJIB (Peta hidrolik skematik jaringan air dengan indikator tekanan real-time, grafik sankey neraca air distrik inlet vs billed vs loss, kalkulator kelayakan finansial retrofit ESCO net present value, matrix optimasi kombinasi chiller baseload vs peaker, ekspor PDF/HTML tagihan utilitas terpadu ramah cetak, audit trail kalibrasi sensor BTU dan flow meter). **Ditunda:** tidak ada.
- **Entitas/tabel + state machine:**
  - `egy_water_networks`: jaringan transmisi/distribusi air dan DMA (District Metering Area).
  - `egy_water_nodes`: node pipa, pompa, tandon, dan junction sensor tekanan/debit.
  - `egy_water_meter_readings`: telemetri flow meter air (liter) & pressure sensor (kPa).
  - `egy_leak_incidents`: deteksi anomali kebocoran pipa (status: `detected`, `dispatched_work_order`, `repaired`, `verified_closed`).
  - `egy_waste_treatment_facilities`: fasilitas pengolahan limbah & digester biogas.
  - `egy_waste_intakes`: penerimaan limbah masuk (berat kg/ton, tipe limbah, tipping fee).
  - `egy_biogas_wte_productions`: produksi biogas (m³) dan konversi ke listrik (kWh).
  - `egy_district_cooling_plants`: stasiun sentral chiller cooling plant.
  - `egy_chillers`: unit chiller individual dengan pemantauan kapasitas, electrical kW, thermal cooling ton/kWh_th, dan COP.
  - `egy_chilled_water_readings`: telemetri BTU meter air dingin (flow rate, delta T, thermal kWh).
  - `egy_esco_contracts`: kontrak ESCO performance contracting (baseline formula, guaranteed savings, split ratio).
  - `egy_esco_settlements`: settlement periodik penghematan energi ESCO (status: `calculated`, `verified`, `approved`, `settled`, `clawbacked`).
- **Posting ledger:**
  - Tagihan air properti: Debit `ar:egy:customer:{party}:IDR`, Kredit `egy:district_water_revenue:IDR`.
  - Tagihan pendingin distrik: Debit `ar:egy:customer:{party}:IDR`, Kredit `egy:district_cooling_revenue:IDR`.
  - Tipping fee limbah masuk: Debit `ar:egy:waste_collector:{party}:IDR` atau `clearing:external:IDR`, Kredit `egy:waste_treatment_revenue:IDR`.
  - Biogas WTE generation: Debit internal offset atau `egy:power_purchase_expense:IDR`, Kredit `egy:wte_generation_revenue:IDR`.
  - ESCO savings split payable: Debit `egy:energy_efficiency_expense:IDR`, Kredit `ap:egy:esco_provider:{party}:IDR`.
  - ESCO underperformance clawback: Debit `ar:egy:esco_provider:{party}:IDR`, Kredit `egy:energy_efficiency_expense:IDR`.
- **Event:** `egy.water.leak_detected.v1`, `egy.waste.intake_logged.v1`, `egy.cooling.anomaly_detected.v1`, `egy.esco.settled.v1`.
- **Role & rute:** `district_operator`, `water_engineer`, `cooling_plant_manager`, `waste_manager`, `esco_auditor`, `customer`; rute `/district/dashboard`, `/district/water`, `/district/waste`, `/district/cooling`, `/district/esco`, `/district/bills`.
- **Audit dua-sumber:** `egy:audit` diperluas dengan verifikasi neraca air distrik (water balance Σ inflow = Σ billed + NRW), neraca chiller plant (COP dan thermal kWh), neraca limbah tipping fee vs WTE biogas kWh, serta konsistensi ESCO settlement dengan ledger AR/AP.
- **Tingkat simulasi:** S1 (parameter berversi tarif air, cooling, tipping fee, standar COP chiller). **Tier skala:** T1 (10 DMA air, 5.000 flow meter air, 2 chiller plant sentral 12 unit, 1 fasilitas WTE, 20 kontrak ESCO).
- **Kontrak lintas modul:** G7-K9 `Modules\Asset\Contracts\MaintenanceWorkOrderRequester` (dibuat di Asset), `AssetRegistryLookupInterface` (Asset), `ContractLookupInterface` (Contract), `EmissionBenefitRecorder` (Esg).
- **Keputusan D-x:** G7-D13, G7-D14, G7-D15, G7-D16, D127-1..D127-7 (DECISIONS).

