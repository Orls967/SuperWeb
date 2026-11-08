<?php

declare(strict_types=1);

namespace Modules\Port\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Port\Application\Services\PortFacilitySecurityAuditService;
use Modules\Port\Domain\Models\FleetPortCall;

/**
 * Action Penerbitan & Eksekusi Deklarasi Keamanan Maritim (ISPS DoS).
 */
final class IssueDeclarationOfSecurityAction
{
    public function __construct(
        private readonly PortFacilitySecurityAuditService $securityService
    ) {}

    public function execute(
        int $portCallId,
        int $shipSecurityLevel,
        int $portSecurityLevel,
        string $ssoName,
        string $pfsoName
    ): array {
        $portCall = FleetPortCall::findOrFail($portCallId);

        $dos = $this->securityService->issueDeclarationOfSecurity(
            $portCall->id,
            $shipSecurityLevel,
            $portSecurityLevel,
            $ssoName,
            $pfsoName
        );

        return [
            'port_call_id' => $portCall->id,
            'declaration' => $dos,
            'enforced_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
