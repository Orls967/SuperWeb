<?php

declare(strict_types=1);

namespace Modules\Party\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Party\Domain\Enums\PartyRoleType;
use Modules\Party\Domain\Enums\PartyStatus;
use Modules\Party\Domain\Enums\PartyType;
use Modules\Party\Domain\Models\CreditProfile;
use Modules\Party\Domain\Models\Party;
use Modules\Party\Domain\Models\PartyRole;
use Modules\Party\Exceptions\DuplicatePartyException;
use Modules\Party\Exceptions\InvalidPartyTransitionException;

class PartyService
{
    /**
     * Create a new party with initial role.
     * Checks for potential duplicates before saving.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws DuplicatePartyException if a strong duplicate signal is found
     */
    public function create(array $data): Party
    {
        $this->guardDuplicate($data);

        return DB::transaction(function () use ($data) {
            $party = Party::create([
                'legal_entity_id' => $data['legal_entity_id'] ?? null,
                'type' => $data['type'] ?? PartyType::Company->value,
                'name' => $data['name'],
                'name_normalized' => $this->normalize($data['name']),
                'short_name' => $data['short_name'] ?? null,
                'npwp_hash' => isset($data['npwp']) ? Party::hashIdentifier($data['npwp']) : null,
                'npwp_masked' => isset($data['npwp']) ? Party::maskIdentifier($data['npwp']) : null,
                'nik_hash' => isset($data['nik']) ? Party::hashIdentifier($data['nik']) : null,
                'nik_masked' => isset($data['nik']) ? Party::maskIdentifier($data['nik']) : null,
                'nib' => $data['nib'] ?? null,
                'status' => PartyStatus::Pending->value,
                'kyb_status' => 'pending',
                'is_active' => true,
            ]);

            // Create initial role
            if (isset($data['role'])) {
                $roleVal = $data['role'] instanceof PartyRoleType ? $data['role']->value : (string) $data['role'];
                if (strlen($roleVal) > 50) {
                    throw new \InvalidArgumentException('Role name exceeds maximum length of 50 characters.');
                }
                $scopeType = $data['scope_type'] ?? null;
                if ($scopeType !== null && strlen((string) $scopeType) > 50) {
                    throw new \InvalidArgumentException('Scope type exceeds maximum length of 50 characters.');
                }
                $scopeId = $data['scope_id'] ?? null;
                if ($scopeId !== null && strlen((string) $scopeId) > 100) {
                    throw new \InvalidArgumentException('Scope ID exceeds maximum length of 100 characters.');
                }
                PartyRole::create([
                    'party_id' => $party->id,
                    'role' => $roleVal,
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId,
                    'is_active' => true,
                ]);
            }

            // Create credit profile
            CreditProfile::create([
                'party_id' => $party->id,
                'credit_limit_idr' => $data['credit_limit_idr'] ?? 0,
                'internal_score' => 50,
                'risk_tier' => 'medium',
            ]);

            return $party;
        });
    }

    /**
     * Transition party status with guard.
     *
     * @throws InvalidPartyTransitionException
     */
    public function transitionStatus(Party $party, PartyStatus $newStatus): Party
    {
        if (! $party->status->canTransitionTo($newStatus)) {
            throw new InvalidPartyTransitionException(
                "Cannot transition party from [{$party->status->value}] to [{$newStatus->value}]."
            );
        }

        $party->lockForUpdate();
        $party->status = $newStatus;
        $party->save();

        return $party;
    }

    /**
     * Add a role to a party (idempotent).
     */
    public function addRole(
        Party $party,
        PartyRoleType $role,
        ?string $scopeType = null,
        ?string $scopeId = null
    ): PartyRole {
        if ($scopeType !== null && strlen($scopeType) > 50) {
            throw new \InvalidArgumentException('Scope type exceeds maximum length of 50 characters.');
        }
        if ($scopeId !== null && strlen($scopeId) > 100) {
            throw new \InvalidArgumentException('Scope ID exceeds maximum length of 100 characters.');
        }

        return PartyRole::firstOrCreate(
            [
                'party_id' => $party->id,
                'role' => $role->value,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
            ],
            ['is_active' => true]
        );
    }

    /**
     * Detect potential duplicate parties.
     * Returns list of candidate parties (empty = no duplicates).
     *
     * @param  array<string, mixed>  $data
     * @return Party[]
     */
    public function findDuplicateCandidates(array $data): array
    {
        $candidates = collect();

        // NPWP hash match (strong signal)
        if (isset($data['npwp'])) {
            $hash = Party::hashIdentifier($data['npwp']);
            $candidates = $candidates->merge(
                Party::where('npwp_hash', $hash)->where('is_active', true)->get()
            );
        }

        // NIK hash match (strong signal)
        if (isset($data['nik'])) {
            $hash = Party::hashIdentifier($data['nik']);
            $candidates = $candidates->merge(
                Party::where('nik_hash', $hash)->where('is_active', true)->get()
            );
        }

        // Name + phone fuzzy match (soft signal — dedup candidate only)
        if (isset($data['name']) && isset($data['phone'])) {
            $norm = $this->normalize($data['name']);
            $candidates = $candidates->merge(
                Party::where('name_normalized', $norm)
                    ->whereHas('contacts', fn ($q) => $q->where('value', $data['phone']))
                    ->where('is_active', true)
                    ->get()
            );
        }

        return $candidates->unique('id')->values()->all();
    }

    /**
     * Merge source party into target. Append-only audit trail.
     */
    public function merge(Party $source, Party $target, string $rule, string $reason, ?string $performer = null): void
    {
        DB::transaction(function () use ($source, $target, $rule, $reason, $performer) {
            // Move roles to target (skip duplicates)
            foreach ($source->roles as $role) {
                PartyRole::firstOrCreate(
                    ['party_id' => $target->id, 'role' => $role->role->value, 'scope_type' => $role->scope_type, 'scope_id' => $role->scope_id],
                    ['is_active' => true]
                );
            }

            // Mark source as merged
            $source->update([
                'merged_into_id' => $target->id,
                'merged_at' => now(),
                'merge_reason' => ['rule' => $rule, 'reason' => $reason],
                'is_active' => false,
            ]);

            // Record merge log
            DB::table('pty_merge_logs')->insert([
                'source_party_id' => $source->id,
                'target_party_id' => $target->id,
                'merge_rule' => $rule,
                'reason' => $reason,
                'performed_by' => $performer,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Reverse a merge (reversible via audit trail).
     */
    public function reverseMerge(int $mergeLogId, string $reason): void
    {
        DB::transaction(function () use ($mergeLogId, $reason) {
            $log = DB::table('pty_merge_logs')->lockForUpdate()->find($mergeLogId);

            if (! $log || $log->reversed) {
                return;
            }

            $source = Party::find($log->source_party_id);
            if ($source) {
                $source->update([
                    'merged_into_id' => null,
                    'merged_at' => null,
                    'merge_reason' => null,
                    'is_active' => true,
                ]);
            }

            DB::table('pty_merge_logs')->where('id', $mergeLogId)->update([
                'reversed' => true,
                'reversed_at' => now(),
                'reversal_reason' => $reason,
                'updated_at' => now(),
            ]);
        });
    }

    /** Backfill party_id for a given model (idempotent). */
    public function backfillPartyLink(string $table, string $id, string $partyId): void
    {
        DB::table($table)->where('id', $id)->whereNull('party_id')->update(['party_id' => $partyId]);
    }

    private function guardDuplicate(array $data): void
    {
        if (isset($data['npwp'])) {
            $hash = Party::hashIdentifier($data['npwp']);
            if (Party::where('npwp_hash', $hash)->where('is_active', true)->exists()) {
                throw new DuplicatePartyException('A party with the same NPWP already exists.');
            }
        }
    }

    private function normalize(string $name): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($name)));
    }
}
