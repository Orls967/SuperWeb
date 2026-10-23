<?php

declare(strict_types=1);

namespace Modules\Shared\Contracts;

use App\Models\User;

interface SearchProviderInterface
{
    /**
     * @return list<array{category: string, title: string, subtitle: string, badge: string, badge_color: string, url: string}>
     */
    public function search(string $keyword, ?User $user = null): array;
}
