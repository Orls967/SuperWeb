<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Search;

/**
 * Service pemeringkat kontekstual (Context-Aware Query Ranker).
 * Memprioritaskan entitas yang berada di bawah modul operasional yang sama
 * dengan halaman saat ini pengguna melakukan kueri pencarian.
 */
class ContextualQueryRanker
{
    /**
     * Menerapkan boost kontekstual berdasarkan modul rute saat ini.
     *
     * @param  array<int, array<string, mixed>>  $results
     * @param  string|null  $activeModule  Modul halaman aktif (misal: 'AutoServe', 'Contract', dll)
     * @return array<int, array<string, mixed>>
     */
    public function applyContextualBoost(array $results, ?string $activeModule): array
    {
        if (empty($activeModule) || empty($results)) {
            return $results;
        }

        $boosted = [];
        $activeLower = mb_strtolower($activeModule);

        foreach ($results as $item) {
            $itemModule = mb_strtolower((string) ($item['module'] ?? ''));
            $score = (float) ($item['score'] ?? 1.0);

            if ($itemModule === $activeLower) {
                // Berikan bobot tambah 25% jika berasal dari modul yang sama
                $score = round($score * 1.25, 4);
                $item['context_boosted'] = true;
            }

            $item['score'] = $score;
            $boosted[] = $item;
        }

        usort($boosted, static fn (array $a, array $b): int => ($b['score'] ?? 0.0) <=> ($a['score'] ?? 0.0));

        return $boosted;
    }
}
