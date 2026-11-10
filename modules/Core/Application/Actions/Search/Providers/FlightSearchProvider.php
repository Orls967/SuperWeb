<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Search\Providers;

use App\Models\User;
use Modules\Core\Domain\Models\CoreSearchIndex;
use Modules\Shared\Contracts\SearchProviderInterface;

/**
 * Penyedia pencarian penerbangan, tiket maskapai, dan jadwal kargo udara.
 */
class FlightSearchProvider implements SearchProviderInterface
{
    /**
     * @return list<array{category: string, title: string, subtitle: string, badge: string, badge_color: string, url: string}>
     */
    public function search(string $keyword, ?User $user = null): array
    {
        $q = trim($keyword);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $indices = CoreSearchIndex::query()
            ->where('module', 'Flight')
            ->where(function ($query) use ($q) {
                $query->where('title', 'LIKE', "%{$q}%")
                    ->orWhere('content', 'LIKE', "%{$q}%")
                    ->orWhere('entity_id', 'LIKE', "%{$q}%");
            })
            ->limit(5)
            ->get();

        $results = [];
        foreach ($indices as $idx) {
            if ($idx->required_permission !== null) {
                if ($user === null || ($user->role !== 'admin' && method_exists($user, 'can') && ! $user->can($idx->required_permission))) {
                    continue;
                }
            }

            $results[] = [
                'category' => 'Penerbangan & Aviasi',
                'title' => $idx->title,
                'subtitle' => 'Flight No: '.$idx->entity_id.' — '.mb_substr($idx->content, 0, 60),
                'badge' => 'FLIGHT',
                'badge_color' => 'bg-indigo-500/20 text-indigo-300',
                'url' => (string) ($idx->metadata['url'] ?? '/flights/'.$idx->entity_id),
            ];
        }

        return $results;
    }
}
