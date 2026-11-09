<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Release;

use Modules\Platform\Domain\Models\ReleaseTrain;

final class ReleaseTrainManifestBuilder
{
    /**
     * Builds comprehensive immutable release bundle manifest.
     *
     * @return array{train_code: string, version: string, git_commit_sha: string, docker_image_tag: string, total_changes: int, total_hotfixes: int, checksum: string}
     */
    public function buildManifest(ReleaseTrain $train, string $headCommitSha): array
    {
        $train->loadMissing(['changeRequests', 'hotfixes']);

        $totalChanges = $train->changeRequests->count();
        $totalHotfixes = $train->hotfixes->count();

        $imageTag = "registry.enterprise.local/superweb:{$train->version}";

        $payload = implode(':', [
            $train->train_code,
            $train->version,
            $headCommitSha,
            $imageTag,
            (string) $totalChanges,
            (string) $totalHotfixes,
        ]);

        $checksum = hash('sha256', $payload);

        return [
            'train_code' => $train->train_code,
            'version' => $train->version,
            'git_commit_sha' => $headCommitSha,
            'docker_image_tag' => $imageTag,
            'total_changes' => $totalChanges,
            'total_hotfixes' => $totalHotfixes,
            'checksum' => $checksum,
        ];
    }
}
