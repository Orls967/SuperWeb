<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Infrastructure\Adapters;

use Modules\EnterpriseFinance\Contracts\ComplianceDeadlineSource;

class RegulatoryLicenseComplianceDeadlineSource implements ComplianceDeadlineSource
{
    public function getSourceName(): string
    {
        return 'RegulatoryLicense';
    }

    public function getDeadlines(string $asOfDate): array
    {
        return [
            [
                'code' => 'LIC-OJK-ANNUAL',
                'title' => 'Penyampaian Laporan Keuangan Tahunan Teraudit OJK',
                'category' => 'REGULATORY',
                'due_date' => date('Y-04-30', strtotime($asOfDate.' +1 year')),
                'responsible_role' => 'finance_controller',
                'entity_code' => 'CORP-HOLDING',
                'penalty_estimate_minor' => 250000000,
            ],
        ];
    }
}
