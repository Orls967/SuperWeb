<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Search\Providers;

use App\Models\User;
use Modules\Core\Domain\Models\CoreSearchIndex;
use Modules\Shared\Contracts\SearchProviderInterface;

/**
 * Penyedia pencarian menu kuliner restoran, pesanan meja hidang, dan reservasi banquet.
 */
class RestoDiningSearchProvider implements SearchProviderInterface
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
            ->where('module', 'Resto')
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
                'category' => 'Restoran & Kuliner',
                'title' => $idx->title,
                'subtitle' => 'Menu/Order: '.$idx->entity_id.' — '.mb_substr($idx->content, 0, 60),
                'badge' => 'RESTO',
                'badge_color' => 'bg-amber-500/20 text-amber-300',
                'url' => (string) ($idx->metadata['url'] ?? '/resto/orders/'.$idx->entity_id),
            ];
        }

        return $results;
    }
}
