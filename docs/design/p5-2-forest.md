# Design Specification - P5.2 Lini 23 Kehutanan (Forest `for_`, Fase 172)

## 1. Arsitektur & Namespace
- **Modul**: `Forest` (`modules/Forest`)
- **Service Provider**: `Modules\Forest\ForestServiceProvider`
- **Domain Models**:
  - `Modules\Forest\Domain\Models\ForestConcessionPlot`
  - `Modules\Forest\Domain\Models\ForestTreeInventory`
  - `Modules\Forest\Domain\Models\ForestHarvestPermit`
  - `Modules\Forest\Domain\Models\ForestTimberLog`
  - `Modules\Forest\Domain\Models\ForestTimberSale`
  - `Modules\Forest\Domain\Models\ForestRestorationPlot`
- **Algorithms**:
  - `Modules\Shared\Domain\Algorithms\Forestry\TreeVolumeCalculator` (Menghitung volume pohon silvikultur presisi integer)
- **Application Services**:
  - `Modules\Forest\Application\Services\ForestConcessionService`
- **Audit**:
  - Command: `Modules\Forest\Console\Commands\AuditForestCommand` (`for:audit`)
  - Fixture: `App\Quality\Audit\Fixtures\ForestAuditCorruptionFixture`

## 2. Ledger Mapping
- Akun Didefinisikan di `Modules\Forest\Ledger\ForestAccounts`:
  - `AR_TIMBER`: `1-10230` (Aset Lancar - Piutang Kayu)
  - `REVENUE_TIMBER`: `4-10230` (Pendapatan - Penjualan Kayu)
  - `FORESTRY_LEVY_PAYABLE`: `2-20230` (Kewajiban Lancar - Utang PSDH & Dana Reboisasi)
- Tipe Transaksi di `Modules\Forest\Ledger\ForestTransactionTypes`:
  - `FOREST_TIMBER_SALE` = `'FOREST_TIMBER_SALE'`

## 3. Integrasi Lintas Modul
- Menggunakan `Modules\Esg\Contracts\CarbonCreditIssuer` untuk penerbitan kredit karbon restorasi.
- Menggunakan `Modules\Banking\Contracts\LedgerService` untuk posting entri akuntansi double-entry.
