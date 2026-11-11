<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

/**
 * Pemeta Bagan Akun Lokal ke Master Chart of Accounts Korporat (MCOA) (54.2).
 * Menstandarisasikan kode akun lokal dari berbagai entitas anak perusahaan (ERP heterogen)
 * ke dalam format kode akun kanonikal konsolidasi grup (Holding Standard COA).
 */
class MasterChartOfAccountsMapper
{
    /**
     * Kamus pemetaan kode akun lokal ke kode kanonikal MCOA.
     * Format: [entity_prefix => [local_pattern => canonical_code]]
     */
    private const COA_MAPPING_DICTIONARY = [
        'DEFAULT' => [
            '1100' => '1101-CASH',
            '1101' => '1101-CASH',
            '1102' => '1102-BANK-IDR',
            '1103' => '1103-BANK-USD',
            '1200' => '1201-AR-TRADE',
            '1201' => '1201-AR-TRADE',
            '1205' => '1205-AR-INTERCOMPANY',
            '1300' => '1301-INVENTORY',
            '1301' => '1301-INVENTORY',
            '2100' => '2101-AP-TRADE',
            '2101' => '2101-AP-TRADE',
            '2105' => '2105-AP-INTERCOMPANY',
            '2200' => '2201-SHORT-TERM-DEBT',
            '2300' => '2301-LONG-TERM-DEBT',
            '3100' => '3101-SHARE-CAPITAL',
            '3200' => '3201-RETAINED-EARNINGS',
            '4100' => '4101-REVENUE-EXTERNAL',
            '4105' => '4105-REVENUE-INTERCOMPANY',
            '5100' => '5101-COGS-EXTERNAL',
            '5105' => '5105-COGS-INTERCOMPANY',
            '6100' => '6101-OPEX-SALARY',
            '6200' => '6201-OPEX-RENT-UTILITIES',
            '6300' => '6301-OPEX-ADMIN-LEGAL',
            '7100' => '7101-FINANCIAL-EXPENSE',
            '8100' => '8101-TAX-EXPENSE',
        ],
    ];

    /**
     * Memetakan kode akun lokal entitas anak ke kode kanonikal MCOA korporat.
     */
    public function mapToCanonical(string $entityCode, string $localAccountCode): string
    {
        $cleanCode = strtoupper(trim($localAccountCode));
        $prefix = substr($cleanCode, 0, 4);

        $dict = self::COA_MAPPING_DICTIONARY['DEFAULT'];
        if (isset($dict[$prefix])) {
            return $dict[$prefix];
        }

        // Jika kode lokal sudah memiliki format kanonikal (misal: 1101-CASH)
        if (str_contains($cleanCode, '-')) {
            return $cleanCode;
        }

        return "9999-UNMAPPED-{$cleanCode}";
    }

    /**
     * Mendapatkan klasifikasi kategori akun dari kode kanonikal.
     * (ASSET, LIABILITY, EQUITY, REVENUE, EXPENSE)
     */
    public function getAccountCategory(string $canonicalAccountCode): string
    {
        $leadingDigit = substr($canonicalAccountCode, 0, 1);

        return match ($leadingDigit) {
            '1' => 'ASSET',
            '2' => 'LIABILITY',
            '3' => 'EQUITY',
            '4' => 'REVENUE',
            '5', '6', '7', '8' => 'EXPENSE',
            default => 'OTHER',
        };
    }

    /**
     * Memvalidasi apakah saldo normal akun sesuai dengan standar akuntansi keuangan.
     * Asset & Expense: Normal Debit. Liability, Equity, Revenue: Normal Credit.
     */
    public function validateNormalBalance(string $canonicalAccountCode, int $debitMinor, int $creditMinor): bool
    {
        $cat = $this->getAccountCategory($canonicalAccountCode);

        return match ($cat) {
            'ASSET', 'EXPENSE' => ($debitMinor >= $creditMinor),
            'LIABILITY', 'EQUITY', 'REVENUE' => ($creditMinor >= $debitMinor),
            default => true,
        };
    }
}
