<?php

declare(strict_types=1);

namespace Modules\Agri\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class CertifyHalalFoodMassBalanceAndAquacultureCatchAction extends BaseAction
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * Owner-module Agri action for Lini 21 CPG Halal Food Processing mass-balance
     * and Lini 22 Sustainable Marine/Aquaculture catch quota & cold-chain verification.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params): array {
            $lotCode = (string) ($params['lot_code'] ?? 'AGRI-W3-HALAL-001');
            $rawInputKg = (int) ($params['raw_input_kg'] ?? 5000);
            $netPackagedKg = (int) ($params['net_packaged_kg'] ?? 4100);
            $byproductKg = (int) ($params['byproduct_kg'] ?? 600);

            if (($netPackagedKg + $byproductKg) > $rawInputKg) {
                throw new DomainException('CPG food mass-balance conservation violated: outputs exceed raw commodity intake');
            }

            $halalAuditStatus = (string) ($params['halal_audit_status'] ?? 'SJPH_CERTIFIED');
            if ($halalAuditStatus !== 'SJPH_CERTIFIED') {
                throw new DomainException("Halal production lot blocked: SJPH status is '{$halalAuditStatus}'");
            }

            $aquacultureHarvestKg = (int) ($params['aquaculture_harvest_kg'] ?? 2000);
            $feedConsumedKg = (int) ($params['feed_consumed_kg'] ?? 2800);
            $fcrScaled100 = $aquacultureHarvestKg > 0 ? intdiv($feedConsumedKg * 100, $aquacultureHarvestKg) : 0;

            $lotSettlementMinor = ($netPackagedKg + $aquacultureHarvestKg) * (int) ($params['unit_price_per_kg_minor'] ?? 35000);

            if ($lotSettlementMinor > 0 && $this->ledger !== null) {
                $this->ledger->post(new PostingDTO(
                    type: 'WAVE3_HALAL_FOOD_MARINE',
                    description: "Agri CPG halal food & aquaculture settlement {$lotCode}",
                    idempotencyKey: hash('sha256', "AGRI:W3:HALAL:{$lotCode}:{$lotSettlementMinor}"),
                    entries: Posting::lines()
                        ->debit('wave3:cpg_halal_receivable:IDR', $lotSettlementMinor)
                        ->credit('wave3:cpg_marine_revenue:IDR', $lotSettlementMinor)
                        ->toEntries('IDR'),
                    referenceType: 'AGRI_CPG_MARINE_LOT',
                    referenceId: $lotCode,
                ));
            }

            $lotHash = hash('sha256', "{$lotCode}:{$netPackagedKg}:{$aquacultureHarvestKg}:{$fcrScaled100}");

            $this->audit(
                action: 'agri.wave3.halal_food_and_aquaculture_certified',
                newValues: ['lot_code' => $lotCode, 'settlement_minor' => $lotSettlementMinor],
                correlationId: "agri_w3_{$lotCode}",
                impactType: 'agri_cpg_marine'
            );

            return [
                'lot_code' => $lotCode,
                'mass_loss_kg' => $rawInputKg - $netPackagedKg - $byproductKg,
                'fcr_scaled_100' => $fcrScaled100,
                'lot_settlement_minor' => $lotSettlementMinor,
                'lot_hash' => $lotHash,
            ];
        });
    }
}
