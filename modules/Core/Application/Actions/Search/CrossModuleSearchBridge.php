<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Search;

use App\Models\User;
use Modules\Core\Domain\Models\CoreSearchIndex;

/**
 * Service jembatan pencarian asosiatif antar-modul (Cross-Module Search Bridge).
 * Menemukan entitas terkait lintas divisi berdasarkan entitas referensi saat ini.
 */
class CrossModuleSearchBridge
{
    /**
     * Menemukan dokumen terkait dari modul lain untuk sebuah entitas acuan.
     *
     * @return array<int, array{id: string, module: string, title: string, url: string}>
     */
    public function findRelatedEntities(string $module, string $entityId, ?User $user = null): array
    {
        $related = CoreSearchIndex::query()
            ->where('entity_id', $entityId)
            ->where('module', '!=', $module)
            ->limit(5)
            ->get();

        $results = [];
        foreach ($related as $doc) {
            if ($doc->required_permission !== null) {
                if ($user === null || ($user->role !== 'admin' && method_exists($user, 'can') && ! $user->can($doc->required_permission))) {
                    continue;
                }
            }

            $results[] = [
                'id' => (string) $doc->id,
                'module' => $doc->module,
                'title' => $doc->title,
                'url' => (string) ($doc->metadata['url'] ?? '#'),
            ];
        }

        return $results;
    }
}
