<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;

/**
 * CustomerCrm360Service (Fase 219)
 *
 * Implements:
 *  - 219.1 & 219.6 Reversible customer master golden record merges
 *  - 219.4 Customer consent management strictly gating cross-line profile sharing
 */
class CustomerCrm360Service
{
    /**
     * Create or retrieve customer golden record.
     */
    public function upsertCustomer(string $goldenId, string $identifierRaw, string $name): object
    {
        $hash = hash('sha256', strtolower(trim($identifierRaw)));

        DB::table('crm_customer_golden_records')->updateOrInsert(
            ['golden_id' => strtoupper($goldenId)],
            [
                'hashed_identifier' => $hash,
                'primary_name' => $name,
                'is_active' => true,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('crm_customer_golden_records')->where('golden_id', strtoupper($goldenId))->first();
    }

    /**
     * Merge child customer record into parent golden record reversibly.
     */
    public function mergeRecords(string $parentGoldenId, string $childGoldenId): object
    {
        $parent = DB::table('crm_customer_golden_records')->where('golden_id', strtoupper($parentGoldenId))->first();
        $child = DB::table('crm_customer_golden_records')->where('golden_id', strtoupper($childGoldenId))->first();

        if (! $parent || ! $child) {
            throw new \InvalidArgumentException('Both parent and child records must exist.');
        }

        $merged = json_decode((string) $parent->merged_child_ids, true) ?: [];
        if (! in_array(strtoupper($childGoldenId), $merged, true)) {
            $merged[] = strtoupper($childGoldenId);
        }

        DB::table('crm_customer_golden_records')->where('golden_id', strtoupper($parentGoldenId))->update([
            'merged_child_ids' => json_encode($merged),
            'updated_at' => now(),
        ]);

        DB::table('crm_customer_golden_records')->where('golden_id', strtoupper($childGoldenId))->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);

        return (object) DB::table('crm_customer_golden_records')->where('golden_id', strtoupper($parentGoldenId))->first();
    }

    /**
     * Undo merge procedure restoring child record.
     */
    public function unmergeRecord(string $parentGoldenId, string $childGoldenId): object
    {
        $parent = DB::table('crm_customer_golden_records')->where('golden_id', strtoupper($parentGoldenId))->first();
        if (! $parent) {
            throw new \InvalidArgumentException('Parent record not found.');
        }

        $merged = json_decode((string) $parent->merged_child_ids, true) ?: [];
        $merged = array_values(array_filter($merged, fn ($id) => $id !== strtoupper($childGoldenId)));

        DB::table('crm_customer_golden_records')->where('golden_id', strtoupper($parentGoldenId))->update([
            'merged_child_ids' => json_encode($merged),
            'updated_at' => now(),
        ]);

        DB::table('crm_customer_golden_records')->where('golden_id', strtoupper($childGoldenId))->update([
            'is_active' => true,
            'updated_at' => now(),
        ]);

        return (object) DB::table('crm_customer_golden_records')->where('golden_id', strtoupper($childGoldenId))->first();
    }

    /**
     * Set or query consent.
     */
    public function setConsent(string $goldenId, string $purpose, bool $granted): object
    {
        $code = 'CST-'.strtoupper($goldenId).'-'.strtoupper($purpose);

        DB::table('crm_customer_consents')->updateOrInsert(
            ['consent_code' => $code],
            [
                'golden_id' => strtoupper($goldenId),
                'purpose' => strtoupper($purpose),
                'is_granted' => $granted,
                'consented_at' => $granted ? now() : null,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('crm_customer_consents')->where('consent_code', $code)->first();
    }

    /**
     * Verify if customer allows cross line sharing.
     */
    public function isSharingAllowed(string $goldenId, string $purpose): bool
    {
        $consent = DB::table('crm_customer_consents')
            ->where('golden_id', strtoupper($goldenId))
            ->where('purpose', strtoupper($purpose))
            ->first();

        return $consent ? (bool) $consent->is_granted : false;
    }

    /**
     * Quality audit gate (`crm:audit`).
     */
    public function audit(): array
    {
        // Check for orphaned active children marked inside parent merged_child_ids
        $activeDuplicates = 0;
        $parents = DB::table('crm_customer_golden_records')->whereNotNull('merged_child_ids')->get();

        foreach ($parents as $p) {
            $children = json_decode((string) $p->merged_child_ids, true) ?: [];
            if (! empty($children)) {
                $activeDuplicates += DB::table('crm_customer_golden_records')
                    ->whereIn('golden_id', $children)
                    ->where('is_active', true)
                    ->count();
            }
        }

        return [
            'status' => $activeDuplicates === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_golden_records' => DB::table('crm_customer_golden_records')->count(),
            'total_consents' => DB::table('crm_customer_consents')->count(),
            'discrepancy_count' => $activeDuplicates,
        ];
    }
}
