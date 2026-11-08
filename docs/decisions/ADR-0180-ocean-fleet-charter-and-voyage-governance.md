# ADR-0180: Ocean Fleet, Ship Management & Voyage Governance Architecture

## Status
Diterima (Accepted)

## Konteks
Operasional armada kapal laut (*Ocean Fleet & Ship Management*) pada Lini 27 memerlukan standardisasi arsitektural enterprise untuk mengintegrasikan aspek hukum maritim internasional, efisiensi bahan bakar, keselamatan pelayaran SOLAS, dan akuntansi biaya pelabuhan berimbang. Kode awal berupa kerangka usang `Modules\Integration\Application\Services\OceanFleetService` yang bersifat monolitik mentah dan tidak memiliki kontrol statutori maritim.

## Keputusan
1. **Vertical Slice di Modul Port (`Modules\Port`)**:
   Mendirikan domain armada kapal lengkap dengan tabel awalan `prt_fleet_*` yang mencakup 25 tabel relasional independen.
2. **Mesin Algoritma Maritim Deterministik (A10 s.d. A14)**:
   - A10 `BunkerFuelConsumptionOptimizer`: Formula hukum kubik propulsi dan penalti Beaufort.
   - A11 `LaytimeDemurrageCalculator`: BIMCO Gencon, SHINC/SHEX, pengecualian cuaca hujan WWD, dan prinsip once on demurrage always on demurrage.
   - A12 `CiiCarbonIntensityRatingEngine`: Penilaian emisi karbon IMO MEPC.354(78) peringkat A hingga E.
   - A13 `TimeCharterEquivalentCalculator`: Baltic Exchange net daily TCE rate vs vessel daily OPEX breakeven.
   - A14 `GeneralAverageContributionDistributor`: Alokasi kerugian darurat bersama York-Antwerp Rules 2016 CMI.
3. **State Machine Lifecycle Operasional**:
   - `VoyageStateMachine` dengan 12 status transisi maritim yang ketat.
   - `CharterPartyStateMachine` dengan 8 status perikatan komersial sewa kapal.
4. **Saga Maritim Terkompensasi (`VoyageCharterExecutionSaga`)**:
   Menjamin bahwa pemesanan kapal, pengalokasian dermaga via `VesselCallWindowProvider` (G7-K27), dan pembebanan anggaran PDA diikat secara atomik dengan rollback otomatis bila terjadi kegagalan.
5. **Double-Entry Ledger & Audit Mandiri (`prt:fleet-audit`)**:
   Setiap transaksi uang tambang, bunker, dan pengeluaran pelabuhan wajib dicatat secara berimbang di `prt_fleet_ledger_entries` dengan integritas hash SHA-256 berantai.

## Konsekuensi
- Arsitektur maritim menjadi terstandardisasi dan terlindungi dari manipulasi data.
- Setiap kapal yang mengalami sertifikat statutori kedaluwarsa secara otomatis diblokir dari izin berlayar.
- Biaya operasional dan klaim demurrage dapat diaudit secara transparan sesuai standar pengapalan internasional.

