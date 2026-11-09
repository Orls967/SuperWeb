<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Release;

final class ReleaseTrainVersionManager
{
    /**
     * Bumps semantic version for weekly release train.
     */
    public function bumpVersion(string $currentVersion, string $bumpType = 'minor'): string
    {
        $clean = ltrim($currentVersion, 'v');
        $parts = explode('.', $clean);

        $major = (int) ($parts[0] ?? 1);
        $minor = (int) ($parts[1] ?? 0);
        $patch = (int) ($parts[2] ?? 0);

        switch ($bumpType) {
            case 'major':
                $major++;
                $minor = 0;
                $patch = 0;
                break;
            case 'minor':
                $minor++;
                $patch = 0;
                break;
            case 'patch':
            default:
                $patch++;
                break;
        }

        return "v{$major}.{$minor}.{$patch}";
    }
}
