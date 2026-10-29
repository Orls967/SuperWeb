<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class OrchestrateAviationMroAirworthinessAndPortBerthAction extends BaseAction
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * Owner-module Logistics action for Lini 26 Aviation Air Cargo / MRO LLP airworthiness
     * and Lini 27 Container Port Berth SOLAS VGM & STS crane stevedoring settlement.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params): array {
            $rotationCode = (string) ($params['rotation_code'] ?? 'LGX-W3-AVIPORT-001');
            $remainingLlpCycles = (int) ($params['remaining_llp_cycles'] ?? 450);
            if ($remainingLlpCycles <= 0) {
                throw new DomainException('Aviation MRO airworthiness gate: aircraft grounded due to zero remaining Life-Limited Part cycles');
            }

            $unverifiedVgmContainers = (int) ($params['unverified_vgm_containers'] ?? 0);
            if ($unverifiedVgmContainers > 0) {
                throw new DomainException("Container Port SOLAS VGM gate blocked loading of {$unverifiedVgmContainers} uncertified container(s)");
            }

            $airCargoKg = (int) ($params['air_cargo_kg'] ?? 12000);
            $airCargoRateMinor = (int) ($params['air_cargo_rate_per_kg_minor'] ?? 18000);
            $portTeuCount = (int) ($params['port_teu_count'] ?? 600);
            $portThcMinor = (int) ($params['port_thc_per_teu_minor'] ?? 150000);

            $multimodalRevenueMinor = ($airCargoKg * $airCargoRateMinor) + ($portTeuCount * $portThcMinor);

            if ($multimodalRevenueMinor > 0 && $this->ledger !== null) {
                $this->ledger->post(new PostingDTO(
                    type: 'WAVE3_PORT_TERMINAL_FASHION',
                    description: "Logistics Aviation MRO & Container Port settlement {$rotationCode}",
                    idempotencyKey: hash('sha256', "LGX:W3:AVIPORT:{$rotationCode}:{$multimodalRevenueMinor}"),
                    entries: Posting::lines()
                        ->debit('wave3:port_fashion_receivable:IDR', $multimodalRevenueMinor)
                        ->credit('wave3:port_fashion_revenue:IDR', $multimodalRevenueMinor)
                        ->toEntries('IDR'),
                    referenceType: 'LGX_AVIATION_PORT',
                    referenceId: $rotationCode,
                ));
            }

            $manifestHash = hash('sha256', "{$rotationCode}:{$airCargoKg}:{$portTeuCount}:{$multimodalRevenueMinor}");

            $this->audit(
                action: 'lgx.wave3.aviation_mro_and_port_berth_settled',
                newValues: ['rotation_code' => $rotationCode, 'multimodal_revenue_minor' => $multimodalRevenueMinor],
                correlationId: "lgx_w3_{$rotationCode}",
                impactType: 'aviation_port_logistics'
            );

            return [
                'rotation_code' => $rotationCode,
                'remaining_llp_cycles' => $remainingLlpCycles,
                'multimodal_revenue_minor' => $multimodalRevenueMinor,
                'manifest_hash' => $manifestHash,
            ];
        });
    }
}
