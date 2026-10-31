<?php

declare(strict_types=1);

namespace Modules\Edu\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Edu\Domain\Models\EduCertificate;
use Modules\Edu\Domain\Models\EduExamAttempt;
use Modules\Edu\Domain\Models\EduJobOpening;
use Modules\Edu\Domain\Models\EduPlacement;
use Modules\Edu\Domain\Models\EduTalentPreference;
use Modules\Shared\Application\BaseAction;
use Modules\Shared\Domain\Numerics\BlockingPairVerifier;
use Modules\Shared\Domain\Numerics\StableMatching;
use UnexpectedValueException;

class MatchTalentPoolAction extends BaseAction
{
    /**
     * Executes candidate-proposing Gale-Shapley matching on eligible talent pool.
     *
     * @param  list<string>  $programCodes
     * @return array<string, mixed>
     */
    public function execute(array $programCodes = []): array
    {
        $data = $this->prepareMatchingData($programCodes);
        $candidates = $data['candidates'];
        $organizations = $data['organizations'];

        if (empty($candidates) || empty($organizations)) {
            return [
                'placements' => [],
                'matches' => [],
                'unmatched_candidates' => array_keys($candidates),
                'remaining_capacities' => array_map(fn ($o) => $o['capacity'], $organizations),
                'certificate' => [],
                'raw_matching' => [
                    'matches' => [],
                    'candidate_matches' => [],
                    'unmatched_candidates' => array_keys($candidates),
                    'remaining_capacities' => array_map(fn ($o) => $o['capacity'], $organizations),
                ],
                'candidate_preferences' => $candidates,
                'organization_data' => $organizations,
            ];
        }

        $result = StableMatching::match($candidates, $organizations);

        // Verify stability invariant via BlockingPairVerifier (PR2)
        $isValid = BlockingPairVerifier::verify($result, $candidates, $organizations);
        if (! $isValid) {
            throw new UnexpectedValueException('Blocking pair detected in Gale-Shapley matching result');
        }

        // Persist placements
        $placements = DB::transaction(function () use ($result, $data) {
            $created = [];
            foreach ($result['matches'] as $orgId => $candidateIds) {
                $opening = $data['openings_by_id'][$orgId] ?? null;
                if (! $opening) {
                    continue;
                }

                foreach ($candidateIds as $candId) {
                    $userId = (int) $candId;
                    $idempotencyKey = "edu:placement:{$orgId}:{$userId}";

                    $placement = EduPlacement::firstOrCreate(
                        ['student_user_id' => $userId, 'opening_id' => $orgId],
                        [
                            'id' => (string) Str::uuid(),
                            'placement_code' => 'PLC-'.strtoupper(substr(hash('sha256', "{$orgId}:{$userId}"), 0, 10)),
                            'placed_at' => Carbon::now()->toDateString(),
                            'guarantee_days' => 90,
                            'status' => 'PLACED',
                            'idempotency_key' => $idempotencyKey,
                        ]
                    );

                    $created[] = $placement;
                }
            }

            return $created;
        });

        return [
            'placements' => $placements,
            'matches' => $result['matches'],
            'unmatched_candidates' => $result['unmatched_candidates'],
            'remaining_capacities' => $result['remaining_capacities'],
            'certificate' => $result['certificate'] ?? [],
            'raw_matching' => $result,
            'candidate_preferences' => $candidates,
            'organization_data' => $organizations,
        ];
    }

    /**
     * Simulates matching deterministically without persisting placements.
     *
     * @param  list<string>  $programCodes
     * @return array<string, mixed>
     */
    public function simulateMatching(array $programCodes = []): array
    {
        $data = $this->prepareMatchingData($programCodes);
        $candidates = $data['candidates'];
        $organizations = $data['organizations'];

        if (empty($candidates) || empty($organizations)) {
            return [
                'matches' => [],
                'unmatched_candidates' => [],
                'remaining_capacities' => [],
            ];
        }

        return StableMatching::match($candidates, $organizations);
    }

    /**
     * @param  list<string>  $programCodes
     * @return array{
     *     candidates: array<string, list<string>>,
     *     organizations: array<string, array{capacity: int, preferences: list<string>}>,
     *     openings_by_id: array<string, EduJobOpening>
     * }
     */
    private function prepareMatchingData(array $programCodes): array
    {
        $query = EduJobOpening::where('status', 'OPEN');
        if (! empty($programCodes)) {
            $query->whereIn('required_credential_code', $programCodes);
        }
        $openings = $query->orderBy('id', 'asc')->get();

        $openingsById = [];
        $organizations = [];
        $openingEligibility = []; // opening_id => list of eligible user_ids

        foreach ($openings as $opening) {
            $opId = (string) $opening->id;
            $openingsById[$opId] = $opening;

            // Find all candidates holding an active credential matching required_credential_code
            $reqCode = (string) $opening->required_credential_code;
            $activeCerts = EduCertificate::where(function ($q) use ($reqCode) {
                $q->where('skill_competency_code', $reqCode)
                    ->orWhereHas('program', function ($pq) use ($reqCode) {
                        $pq->where('program_code', $reqCode);
                    });
            })
                ->where('status', 'ACTIVE')
                ->where('issued_at', '<=', now())
                ->where('expires_at', '>=', now())
                ->get();

            $candidateScores = [];
            foreach ($activeCerts as $cert) {
                $userId = (int) ($cert->student_user_id ?? 0);
                if ($userId <= 0) {
                    continue;
                }

                $score = 75; // Default score
                if ($cert->exam_attempt_id) {
                    $att = EduExamAttempt::find($cert->exam_attempt_id);
                    if ($att) {
                        $score = $att->score;
                    }
                }

                $candidateScores[$userId] = [
                    'user_id' => $userId,
                    'score' => $score,
                    'issued_at' => $cert->issued_at ? $cert->issued_at->timestamp : 0,
                ];
            }

            // Rank eligible candidates: score DESC, issued_at ASC, user_id ASC (PR3 Determinism)
            uasort($candidateScores, function ($a, $b) {
                if ($b['score'] !== $a['score']) {
                    return $b['score'] <=> $a['score'];
                }
                if ($a['issued_at'] !== $b['issued_at']) {
                    return $a['issued_at'] <=> $b['issued_at'];
                }

                return $a['user_id'] <=> $b['user_id'];
            });

            $orgPrefList = array_map(fn ($c) => (string) $c['user_id'], array_values($candidateScores));
            $openingEligibility[$opId] = array_map('strval', array_keys($candidateScores));

            $organizations[$opId] = [
                'capacity' => (int) $opening->capacity,
                'preferences' => $orgPrefList,
            ];
        }

        // Prepare candidate preference lists
        $candidatePreferences = [];
        $allPreferences = EduTalentPreference::orderBy('student_user_id', 'asc')
            ->orderBy('rank_order', 'asc')
            ->get();

        foreach ($allPreferences as $pref) {
            $candId = (string) $pref->student_user_id;
            $opId = (string) $pref->opening_id;

            // Only allow preference if opening exists and candidate is eligible
            if (isset($organizations[$opId]) && in_array($candId, $openingEligibility[$opId] ?? [], true)) {
                $candidatePreferences[$candId][] = $opId;
            }
        }

        // Ensure distinct values per candidate
        foreach ($candidatePreferences as $candId => $prefs) {
            $candidatePreferences[$candId] = array_values(array_unique($prefs));
        }

        ksort($candidatePreferences);
        ksort($organizations);

        return [
            'candidates' => $candidatePreferences,
            'organizations' => $organizations,
            'openings_by_id' => $openingsById,
        ];
    }
}
