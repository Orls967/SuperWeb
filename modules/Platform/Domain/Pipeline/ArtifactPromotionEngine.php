<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Pipeline;

final class ArtifactPromotionEngine
{
    /**
     * Promotes verified release build artifact to target environment registry.
     *
     * @return array{artifact_id: string, source_env: string, target_env: string, promoted_at: string, promotion_digest: string}
     */
    public function promoteArtifact(string $imageTag, string $sourceEnv, string $targetEnv, string $builderSignature): array
    {
        $timestamp = now()->toIso8601String();
        $payload = "PROMOTION:{$imageTag}:{$sourceEnv}->{$targetEnv}:{$builderSignature}:{$timestamp}";
        $digest = hash('sha256', $payload);

        return [
            'artifact_id' => $imageTag,
            'source_env' => $sourceEnv,
            'target_env' => $targetEnv,
            'promoted_at' => $timestamp,
            'promotion_digest' => $digest,
        ];
    }
}
