<?php

declare(strict_types=1);

namespace Modules\Edu\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class ConferHigherEdAccreditedDegreeAndEndowmentGrantAction extends BaseAction
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * Owner-module Edu action for Lini 20 formal Higher-Ed credit/GPA degree conferral,
     * academic research grant milestone validation, and endowment principal protection.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params): array {
            $convocationCode = (string) ($params['convocation_code'] ?? 'EDU-W3-DEG-001');
            $earnedSks = (int) ($params['earned_sks'] ?? 144);
            $requiredSks = (int) ($params['required_sks'] ?? 144);
            $gpaScaled100 = (int) ($params['gpa_scaled_100'] ?? 350);

            if ($earnedSks < $requiredSks || $gpaScaled100 < 275) {
                throw new DomainException("Degree conferral rejected: earned_sks={$earnedSks}/{$requiredSks}, gpa={$gpaScaled100}");
            }

            $endowmentYieldAvailableMinor = (int) ($params['endowment_yield_available_minor'] ?? 300000000);
            $researchGrantTrancheMinor = (int) ($params['research_grant_tranche_minor'] ?? 120000000);
            if ($researchGrantTrancheMinor > $endowmentYieldAvailableMinor) {
                throw new DomainException('Research grant tranche cannot invade locked university endowment corpus');
            }

            if ($researchGrantTrancheMinor > 0 && $this->ledger !== null) {
                $this->ledger->post(new PostingDTO(
                    type: 'WAVE3_HIGHER_ED_ENDOWMENT',
                    description: "Edu Higher-Ed research grant & degree settlement {$convocationCode}",
                    idempotencyKey: hash('sha256', "EDU:W3:DEGREE:{$convocationCode}:{$researchGrantTrancheMinor}"),
                    entries: Posting::lines()
                        ->debit('wave3:campus_tuition_receivable:IDR', $researchGrantTrancheMinor)
                        ->credit('wave3:campus_endowment_reserve:IDR', $researchGrantTrancheMinor)
                        ->toEntries('IDR'),
                    referenceType: 'EDU_HIGHER_ED_DEGREE',
                    referenceId: $convocationCode,
                ));
            }

            $honorsClassification = $gpaScaled100 >= 351 ? 'CUM_LAUDE' : 'MEMUASKAN';
            $diplomaHash = hash('sha256', "{$convocationCode}:{$earnedSks}:{$gpaScaled100}:{$honorsClassification}");

            $this->audit(
                action: 'edu.wave3.degree_and_endowment_conferred',
                newValues: ['convocation_code' => $convocationCode, 'honors' => $honorsClassification],
                correlationId: "edu_w3_{$convocationCode}",
                impactType: 'higher_ed_credentials'
            );

            return [
                'convocation_code' => $convocationCode,
                'honors_classification' => $honorsClassification,
                'research_grant_tranche_minor' => $researchGrantTrancheMinor,
                'remaining_endowment_yield_minor' => $endowmentYieldAvailableMinor - $researchGrantTrancheMinor,
                'diploma_hash' => $diplomaHash,
            ];
        });
    }
}
