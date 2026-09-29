<?php

declare(strict_types=1);

namespace Modules\Shared\Application;

final class MenuItem
{
    /**
     * @param array<string> $roles
     */
    public function __construct(
        public string $label,
        public string $route,
        public string $icon = '',
        public array $roles = [],
        public int $order = 50,
        public ?string $group = null,
        public ?string $activePattern = null,
        public mixed $badge = null,
    ) {}

    public function isVisibleFor(?object $user): bool
    {
        if (empty($this->roles)) {
            return true;
        }

        if (! $user) {
            return false;
        }

        $userRole = $user->role ?? null;
        $roleValue = is_object($userRole) ? ($userRole->value ?? (string) $userRole) : (string) $userRole;

        return in_array($roleValue, $this->roles, true);
    }

    public function isActive(): bool
    {
        $pattern = $this->activePattern ?? ($this->route . '*');

        return request()->routeIs($pattern);
    }
}
