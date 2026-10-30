<?php

declare(strict_types=1);

namespace Tests\Unit\Numerics;

use InvalidArgumentException;
use Modules\Shared\Domain\Numerics\BlockingPairVerifier;
use Modules\Shared\Domain\Numerics\StableMatching;
use UnexpectedValueException;

covers(StableMatching::class, BlockingPairVerifier::class);

describe('StableMatching & BlockingPairVerifier', function () {
    test('vector test with 3 candidates and 2 organizations', function () {
        $candidates = [
            'C1' => ['O1', 'O2'],
            'C2' => ['O1', 'O2'],
            'C3' => ['O1', 'O2'],
        ];

        $organizations = [
            'O1' => ['capacity' => 1, 'preferences' => ['C1', 'C2', 'C3']],
            'O2' => ['capacity' => 2, 'preferences' => ['C3', 'C1', 'C2']],
        ];

        $result = StableMatching::match($candidates, $organizations);

        expect($result['matches']['O1'])->toBe(['C1'])
            ->and($result['matches']['O2'])->toBe(['C3', 'C2'])
            ->and($result['unmatched_candidates'])->toBeEmpty()
            ->and($result['remaining_capacities']['O1'])->toBe(0)
            ->and($result['remaining_capacities']['O2'])->toBe(0)
            ->and($result['candidate_matches']['C1'])->toBe('O1')
            ->and($result['candidate_matches']['C2'])->toBe('O2')
            ->and($result['candidate_matches']['C3'])->toBe('O2')
            ->and($result['certificate']['total_candidates'])->toBe(3)
            ->and($result['certificate']['total_organizations'])->toBe(2)
            ->and($result['certificate']['matched_candidates_count'])->toBe(3)
            ->and($result['certificate']['unmatched_candidates_count'])->toBe(0)
            ->and($result['certificate']['matches'])->toBe($result['matches'])
            ->and($result['certificate']['remaining_capacities'])->toBe($result['remaining_capacities']);

        expect(BlockingPairVerifier::verify($result, $candidates, $organizations))->toBeTrue();
    });

    test('candidate-proposing produces candidate-optimal stable matching', function () {
        $candidates = [
            'C1' => ['O1', 'O2'],
            'C2' => ['O2', 'O1'],
        ];

        $organizations = [
            'O1' => ['capacity' => 1, 'preferences' => ['C2', 'C1']],
            'O2' => ['capacity' => 1, 'preferences' => ['C1', 'C2']],
        ];

        $result = StableMatching::match($candidates, $organizations);

        // Candidate-optimal gives candidates their top choice
        expect($result['candidate_matches']['C1'])->toBe('O1')
            ->and($result['candidate_matches']['C2'])->toBe('O2')
            ->and($result['matches']['O1'])->toBe(['C1'])
            ->and($result['matches']['O2'])->toBe(['C2']);

        expect(BlockingPairVerifier::verify($result, $candidates, $organizations))->toBeTrue();
    });

    test('evicted candidate re-proposes to second choice and gets matched', function () {
        $candidates = [
            'C1' => ['O1', 'O2'],
            'C2' => ['O1'],
        ];

        $organizations = [
            'O1' => ['capacity' => 1, 'preferences' => ['C2', 'C1']],
            'O2' => ['capacity' => 1, 'preferences' => ['C1']],
        ];

        $result = StableMatching::match($candidates, $organizations);

        expect($result['matches']['O1'])->toBe(['C2'])
            ->and($result['matches']['O2'])->toBe(['C1'])
            ->and($result['candidate_matches']['C1'])->toBe('O2')
            ->and($result['candidate_matches']['C2'])->toBe('O1')
            ->and($result['unmatched_candidates'])->toBeEmpty()
            ->and($result['certificate']['matched_candidates_count'])->toBe(2)
            ->and($result['certificate']['unmatched_candidates_count'])->toBe(0);

        expect(BlockingPairVerifier::verify($result, $candidates, $organizations))->toBeTrue();
    });

    test('sorting inside matched organization respects preference rank and tie-break', function () {
        $candidates = [
            'C_B' => ['O1'],
            'C_A' => ['O1'],
            'C_C' => ['O1'],
        ];

        $organizations = [
            'O1' => ['capacity' => 3, 'preferences' => ['C_C', 'C_A', 'C_B']],
        ];

        $result = StableMatching::match($candidates, $organizations);

        // Sorted by O1's preference: C_C first, then C_A, then C_B
        expect($result['matches']['O1'])->toBe(['C_C', 'C_A', 'C_B']);
    });

    test('handles unacceptable candidates and leaves candidates unmatched', function () {
        $candidates = [
            'C1' => ['O1'],
            'C2' => ['O1'],
            'C3' => ['O1'],
        ];

        // O1 only accepts C1 and C2, capacity 1
        $organizations = [
            'O1' => ['capacity' => 1, 'preferences' => ['C1', 'C2']],
        ];

        $result = StableMatching::match($candidates, $organizations);

        expect($result['matches']['O1'])->toBe(['C1'])
            ->and($result['candidate_matches']['C1'])->toBe('O1')
            ->and($result['candidate_matches']['C2'])->toBeNull()
            ->and($result['candidate_matches']['C3'])->toBeNull()
            ->and($result['unmatched_candidates'])->toBe(['C2', 'C3'])
            ->and($result['remaining_capacities']['O1'])->toBe(0)
            ->and($result['certificate']['matched_candidates_count'])->toBe(1)
            ->and($result['certificate']['unmatched_candidates_count'])->toBe(2);

        expect(BlockingPairVerifier::verify($result, $candidates, $organizations))->toBeTrue();
    });

    test('handles integer identifiers for candidates and organizations', function () {
        $candidates = [
            1 => [10, 20],
            2 => [10],
        ];

        $organizations = [
            10 => ['capacity' => 1, 'preferences' => [2, 1]],
            20 => ['capacity' => 1, 'preferences' => [1]],
        ];

        $result = StableMatching::match($candidates, $organizations);

        expect($result['candidate_matches']['2'])->toBe('10')
            ->and($result['candidate_matches']['1'])->toBe('20')
            ->and($result['matches']['10'])->toBe([2])
            ->and($result['matches']['20'])->toBe([1]);

        expect(BlockingPairVerifier::verify($result, $candidates, $organizations))->toBeTrue();
    });

    test('handles list format with id keys', function () {
        $candidates = [
            ['id' => 'cand_1', 'preferences' => ['org_1']],
            ['id' => 'cand_2', 'preferences' => ['org_1']],
        ];

        $organizations = [
            ['id' => 'org_1', 'capacity' => 2, 'preferences' => ['cand_2', 'cand_1']],
        ];

        $result = StableMatching::match($candidates, $organizations);

        expect($result['candidate_matches']['cand_1'])->toBe('org_1')
            ->and($result['candidate_matches']['cand_2'])->toBe('org_1')
            ->and($result['remaining_capacities']['org_1'])->toBe(0);

        $normalizedCandidates = [
            'cand_1' => ['org_1'],
            'cand_2' => ['org_1'],
        ];
        $normalizedOrgs = [
            'org_1' => ['capacity' => 2, 'preferences' => ['cand_2', 'cand_1']],
        ];
        expect(BlockingPairVerifier::verify($result, $normalizedCandidates, $normalizedOrgs))->toBeTrue();
    });

    test('replaces worse matched candidate when a more preferred candidate proposes', function () {
        $candidates = [
            'C1' => ['O1'],
            'C2' => ['O1'],
        ];

        // O1 prefers C2 over C1, but capacity is 1
        $organizations = [
            'O1' => ['capacity' => 1, 'preferences' => ['C2', 'C1']],
        ];

        $result = StableMatching::match($candidates, $organizations);

        expect($result['matches']['O1'])->toBe(['C2'])
            ->and($result['candidate_matches']['C2'])->toBe('O1')
            ->and($result['candidate_matches']['C1'])->toBeNull()
            ->and($result['unmatched_candidates'])->toBe(['C1']);
    });

    test('candidate proposes to non-existent organization skips to next preference', function () {
        $candidates = [
            'C1' => ['NON_EXISTENT', 'O1'],
        ];

        $organizations = [
            'O1' => ['capacity' => 1, 'preferences' => ['C1']],
        ];

        $result = StableMatching::match($candidates, $organizations);

        expect($result['matches']['O1'])->toBe(['C1'])
            ->and($result['candidate_matches']['C1'])->toBe('O1');
    });

    test('invalid candidate format throws InvalidArgumentException', function () {
        $candidates = [
            'C1' => 'invalid_preferences',
        ];

        $organizations = [
            'O1' => ['capacity' => 1, 'preferences' => ['C1']],
        ];

        StableMatching::match($candidates, $organizations);
    })->throws(InvalidArgumentException::class, 'Invalid candidate format');

    test('invalid organization format throws InvalidArgumentException', function () {
        $candidates = [
            'C1' => ['O1'],
        ];

        $organizations = [
            'O1' => 'invalid_org_data',
        ];

        StableMatching::match($candidates, $organizations);
    })->throws(InvalidArgumentException::class, 'Invalid organization format');

    test('organization non-positive capacity throws InvalidArgumentException', function () {
        $candidates = [
            'C1' => ['O1'],
        ];

        $organizations = [
            'O1' => ['capacity' => 0, 'preferences' => ['C1']],
        ];

        StableMatching::match($candidates, $organizations);
    })->throws(InvalidArgumentException::class, 'capacity must be positive');

    test('verifier throws InvalidArgumentException on missing keys in result', function () {
        BlockingPairVerifier::verify([], [], []);
    })->throws(InvalidArgumentException::class, 'missing required keys');

    test('verifier throws UnexpectedValueException when organization capacity is exceeded', function () {
        $candidates = ['C1' => ['O1'], 'C2' => ['O1']];
        $organizations = ['O1' => ['capacity' => 1, 'preferences' => ['C1', 'C2']]];

        $badResult = [
            'matches' => ['O1' => ['C1', 'C2']],
            'candidate_matches' => ['C1' => 'O1', 'C2' => 'O1'],
            'unmatched_candidates' => [],
            'remaining_capacities' => ['O1' => -1],
        ];

        BlockingPairVerifier::verify($badResult, $candidates, $organizations);
    })->throws(UnexpectedValueException::class, 'exceeded capacity');

    test('verifier throws UnexpectedValueException on unknown organization in matches', function () {
        $candidates = ['C1' => ['O1']];
        $organizations = ['O1' => ['capacity' => 1, 'preferences' => ['C1']]];

        $badResult = [
            'matches' => ['UNKNOWN_ORG' => ['C1']],
            'candidate_matches' => ['C1' => 'UNKNOWN_ORG'],
            'unmatched_candidates' => [],
            'remaining_capacities' => ['UNKNOWN_ORG' => 0],
        ];

        BlockingPairVerifier::verify($badResult, $candidates, $organizations);
    })->throws(UnexpectedValueException::class, "Unknown organization 'UNKNOWN_ORG'");

    test('verifier throws UnexpectedValueException when remaining capacity is inconsistent', function () {
        $candidates = ['C1' => ['O1']];
        $organizations = ['O1' => ['capacity' => 2, 'preferences' => ['C1']]];

        $badResult = [
            'matches' => ['O1' => ['C1']],
            'candidate_matches' => ['C1' => 'O1'],
            'unmatched_candidates' => [],
            'remaining_capacities' => ['O1' => 0], // Should be 1!
        ];

        BlockingPairVerifier::verify($badResult, $candidates, $organizations);
    })->throws(UnexpectedValueException::class, 'remaining capacity mismatch');

    test('verifier throws UnexpectedValueException when unacceptable candidate is matched', function () {
        $candidates = ['C1' => ['O1']];
        $organizations = ['O1' => ['capacity' => 1, 'preferences' => []]]; // O1 does not accept C1

        $badResult = [
            'matches' => ['O1' => ['C1']],
            'candidate_matches' => ['C1' => 'O1'],
            'unmatched_candidates' => [],
            'remaining_capacities' => ['O1' => 0],
        ];

        BlockingPairVerifier::verify($badResult, $candidates, $organizations);
    })->throws(UnexpectedValueException::class, 'is unacceptable to organization');

    test('verifier throws UnexpectedValueException when unacceptable org is matched to candidate', function () {
        $candidates = ['C1' => []]; // C1 does not accept O1
        $organizations = ['O1' => ['capacity' => 1, 'preferences' => ['C1']]];

        $badResult = [
            'matches' => ['O1' => ['C1']],
            'candidate_matches' => ['C1' => 'O1'],
            'unmatched_candidates' => [],
            'remaining_capacities' => ['O1' => 0],
        ];

        BlockingPairVerifier::verify($badResult, $candidates, $organizations);
    })->throws(UnexpectedValueException::class, 'is unacceptable to candidate');

    test('verifier throws UnexpectedValueException on candidate_matches bidirectional mismatch', function () {
        $candidates = ['C1' => ['O1'], 'C2' => ['O1']];
        $organizations = ['O1' => ['capacity' => 2, 'preferences' => ['C1', 'C2']]];

        $badResult = [
            'matches' => ['O1' => ['C1']],
            'candidate_matches' => ['C1' => 'O1', 'C2' => 'O1'], // C2 claims O1, but O1 did not match C2
            'unmatched_candidates' => [],
            'remaining_capacities' => ['O1' => 1],
        ];

        BlockingPairVerifier::verify($badResult, $candidates, $organizations);
    })->throws(UnexpectedValueException::class, "not in organization's match list");

    test('verifier throws UnexpectedValueException when org has candidate not reflected in candidate_matches', function () {
        $candidates = ['C1' => ['O1']];
        $organizations = ['O1' => ['capacity' => 1, 'preferences' => ['C1']]];

        $badResult = [
            'matches' => ['O1' => ['C1']],
            'candidate_matches' => ['C1' => null], // C1 claims null!
            'unmatched_candidates' => ['C1'],
            'remaining_capacities' => ['O1' => 0],
        ];

        BlockingPairVerifier::verify($badResult, $candidates, $organizations);
    })->throws(UnexpectedValueException::class, 'matches but candidate_matches maps to NULL.');

    test('verifier continues loop across preferred orgs to detect blocking pair', function () {
        $candidates = [
            'C1' => ['O1', 'O2', 'O3'],
        ];
        $organizations = [
            'O1' => ['capacity' => 1, 'preferences' => []], // C1 unacceptable to O1
            'O2' => ['capacity' => 1, 'preferences' => ['C1']], // C1 acceptable and vacant
            'O3' => ['capacity' => 1, 'preferences' => ['C1']], // C1 assigned here
        ];

        // C1 is matched to O3, but prefers O2 which has vacant quota!
        $badResult = [
            'matches' => ['O1' => [], 'O2' => [], 'O3' => ['C1']],
            'candidate_matches' => ['C1' => 'O3'],
            'unmatched_candidates' => [],
            'remaining_capacities' => ['O1' => 1, 'O2' => 1, 'O3' => 0],
        ];

        // Should detect blocking pair (C1, O2)
        expect(fn () => BlockingPairVerifier::verify($badResult, $candidates, $organizations))
            ->toThrow(UnexpectedValueException::class, "candidate 'C1' and organization 'O2' prefer each other");
    });

    test('verifier detects blocking pair when candidate and vacant org prefer each other', function () {
        $candidates = [
            'C1' => ['O1'],
        ];
        $organizations = [
            'O1' => ['capacity' => 1, 'preferences' => ['C1']],
        ];

        $badResult = [
            'matches' => ['O1' => []],
            'candidate_matches' => ['C1' => null],
            'unmatched_candidates' => ['C1'],
            'remaining_capacities' => ['O1' => 1],
        ];

        BlockingPairVerifier::verify($badResult, $candidates, $organizations);
    })->throws(UnexpectedValueException::class, 'Blocking pair detected');

    test('verifier detects blocking pair when candidate and full org prefer each other over matched candidate', function () {
        $candidates = [
            'C1' => ['O1'],
            'C2' => ['O1'],
        ];
        $organizations = [
            'O1' => ['capacity' => 1, 'preferences' => ['C1', 'C2']],
        ];

        $badResult = [
            'matches' => ['O1' => ['C2']],
            'candidate_matches' => ['C1' => null, 'C2' => 'O1'],
            'unmatched_candidates' => ['C1'],
            'remaining_capacities' => ['O1' => 0],
        ];

        BlockingPairVerifier::verify($badResult, $candidates, $organizations);
    })->throws(UnexpectedValueException::class, 'Blocking pair detected');

    test('verifier validates certificate metadata fields', function () {
        $candidates = ['C1' => ['O1']];
        $organizations = ['O1' => ['capacity' => 1, 'preferences' => ['C1']]];

        $baseResult = [
            'matches' => ['O1' => ['C1']],
            'candidate_matches' => ['C1' => 'O1'],
            'unmatched_candidates' => [],
            'remaining_capacities' => ['O1' => 0],
            'certificate' => [
                'total_candidates' => 1,
                'total_organizations' => 1,
                'matched_candidates_count' => 1,
                'unmatched_candidates_count' => 0,
                'matches' => ['O1' => ['C1']],
                'remaining_capacities' => ['O1' => 0],
            ],
        ];

        // Valid certificate passes
        expect(BlockingPairVerifier::verify($baseResult, $candidates, $organizations))->toBeTrue();

        // Tamper matched_candidates_count
        $t1 = $baseResult;
        $t1['certificate']['matched_candidates_count'] = 999;
        expect(fn () => BlockingPairVerifier::verify($t1, $candidates, $organizations))
            ->toThrow(UnexpectedValueException::class, 'Certificate matched_candidates_count mismatch');

        // Tamper unmatched_candidates_count
        $t2 = $baseResult;
        $t2['certificate']['unmatched_candidates_count'] = 999;
        expect(fn () => BlockingPairVerifier::verify($t2, $candidates, $organizations))
            ->toThrow(UnexpectedValueException::class, 'Certificate unmatched_candidates_count mismatch');

        // Tamper matches
        $t3 = $baseResult;
        $t3['certificate']['matches'] = [];
        expect(fn () => BlockingPairVerifier::verify($t3, $candidates, $organizations))
            ->toThrow(UnexpectedValueException::class, 'Certificate matches mismatch');

        // Tamper remaining_capacities
        $t4 = $baseResult;
        $t4['certificate']['remaining_capacities'] = [];
        expect(fn () => BlockingPairVerifier::verify($t4, $candidates, $organizations))
            ->toThrow(UnexpectedValueException::class, 'Certificate remaining_capacities mismatch');
    });

    test('skala: 2000 candidates x 500 organizations completes in < 2.0s', function () {
        $candCount = 2000;
        $orgCount = 500;

        $orgIds = [];
        $organizations = [];
        for ($o = 1; $o <= $orgCount; $o++) {
            $orgId = "ORG_{$o}";
            $orgIds[] = $orgId;
            $organizations[$orgId] = [
                'capacity' => 4,
                'preferences' => [],
            ];
        }

        $candidates = [];
        for ($c = 1; $c <= $candCount; $c++) {
            $candId = "CAND_{$c}";
            $prefs = [];
            for ($k = 0; $k < 5; $k++) {
                $randOrgIdx = ($c * 7 + $k * 31) % $orgCount;
                $prefs[] = $orgIds[$randOrgIdx];
            }
            $candidates[$candId] = array_values(array_unique($prefs));
        }

        foreach ($candidates as $candId => $prefs) {
            foreach ($prefs as $orgId) {
                $organizations[$orgId]['preferences'][] = $candId;
            }
        }

        $startTime = microtime(true);
        $result = StableMatching::match($candidates, $organizations);
        $elapsed = microtime(true) - $startTime;

        expect($elapsed)->toBeLessThan(2.0)
            ->and($result['certificate']['matched_candidates_count'])->toBeGreaterThan(0);
    })->group('skala');
});
