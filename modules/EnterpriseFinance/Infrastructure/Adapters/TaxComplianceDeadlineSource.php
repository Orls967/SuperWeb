<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Infrastructure\Adapters;

use Modules\EnterpriseFinance\Contracts\ComplianceDeadlineSource;

class TaxComplianceDeadlineSource implements ComplianceDeadlineSource
{
    public function getSourceName(): string
    {
        return 'TaxCompliance';
    }

    public function getDeadlines(string $asOfDate): array
    {
        return [
            [
                'code' => 'TAX-PPN-MONTHLY',
                'title' => 'Pelaporan SPT Masa PPN Akhir Bulan',
                'category' => 'TAXATION',
                'due_date' => date('Y-m-t', strtotime($asOfDate)),
                'responsible_role' => 'finance_controller',
                'entity_code' => 'CORP-HOLDING',
                'penalty_estimate_minor' => 50000000,
            ],
            [
                'code' => 'TAX-PPH21-MONTHLY',
                'title' => 'Penyetoran PPh Pasal 21 Masa',
                'category' => 'TAXATION',
                'due_date' => date('Y-m-10', strtotime($asOfDate.' +1 month')),
                'responsible_role' => 'finance_controller',
                'entity_code' => 'CORP-HOLDING',
                'penalty_estimate_minor' => 25000000,
            ],
        ];
    }
}
