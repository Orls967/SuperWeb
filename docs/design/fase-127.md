# Desain Teknis — Fase 127: Water, Waste & District Utilities (Lini 13)

## 1. Arsitektur Domain & ERD
Fase 127 memperluas cakupan modul `Egy` ke utilitas distrik perkotaan & industri:
1. **Water Distribution & DMA**:
   - `egy_water_networks`: Jaringan transmisi & distribusi air.
   - `egy_water_nodes`: Node pipa, stasiun pompa, tandon penampung, sensor tekanan (kPa) dan flow meter (liter).
   - `egy_water_meter_readings`: Pembacaan debit kumulatif & tekanan statis per node.
   - `egy_leak_incidents`: Log insiden anomali kebocoran pipa saat selisih inlet vs outlet DMA melebihi toleransi wajar, secara otomatis memicu Work Order via G7-K9 (`MaintenanceWorkOrderRequester`).
2. **Waste Management & Waste-to-Energy**:
   - `egy_waste_treatment_facilities`: Stasiun pengolahan limbah organik & non-organik serta anaerobic digester.
   - `egy_waste_intakes`: Pencatatan tonase limbah masuk, jenis limbah, dan penagihan tipping fee.
   - `egy_biogas_wte_productions`: Produksi biogas hasil digesti (m³) dan konversi ke energi listrik (kWh) dengan efisiensi genset gas.
3. **District Cooling System (Chilled Water & BTU Metering)**:
   - `egy_district_cooling_plants`: Sentral plant pendingin distrik.
   - `egy_chillers`: Unit chiller spesifik (kapasitas ton pendingin, kW input listrik, koefisien performa COP target).
   - `egy_chilled_water_readings`: Telemetri BTU meter (suhu pasok °C, suhu balik °C, debit gpm/lps, akumulasi thermal kWh). Anomali COP < threshold memicu alert dan work order pemeliharaan chiller via G7-K9.
4. **ESCO Performance Contracting (IPMVP Option C)**:
   - `egy_esco_contracts`: Kontrak penghematan energi dengan baseline regresi/rata-rata, persentase penghematan garansi, split ratio penghematan antara pemilik gedung dan penyedia ESCO.
   - `egy_esco_settlements`: Kalkulasi settlement periodik bersertifikat E6 (`EscoMeasurementVerification`), dengan perhitungan insentif bagi hasil penghematan atau penagihan klaim penalti (clawback) jika target garansi tidak tercapai.
5. **Consolidated Multi-Utility Invoicing**:
   - Tagihan bulanan properti menyatukan meteran listrik (Fase 126), air bersih, air limbah/non-potable, dan pendingin ruangan (district cooling) ke dalam satu invoice konsolidasi tunggal `UtilityConsolidatedInvoice` dengan line items terperinci.

## 2. Algoritma Numerik Bersertifikat
### E5: WaterBalance (`Modules\Shared\Domain\Algorithms\Utility\WaterBalance.php`)
- **Tujuan**: Menghitung neraca air sistem distribusi (IWA Standard Water Balance) dan identifikasi Non-Revenue Water (NRW).
- **Formula**:
  - `System Input Volume (SIV) = Authorized Consumption (AC) + Water Losses (WL)`
  - `Authorized Consumption = Billed Authorized + Unbilled Authorized`
  - `Water Losses = Apparent Losses (meter error, unauthorized) + Real Losses (pipe leaks, bursts)`
  - `Non-Revenue Water (NRW) = SIV - Billed Authorized`
  - `NRW % = (NRW / SIV) * 100`
- **Toleransi**: Nilai integer volume dalam liter. Zero float. Selisih toleransi 0 liter.

### E6: EscoMeasurementVerification (`Modules\Shared\Domain\Algorithms\Utility\EscoMeasurementVerification.php`)
- **Tujuan**: Verifikasi penghematan energi berbasis IPMVP Option C (Whole Facility Baseline vs Reporting Period).
- **Formula**:
  - `Adjusted Baseline Energy = Base Energy * Cooling Degree Days Adjustment (atau faktor operasional)`
  - `Gross Savings (kWh) = Adjusted Baseline Energy - Actual Energy Consumed`
  - `Savings Value (IDR) = Gross Savings * Utility Baseline Tariff (IDR/kWh)`
  - `Guaranteed Savings Value (IDR) = Guaranteed Savings (kWh) * Tariff`
  - `Net Savings Split to ESCO = (Savings Value * ESCO Share %) jika Savings >= Guaranteed`
  - `Shortfall Clawback = (Guaranteed Savings Value - Savings Value) jika Savings < Guaranteed`
- **Toleransi**: Zero float, pembulatan integer minor IDR dengan HalfUp.

## 3. Integrasi Kontrak Lintas Modul
### G7-K9: MaintenanceWorkOrderRequester
- Didefinisikan di `Modules\Asset\Contracts\MaintenanceWorkOrderRequester`:
  ```php
  namespace Modules\Asset\Contracts;

  interface MaintenanceWorkOrderRequester
  {
      public function requestWorkOrder(
          string $originModule,
          string $equipmentReference,
          string $anomalyType,
          string $severity, // LOW, MEDIUM, HIGH, CRITICAL
          string $description,
          array $telemetryContext
      ): string; // returns work order number
  }
  ```
- Implementasi di `Modules\Asset\Application\Services\MaintenanceWorkOrderService` membuat entri `AssetWorkOrder` yang terlacak dan diaudit.

