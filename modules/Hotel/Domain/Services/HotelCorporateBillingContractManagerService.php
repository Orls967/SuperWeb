<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

/**
 * Layanan Validasi Kontrak Tagihan Korporat B2B & Penetapan Batas Kredit.
 */
final class HotelCorporateBillingContractManagerService
{
    /**
     * @return array{is_contract_valid: bool, approved_credit_limit_idr: int, allowed_incidentals: bool}
     */
    public function verifyCorporateClient(string $corporateCode): array
    {
        $corporateRegistry = [
            'CORP-PERTAMINA' => [
                'name' => 'PT Pertamina Persero',
                'credit_limit_idr' => 200000000,
                'allowed_incidentals' => true,
            ],
            'CORP-TELKOM' => [
                'name' => 'PT Telkom Indonesia',
                'credit_limit_idr' => 150000000,
                'allowed_incidentals' => false,
            ],
            'CORP-BANKMANDIRI' => [
                'name' => 'PT Bank Mandiri Persero',
                'credit_limit_idr' => 300000000,
                'allowed_incidentals' => true,
            ],
        ];

        if (!isset($corporateRegistry[$corporateCode])) {
            return [
                'is_contract_valid' => false,
                'approved_credit_limit_idr' => 0,
                'allowed_incidentals' => false,
            ];
        }

        return [
            'is_contract_valid' => true,
            'approved_credit_limit_idr' => $corporateRegistry[$corporateCode]['credit_limit_idr'],
            'allowed_incidentals' => $corporateRegistry[$corporateCode]['allowed_incidentals'],
        ];
    }
}
