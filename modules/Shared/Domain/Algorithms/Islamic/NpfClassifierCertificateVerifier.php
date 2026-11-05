<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

use RuntimeException;

final class NpfClassifierCertificateVerifier
{
    /**
     * Verifikasi keabsahan klasifikasi NPF dan integritas laporan kepatuhan.
     */
    public static function verify(NpfClassifierResult $result): bool
    {
        // 1. Guard Konsistensi Portofolio
        if ($result->totalPortfolioPrincipalMinor !== ($result->performingPrincipalMinor + $result->nonPerformingPrincipalMinor)) {
            throw new RuntimeException('Integrity violation: Performing plus non-performing principal does not equal total portfolio principal.');
        }

        // 2. Guard Konsistensi Bucket
        $sumBucketPrincipal = 0;
        $sumBucketProvision = 0;
        foreach ($result->bucketSummary as $bucket) {
            $sumBucketPrincipal += $bucket['total_principal_minor'];
            $sumBucketProvision += $bucket['total_provision_minor'];
        }

        if ($sumBucketPrincipal !== $result->totalPortfolioPrincipalMinor) {
            throw new RuntimeException('Integrity violation: Sum of bucket principals does not match total portfolio principal.');
        }

        if ($sumBucketProvision !== $result->totalRequiredProvisionMinor) {
            throw new RuntimeException('Integrity violation: Sum of bucket provisions does not match total required provision.');
        }

        // 3. Verifikasi Hash Kriptografis
        $expectedPayload = json_encode([
            'total_contracts' => $result->totalContracts,
            'total_principal' => $result->totalPortfolioPrincipalMinor,
            'performing_principal' => $result->performingPrincipalMinor,
            'non_performing_principal' => $result->nonPerformingPrincipalMinor,
            'total_provision' => $result->totalRequiredProvisionMinor,
            'npf_gross_bps' => $result->npfGrossRatioBps,
            'npf_net_bps' => $result->npfNetRatioBps,
            'is_compliant' => $result->isCompliantWithPrudentialLimit,
        ], JSON_THROW_ON_ERROR);

        $expectedHash = hash('sha256', $expectedPayload);

        if (! hash_equals($expectedHash, $result->certificateHash)) {
            throw new RuntimeException('Certificate signature mismatch: NPF portfolio classification certificate has been tampered with.');
        }

        return true;
    }
}
