<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Infrastructure\Adapters;

use Modules\EnterpriseFinance\Contracts\ComplianceDeadlineSource;

class ContractComplianceDeadlineSource implements ComplianceDeadlineSource
{
    public function getSourceName(): string
    {
        return 'ContractManagement';
    }

    public function getDeadlines(string $asOfDate): array
    {
        return [
            [
                'code' => 'CTR-EXPIRY-Q4',
                'title' => 'Tenggat Perpanjangan Kontrak Sewa Gedung Holding',
                'category' => 'CONTRACT',
                'due_date' => date('Y-12-31', strtotime($asOfDate)),
                'responsible_role' => 'legal',
                'entity_code' => 'CORP-HOLDING',
                'penalty_estimate_minor' => 100000000,
            ],
        ];
    }
}

