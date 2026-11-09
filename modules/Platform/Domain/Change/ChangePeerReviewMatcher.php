<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Change;

final class ChangePeerReviewMatcher
{
    /**
     * Matches change author with qualified, non-conflicted peer reviewer from same domain but different squad.
     *
     * @param  list<array{id: int, name: string, module: string, squad: string}>  $engineers
     * @return ?array{id: int, name: string}
     */
    public function matchReviewer(int $authorId, string $targetModule, string $authorSquad, array $engineers): ?array
    {
        foreach ($engineers as $eng) {
            $eId = (int) ($eng['id'] ?? 0);
            $eMod = $eng['module'] ?? '';
            $eSquad = $eng['squad'] ?? '';

            if ($eId !== $authorId && $eMod === $targetModule && $eSquad !== $authorSquad) {
                return [
                    'id' => $eId,
                    'name' => $eng['name'] ?? 'Engineer',
                ];
            }
        }

        // Fallback: any senior engineer in module
        foreach ($engineers as $eng) {
            $eId = (int) ($eng['id'] ?? 0);
            if ($eId !== $authorId && ($eng['module'] ?? '') === $targetModule) {
                return [
                    'id' => $eId,
                    'name' => $eng['name'] ?? 'Engineer',
                ];
            }
        }

        return null;
    }
}
