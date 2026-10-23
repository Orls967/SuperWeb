<?php

declare(strict_types=1);

namespace Modules\Shared\Application;

use App\Models\User;
use Modules\Shared\Contracts\SearchProviderInterface;

class SearchRegistry
{
    /** @var list<SearchProviderInterface> */
    protected array $providers = [];

    public function registerProvider(SearchProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * @return list<array{category: string, title: string, subtitle: string, badge: string, badge_color: string, url: string}>
     */
    public function search(string $keyword, ?User $user = null): array
    {
        $results = [];

        foreach ($this->providers as $provider) {
            try {
                $providerResults = $provider->search($keyword, $user);
                foreach ($providerResults as $result) {
                    $results[] = $result;
                }
            } catch (\Throwable) {
                // Ignore failure in individual provider
            }
        }

        return $results;
    }
}
