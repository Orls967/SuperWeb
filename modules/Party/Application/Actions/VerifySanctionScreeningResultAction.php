<?php

declare(strict_types=1);

namespace Modules\Party\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Party\Domain\Enums\PartyStatus;
use Modules\Party\Domain\Enums\SanctionCheckStatus;
use Modules\Party\Domain\Models\Party;
use Modules\Party\Domain\Models\SanctionCheck;
use Modules\Shared\Application\BaseAction;

class VerifySanctionScreeningResultAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): SanctionCheck
    {
        return DB::transaction(function () use ($params) {
            $party = Party::findOrFail($params['party_id']);
            $hits = (array) ($params['hits'] ?? []);
            $score = (float) ($params['match_score'] ?? 0.0);
            $trigger = (string) ($params['trigger'] ?? 'periodic_batch');
            $reviewer = (string) ($params['reviewed_by'] ?? 'AML Compliance Officer');
            $note = (string) ($params['review_note'] ?? 'Sanction check automated screening');

            if (empty($hits) && $score < 0.1) {
                $status = SanctionCheckStatus::Clear;
            } elseif ($score >= 0.8) {
                $status = SanctionCheckStatus::Hit;
                if ($party->status->canTransitionTo(PartyStatus::Suspended)) {
                    $party->update(['status' => PartyStatus::Suspended]);
                }
            } else {
                $status = SanctionCheckStatus::ManualReview;
            }

            $check = SanctionCheck::create([
                'id' => (string) Str::uuid(),
                'party_id' => $party->id,
                'trigger' => $trigger,
                'status' => $status,
                'hits' => $hits,
                'match_score' => $score,
                'reviewed_by' => $reviewer,
                'reviewed_at' => Carbon::now(),
                'review_note' => $note,
            ]);

            $this->audit(
                action: 'sanction_screening_verified',
                auditable: $check,
                newValues: ['party_id' => $party->id, 'status' => $status->value, 'score' => $score],
                impactType: 'compliance_sanction',
            );

            return $check;
        });
    }
}
