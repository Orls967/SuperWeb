<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Operations;

use DomainException;
use Modules\Hospital\Domain\Models\MedFridgeBreach;
use Modules\Hospital\Domain\Services\ColdChainMonitoringService;

final class ReleaseColdChainLotAction
{
    public function __construct(
        private readonly ColdChainMonitoringService $coldChainService
    ) {}

    /**
     * @param  array{breach_id: int, stability_protocol_reference: string}  $data
     */
    public function execute(array $data): MedFridgeBreach
    {
        $breachId = (int) $data['breach_id'];
        $breach = MedFridgeBreach::find($breachId);

        if (! $breach) {
            throw new DomainException("Insiden breach rantai dingin #{$breachId} tidak ditemukan.");
        }

        return $this->coldChainService->releaseFromQuarantine(
            breach: $breach,
            stabilityProtocolReference: $data['stability_protocol_reference']
        );
    }
}
