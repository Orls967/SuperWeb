<?php

declare(strict_types=1);

namespace Modules\Shared\Application\Ui;

use InvalidArgumentException;

/**
 * Resolver hierarki menu modular berbasis RBAC dan status aktif rute.
 */
class MenuTreeResolver
{
    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $registeredNodes = [];

    /**
     * @param array<string, array<string, mixed>> $initialNodes
     */
    public function __construct(array $initialNodes = [])
    {
        foreach ($initialNodes as $node) {
            $this->addNode($node);
        }
    }

    /**
     * Daftarkan simpul menu baru.
     *
     * @param array<string, mixed> $node
     */
    public function addNode(array $node): self
    {
        if (empty($node['id']) || !is_string($node['id'])) {
            throw new InvalidArgumentException('Simpul menu wajib memiliki ID berupa string non-kosong.');
        }

        if (empty($node['title']) || !is_string($node['title'])) {
            throw new InvalidArgumentException("Simpul menu [{$node['id']}] wajib memiliki title.");
        }

        $id = $node['id'];
        $this->registeredNodes[$id] = [
            'id' => $id,
            'title' => (string) $node['title'],
            'url' => (string) ($node['url'] ?? '#'),
            'route' => isset($node['route']) ? (string) $node['route'] : null,
            'icon' => isset($node['icon']) ? (string) $node['icon'] : null,
            'parent_id' => isset($node['parent_id']) && $node['parent_id'] !== '' ? (string) $node['parent_id'] : null,
            'order' => (int) ($node['order'] ?? 100),
            'roles' => is_array($node['roles'] ?? null) ? $node['roles'] : [],
            'permissions' => is_array($node['permissions'] ?? null) ? $node['permissions'] : [],
            'badge' => isset($node['badge']) ? (string) $node['badge'] : null,
            'badge_variant' => (string) ($node['badge_variant'] ?? 'neutral'),
            'module' => isset($node['module']) ? (string) $node['module'] : 'core',
            'is_visible' => (bool) ($node['is_visible'] ?? true),
            'attributes' => is_array($node['attributes'] ?? null) ? $node['attributes'] : [],
        ];

        return $this;
    }

    /**
     * Resolusi pohon menu terurut dan terfilter berdasarkan hak akses pengguna.
     *
     * @param array<string> $userRoles
     * @param array<string> $userPermissions
     * @param string|null $currentRoute
     * @param string|null $currentUrl
     * @return array<int, array<string, mixed>>
     */
    public function resolveTree(
        array $userRoles = [],
        array $userPermissions = [],
        ?string $currentRoute = null,
        ?string $currentUrl = null
    ): array {
        $allowedNodes = [];

        foreach ($this->registeredNodes as $id => $node) {
            if (!$node['is_visible']) {
                continue;
            }

            if (!$this->isAuthorized($node, $userRoles, $userPermissions)) {
                continue;
            }

            $isActive = $this->determineActiveState($node, $currentRoute, $currentUrl);

            $allowedNodes[$id] = array_merge($node, [
                'is_active' => $isActive,
                'children' => [],
            ]);
        }

        return $this->buildHierarchicalTree($allowedNodes);
    }

    /**
     * Periksa apakah pengguna memiliki hak akses ke simpul menu.
     *
     * @param array<string, mixed> $node
     * @param array<string> $userRoles
     * @param array<string> $userPermissions
     */
    protected function isAuthorized(array $node, array $userRoles, array $userPermissions): bool
    {
        $nodeRoles = $node['roles'];
        $nodePermissions = $node['permissions'];

        if (in_array('*', $userRoles, true) || in_array('superadmin', $userRoles, true) || in_array('admin', $userRoles, true)) {
            return true;
        }

        if (empty($nodeRoles) && empty($nodePermissions)) {
            return true;
        }

        if (!empty($nodeRoles)) {
            $hasRole = false;
            foreach ($nodeRoles as $role) {
                if (in_array($role, $userRoles, true)) {
                    $hasRole = true;
                    break;
                }
            }
            if (!$hasRole) {
                return false;
            }
        }

        if (!empty($nodePermissions)) {
            $hasPermission = false;
            foreach ($nodePermissions as $perm) {
                if (in_array($perm, $userPermissions, true)) {
                    $hasPermission = true;
                    break;
                }
            }
            if (!$hasPermission) {
                return false;
            }
        }

        return true;
    }

    /**
     * Tentukan apakah simpul sedang aktif berdasarkan rute atau URL saat ini.
     *
     * @param array<string, mixed> $node
     */
    protected function determineActiveState(array $node, ?string $currentRoute, ?string $currentUrl): bool
    {
        if ($currentRoute !== null && !empty($node['route'])) {
            if ($node['route'] === $currentRoute) {
                return true;
            }
            // Dukungan wildcards rute (e.g. design-system.*)
            if (str_ends_with($node['route'], '.*')) {
                $prefix = substr($node['route'], 0, -2);
                if (str_starts_with($currentRoute, $prefix)) {
                    return true;
                }
            }
        }

        if ($currentUrl !== null && !empty($node['url']) && $node['url'] !== '#') {
            $cleanNodeUrl = rtrim($node['url'], '/');
            $cleanCurrentUrl = rtrim($currentUrl, '/');
            if ($cleanNodeUrl === $cleanCurrentUrl) {
                return true;
            }
        }

        return false;
    }

    /**
     * Susun daftar flat simpul menjadi struktur pohon bersarang bertingkat.
     *
     * @param array<string, array<string, mixed>> $flatNodes
     * @return array<int, array<string, mixed>>
     */
    protected function buildHierarchicalTree(array $flatNodes): array
    {
        $tree = [];
        $childrenMap = [];

        foreach ($flatNodes as $id => $node) {
            $parentId = $node['parent_id'];
            if ($parentId !== null && isset($flatNodes[$parentId])) {
                $childrenMap[$parentId][] = $id;
            }
        }

        // Susun anak secara rekursif
        $attachChildren = function ($parentId, &$nodes) use (&$attachChildren, $childrenMap) {
            if (!isset($childrenMap[$parentId])) {
                return [];
            }

            $children = [];
            foreach ($childrenMap[$parentId] as $childId) {
                if (isset($nodes[$childId])) {
                    $child = $nodes[$childId];
                    $child['children'] = $attachChildren($childId, $nodes);

                    // Jika salah satu anak aktif, induk juga aktif
                    foreach ($child['children'] as $nested) {
                        if (!empty($nested['is_active'])) {
                            $child['is_active'] = true;
                            break;
                        }
                    }

                    $children[] = $child;
                }
            }

            usort($children, fn ($a, $b) => $a['order'] <=> $b['order']);
            return $children;
        };

        foreach ($flatNodes as $id => $node) {
            if ($node['parent_id'] === null || !isset($flatNodes[$node['parent_id']])) {
                $node['children'] = $attachChildren($id, $flatNodes);

                // Induk aktif jika ada anak yang aktif
                if (!$node['is_active']) {
                    foreach ($node['children'] as $child) {
                        if (!empty($child['is_active'])) {
                            $node['is_active'] = true;
                            break;
                        }
                    }
                }

                $tree[] = $node;
            }
        }

        usort($tree, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $tree;
    }

    /**
     * Temukan jalur breadcrumb menuju simpul target.
     *
     * @return array<int, array{id: string, title: string, url: string}>
     */
    public function getBreadcrumbsTo(string $targetId): array
    {
        $breadcrumbs = [];
        $currentId = $targetId;

        while ($currentId !== null && isset($this->registeredNodes[$currentId])) {
            $node = $this->registeredNodes[$currentId];
            array_unshift($breadcrumbs, [
                'id' => $node['id'],
                'title' => $node['title'],
                'url' => $node['url'],
            ]);

            $currentId = $node['parent_id'];
        }

        return $breadcrumbs;
    }

    /**
     * Hitung total simpul menu terdaftar.
     */
    public function count(): int
    {
        return count($this->registeredNodes);
    }
}
