<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Search;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Core\Domain\Models\CoreSearchQueryLog;
use Modules\Core\Domain\Models\CoreUserSearchPin;

final class SearchPersonalizationEngine
{
    /**
     * Menyesuaikan dan meningkatkan peringkat hasil pencarian berdasarkan preferensi dan riwayat pengguna.
     *
     * @param  list<array<string, mixed>>  $rankedResults
     * @return list<array<string, mixed>>
     */
    public function applyPersonalization(array $rankedResults, ?Authenticatable $user): array
    {
        if ($user === null || empty($rankedResults)) {
            return $rankedResults;
        }

        $userId = (string) $user->getAuthIdentifier();
        $pinnedKeys = $this->getPinnedEntityKeys($userId);
        $recentModules = $this->getFrequentModules($userId);

        $adjusted = [];
        foreach ($rankedResults as $item) {
            $score = (float) ($item['score'] ?? 1.0);
            $entityKey = ($item['module_code'] ?? '').':'.($item['entity_id'] ?? '');
            $module = (string) ($item['module_code'] ?? '');

            // Boost untuk item yang disematkan oleh pengguna
            if (isset($pinnedKeys[$entityKey])) {
                $score *= 1.50;
                $item['personalization_boost'] = 'pinned';
            }

            // Boost untuk modul yang sering diakses pengguna
            if (isset($recentModules[$module])) {
                $moduleWeight = 1.0 + min(0.30, ($recentModules[$module] * 0.05));
                $score *= $moduleWeight;
                $item['module_affinity_boost'] = round($moduleWeight, 2);
            }

            $item['score'] = round($score, 4);
            $adjusted[] = $item;
        }

        usort($adjusted, static fn (array $a, array $b): int => ($b['score'] <=> $a['score']));

        return $adjusted;
    }

    /**
     * @return array<string, true>
     */
    private function getPinnedEntityKeys(string $userId): array
    {
        $pins = CoreUserSearchPin::where('user_id', $userId)->get(['module_code', 'entity_id']);
        $keys = [];

        foreach ($pins as $pin) {
            $key = $pin->module_code.':'.$pin->entity_id;
            $keys[$key] = true;
        }

        return $keys;
    }

    /**
     * @return array<string, int>
     */
    private function getFrequentModules(string $userId): array
    {
        $logs = CoreSearchQueryLog::where('user_id', $userId)
            ->whereNotNull('clicked_module_code')
            ->limit(50)
            ->pluck('clicked_module_code');

        $frequencies = [];
        foreach ($logs as $module) {
            if ($module !== null && $module !== '') {
                $frequencies[$module] = ($frequencies[$module] ?? 0) + 1;
            }
        }

        return $frequencies;
    }
}
