<?php

declare(strict_types=1);

namespace Modules\Shared\Application;

class MenuRegistry
{
    /**
     * @var array<MenuItem>
     */
    protected array $items = [];

    public function register(MenuItem $item): self
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * @param array<string> $roles
     */
    public function addItem(
        string $label,
        string $route,
        string $icon = '',
        array $roles = [],
        int $order = 50,
        ?string $group = null,
        ?string $activePattern = null,
        mixed $badge = null,
    ): self {
        $this->items[] = new MenuItem(
            label: $label,
            route: $route,
            icon: $icon,
            roles: $roles,
            order: $order,
            group: $group,
            activePattern: $activePattern,
            badge: $badge,
        );

        return $this;
    }

    /**
     * Get sorted items visible for a given user.
     *
     * @return array<MenuItem>
     */
    public function getItemsForUser(?object $user): array
    {
        $visible = array_filter(
            $this->items,
            fn (MenuItem $item) => $item->isVisibleFor($user)
        );

        usort($visible, fn (MenuItem $a, MenuItem $b) => $a->order <=> $b->order);

        return array_values($visible);
    }

    /**
     * Get grouped items visible for a given user.
     *
     * @return array<string, array<MenuItem>>
     */
    public function getGroupedItemsForUser(?object $user): array
    {
        $items = $this->getItemsForUser($user);
        $grouped = [];

        foreach ($items as $item) {
            $group = $item->group ?? 'General';
            $grouped[$group][] = $item;
        }

        return $grouped;
    }
}
