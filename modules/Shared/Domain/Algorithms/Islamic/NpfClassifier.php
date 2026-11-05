<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

use InvalidArgumentException;

/**
 * Algoritma B10: NpfClassifier
 *
 * Klasifikasi kolektibilitas pembiayaan syariah 1-5 sesuai ketentuan regulasi POJK Syariah
 * dan perhitungan cadangan kerugian penurunan nilai (CKPN/PPAP) portofolio.
 */
final class NpfClassifier
{
    public const int PROVISION_KOL1_BPS = 100;   // 1%

    public const int PROVISION_KOL2_BPS = 500;   // 5%

    public const int PROVISION_KOL3_BPS = 1500;  // 15%

    public const int PROVISION_KOL4_BPS = 5000;  // 50%

    public const int PROVISION_KOL5_BPS = 10000; // 100%

    public const int PRUDENTIAL_NPF_LIMIT_BPS = 500; // 5.00%

    /**
     * @param  array<int, array{contract_ref: string, dpd: int, outstanding_principal_minor: int}>  $contracts
     */
    public static function classifyPortfolio(array $contracts): NpfClassifierResult
    {
        $totalContracts = count($contracts);
        $totalPrincipal = 0;
        $performingPrincipal = 0;
        $nonPerformingPrincipal = 0;
        $totalProvision = 0;
        $nonPerformingProvision = 0;

        $bucketSummary = [
            1 => ['count' => 0, 'total_principal_minor' => 0, 'total_provision_minor' => 0],
            2 => ['count' => 0, 'total_principal_minor' => 0, 'total_provision_minor' => 0],
            3 => ['count' => 0, 'total_principal_minor' => 0, 'total_provision_minor' => 0],
            4 => ['count' => 0, 'total_principal_minor' => 0, 'total_provision_minor' => 0],
            5 => ['count' => 0, 'total_principal_minor' => 0, 'total_provision_minor' => 0],
        ];

        $classifiedItems = [];

        foreach ($contracts as $c) {
            $ref = (string) ($c['contract_ref'] ?? '');
            $dpd = (int) ($c['dpd'] ?? 0);
            $principal = (int) ($c['outstanding_principal_minor'] ?? 0);

            if ($principal < 0) {
                throw new InvalidArgumentException("Principal cannot be negative for contract {$ref}.");
            }
            if ($dpd < 0) {
                throw new InvalidArgumentException("DPD cannot be negative for contract {$ref}.");
            }

            [$kol, $kolName, $provBps] = self::evaluateSingleContract($dpd);

            $provisionAmount = (int) intval(round(($principal * $provBps) / 10000.0));

            $totalPrincipal += $principal;
            $totalProvision += $provisionAmount;

            if ($kol <= 2) {
                $performingPrincipal += $principal;
            } else {
                $nonPerformingPrincipal += $principal;
                $nonPerformingProvision += $provisionAmount;
            }

            $bucketSummary[$kol]['count']++;
            $bucketSummary[$kol]['total_principal_minor'] += $principal;
            $bucketSummary[$kol]['total_provision_minor'] += $provisionAmount;

            $classifiedItems[] = [
                'contract_ref' => $ref,
                'dpd' => $dpd,
                'collectibility' => $kol,
                'collectibility_name' => $kolName,
                'principal_minor' => $principal,
                'provision_rate_bps' => $provBps,
                'provision_amount_minor' => $provisionAmount,
            ];
        }

        $npfGrossRatioBps = $totalPrincipal > 0
            ? (int) intval(round(($nonPerformingPrincipal * 10000.0) / $totalPrincipal))
            : 0;

        $netNpfPrincipal = max(0, $nonPerformingPrincipal - $nonPerformingProvision);
        $npfNetRatioBps = $totalPrincipal > 0
            ? (int) intval(round(($netNpfPrincipal * 10000.0) / $totalPrincipal))
            : 0;

        $isCompliant = ($npfGrossRatioBps <= self::PRUDENTIAL_NPF_LIMIT_BPS);

        $certificatePayload = json_encode([
            'total_contracts' => $totalContracts,
            'total_principal' => $totalPrincipal,
            'performing_principal' => $performingPrincipal,
            'non_performing_principal' => $nonPerformingPrincipal,
            'total_provision' => $totalProvision,
            'npf_gross_bps' => $npfGrossRatioBps,
            'npf_net_bps' => $npfNetRatioBps,
            'is_compliant' => $isCompliant,
        ], JSON_THROW_ON_ERROR);

        $certificateHash = hash('sha256', $certificatePayload);

        return new NpfClassifierResult(
            totalContracts: $totalContracts,
            totalPortfolioPrincipalMinor: $totalPrincipal,
            performingPrincipalMinor: $performingPrincipal,
            nonPerformingPrincipalMinor: $nonPerformingPrincipal,
            totalRequiredProvisionMinor: $totalProvision,
            npfGrossRatioBps: $npfGrossRatioBps,
            npfNetRatioBps: $npfNetRatioBps,
            isCompliantWithPrudentialLimit: $isCompliant,
            bucketSummary: $bucketSummary,
            classifiedItems: $classifiedItems,
            certificateHash: $certificateHash,
        );
    }

    /**
     * @return array{0: int, 1: string, 2: int}
     */
    public static function evaluateSingleContract(int $dpd): array
    {
        if ($dpd === 0) {
            return [1, 'Lancar (PASS)', self::PROVISION_KOL1_BPS];
        }

        if ($dpd <= 90) {
            return [2, 'Dalam Perhatian Khusus (SMA)', self::PROVISION_KOL2_BPS];
        }

        if ($dpd <= 120) {
            return [3, 'Kurang Lancar (SUB)', self::PROVISION_KOL3_BPS];
        }

        if ($dpd <= 180) {
            return [4, 'Diragukan (DBT)', self::PROVISION_KOL4_BPS];
        }

        return [5, 'Macet (LOSS)', self::PROVISION_KOL5_BPS];
    }
}
