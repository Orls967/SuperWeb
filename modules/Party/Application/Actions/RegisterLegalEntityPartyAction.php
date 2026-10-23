<?php

declare(strict_types=1);

namespace Modules\Party\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Party\Domain\Enums\KybStatus;
use Modules\Party\Domain\Enums\PartyStatus;
use Modules\Party\Domain\Enums\PartyType;
use Modules\Party\Domain\Enums\RiskTier;
use Modules\Party\Domain\Models\CreditProfile;
use Modules\Party\Domain\Models\LegalEntity;
use Modules\Party\Domain\Models\Party;
use Modules\Shared\Application\BaseAction;

class RegisterLegalEntityPartyAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     * @return array{legal_entity: LegalEntity, party: Party, credit_profile: CreditProfile|null}
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params) {
            $name = trim((string) $params['name']);
            $shortName = (string) ($params['short_name'] ?? Str::limit($name, 16, ''));
            $npwp = isset($params['npwp']) ? (string) $params['npwp'] : null;
            $nib = isset($params['nib']) ? (string) $params['nib'] : null;
            $currency = (string) ($params['functional_currency'] ?? 'IDR');

            $legalEntity = LegalEntity::create([
                'id' => (string) Str::uuid(),
                'name' => $name,
                'short_name' => $shortName,
                'entity_type' => (string) ($params['entity_type'] ?? 'company'),
                'parent_id' => $params['parent_id'] ?? null,
                'npwp' => $npwp,
                'nib' => $nib,
                'functional_currency' => $currency,
                'ledger_prefix' => (string) ($params['ledger_prefix'] ?? strtolower(Str::slug($shortName, '_'))),
                'is_active' => true,
            ]);

            $party = Party::create([
                'id' => (string) Str::uuid(),
                'legal_entity_id' => $legalEntity->id,
                'type' => PartyType::Company,
                'name' => $name,
                'name_normalized' => strtoupper($name),
                'short_name' => $shortName,
                'npwp' => $npwp,
                'nib' => $nib,
                'status' => PartyStatus::Verified,
                'kyb_status' => KybStatus::Verified,
                'is_active' => true,
            ]);

            $creditProfile = null;
            if (isset($params['initial_credit_limit_idr'])) {
                $limit = (int) $params['initial_credit_limit_idr'];
                $creditProfile = CreditProfile::create([
                    'party_id' => $party->id,
                    'credit_limit_idr' => $limit,
                    'current_exposure_idr' => 0,
                    'internal_score' => (int) ($params['internal_score'] ?? 750),
                    'risk_tier' => RiskTier::Low,
                    'last_scored_at' => now(),
                ]);
            }

            $this->audit(
                action: 'legal_entity_party_registered',
                auditable: $party,
                newValues: ['name' => $name, 'legal_entity_id' => $legalEntity->id],
                impactType: 'party_onboarding',
            );

            return [
                'legal_entity' => $legalEntity,
                'party' => $party,
                'credit_profile' => $creditProfile,
            ];
        });
    }
}
