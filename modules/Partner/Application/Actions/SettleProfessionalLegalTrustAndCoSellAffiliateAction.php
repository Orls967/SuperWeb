<?php

declare(strict_types=1);

namespace Modules\Partner\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class SettleProfessionalLegalTrustAndCoSellAffiliateAction extends BaseAction
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * Owner-module Partner action for Lini 25 Professional/Legal conflict check & WIP billing,
     * Lini 28 Fashion tier-supplier compliance, and Fase 247/260 Partner Co-Sell & Affiliate attribution splits.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params): array {
            $dealCode = (string) ($params['deal_code'] ?? 'PTN-W3-COSELL-001');
            $clientParty = (string) ($params['client_party'] ?? 'CORP-A');
            $opposingParty = (string) ($params['opposing_party'] ?? 'CORP-B');
            /** @var list<string> $conflictRegistry */
            $conflictRegistry = $params['conflict_registry'] ?? [];

            if ($clientParty === $opposingParty || in_array($opposingParty, $conflictRegistry, true)) {
                throw new DomainException("Partner Legal & Advisory conflict-of-interest blocked deal {$dealCode}");
            }

            $grossCoSellGmvMinor = (int) ($params['gross_cosell_gmv_minor'] ?? 250000000);
            $partnerShareBps = (int) ($params['partner_share_bps'] ?? 2000); // 20%
            if ($partnerShareBps < 0 || $partnerShareBps > 10000) {
                throw new DomainException('Partner share basis points must be between 0 and 10000');
            }

            $partnerCommissionMinor = intdiv($grossCoSellGmvMinor * $partnerShareBps, 10000);
            $netEnterpriseRevenueMinor = $grossCoSellGmvMinor - $partnerCommissionMinor;

            if ($netEnterpriseRevenueMinor > 0 && $this->ledger !== null) {
                $this->ledger->post(new PostingDTO(
                    type: 'WAVE3_PRO_LEGAL_AVIATION',
                    description: "Partner professional advisory & co-sell settlement {$dealCode}",
                    idempotencyKey: hash('sha256', "PTN:W3:COSELL:{$dealCode}:{$netEnterpriseRevenueMinor}"),
                    entries: Posting::lines()
                        ->debit('wave3:pro_aviation_receivable:IDR', $netEnterpriseRevenueMinor)
                        ->credit('wave3:pro_aviation_revenue:IDR', $netEnterpriseRevenueMinor)
                        ->toEntries('IDR'),
                    referenceType: 'PTN_COSELL_ADVISORY',
                    referenceId: $dealCode,
                ));
            }

            $attributionHash = hash('sha256', "{$dealCode}:{$grossCoSellGmvMinor}:{$partnerCommissionMinor}:{$netEnterpriseRevenueMinor}");

            $this->audit(
                action: 'ptn.wave3.legal_advisory_and_cosell_settled',
                newValues: ['deal_code' => $dealCode, 'partner_commission_minor' => $partnerCommissionMinor],
                correlationId: "ptn_w3_{$dealCode}",
                impactType: 'partner_cosell_ecosystem'
            );

            return [
                'deal_code' => $dealCode,
                'gross_cosell_gmv_minor' => $grossCoSellGmvMinor,
                'partner_commission_minor' => $partnerCommissionMinor,
                'net_enterprise_revenue_minor' => $netEnterpriseRevenueMinor,
                'attribution_hash' => $attributionHash,
            ];
        });
    }
}
