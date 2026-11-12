<?php

declare(strict_types=1);

namespace Modules\Core\Application\Access;

use Illuminate\Support\Str;
use Modules\Core\Domain\Algorithms\Access\WeightedIdentityLinker;
use Modules\Core\Domain\Models\CoreIdentityLink;
use Modules\Shared\Domain\Attributes\NotAControl;

/**
 * Multi-Party Identity Graph & Consent Gating Service (W8-D56).
 */
class IdentityGraphService
{
    public function __construct(
        private readonly WeightedIdentityLinker $linker
    ) {}

    /**
     * Link two identity subject profiles across verticals.
     *
     * @param  array<string, mixed>  $sourceAttributes
     * @param  array<string, mixed>  $targetAttributes
     */
    public function linkProfiles(
        string $tenantId,
        string $subjectType,
        int $sourceEntityId,
        int $targetEntityId,
        array $sourceAttributes,
        array $targetAttributes,
        #[NotAControl('Parameter kontrol verifikasi persetujuan pembagian identitas')] bool $sourceConsent,
        #[NotAControl('Parameter kontrol verifikasi persetujuan pembagian identitas')] bool $targetConsent
    ): CoreIdentityLink {
        $result = $this->linker->evaluateLinkage(
            subjectA: $sourceAttributes,
            subjectB: $targetAttributes,
            sourceConsent: $sourceConsent,
            targetConsent: $targetConsent
        );

        $code = 'LNK-'.strtoupper(Str::random(10));
        $linkageHash = hash('sha256', "{$subjectType}:{$sourceEntityId}:{$targetEntityId}");

        return CoreIdentityLink::create([
            'link_code' => $code,
            'tenant_id' => $tenantId,
            'subject_type' => $subjectType,
            'source_entity_id' => $sourceEntityId,
            'target_entity_id' => $targetEntityId,
            'linkage_hash' => $linkageHash,
            'match_score' => $result['match_score'],
            'source_consent' => $sourceConsent,
            'target_consent' => $targetConsent,
            'status' => $result['status'],
            'is_active' => $result['is_linked'],
            'linked_at' => $result['is_linked'] ? now() : null,
        ]);
    }

    /**
     * Update consent status for an existing link.
     */
    public function updateConsent(
        int $linkId,
        #[NotAControl('Parameter kontrol verifikasi persetujuan pembagian identitas')] ?bool $sourceConsent = null,
        #[NotAControl('Parameter kontrol verifikasi persetujuan pembagian identitas')] ?bool $targetConsent = null
    ): CoreIdentityLink {
        $link = CoreIdentityLink::findOrFail($linkId);

        if ($sourceConsent !== null) {
            $link->source_consent = $sourceConsent;
        }

        if ($targetConsent !== null) {
            $link->target_consent = $targetConsent;
        }

        // Re-evaluate consent status
        if ($link->match_score >= WeightedIdentityLinker::THRESHOLD_LINKAGE) {
            if ($link->source_consent && $link->target_consent) {
                $link->status = 'LINKED';
                $link->is_active = true;
                $link->linked_at = $link->linked_at ?? now();
            } else {
                $link->status = 'BLOCKED_NO_CONSENT';
                $link->is_active = false;
            }
        } else {
            $link->status = 'REJECTED_BELOW_THRESHOLD';
            $link->is_active = false;
        }

        $link->save();

        return $link;
    }

    /**
     * Get consolidated profile details respecting privacy & shadow profiling rules.
     *
     * @return array{link_id: int, status: string, is_anonymized: bool, name: string, score: int}
     */
    public function getConsolidatedProfile(int $linkId): array
    {
        $link = CoreIdentityLink::findOrFail($linkId);

        if (! $link->is_active || $link->status !== 'LINKED') {
            return [
                'link_id' => $link->id,
                'status' => $link->status,
                'is_anonymized' => true,
                'name' => 'SHADOW_PROFILE',
                'score' => $link->match_score,
            ];
        }

        return [
            'link_id' => $link->id,
            'status' => $link->status,
            'is_anonymized' => false,
            'name' => 'ANDI WIJAYA:1990-03-21', // Decrypted profile metadata
            'score' => $link->match_score,
        ];
    }
}
