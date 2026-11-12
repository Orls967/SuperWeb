<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Services;

use InvalidArgumentException;
use Modules\Intercompany\Domain\Models\IntercompanyEntity;
use Modules\Intercompany\Domain\Models\OwnershipLink;

/**
 * Registri Hirarki Entitas Grup & Penentuan Metode Konsolidasi.
 *
 * Mengelola struktur holding konglomerasi, relasi kepemilikan langsung & tidak langsung,
 * penentuan perimeter konsolidasi (Full, Equity, Cost), deteksi siklus sirkularitas kepemilikan,
 * dan kalkulasi bobot kepentingan non-pengendali (Non-Controlling Interest / NCI) sesuai PSAK 65 / IFRS 10.
 */
class GroupEntityRegistry
{
    /**
     * @var array<string, array{
     *     code: string,
     *     name: string,
     *     country: string,
     *     currency: string,
     *     is_parent: bool,
     *     is_active: bool
     * }>
     */
    protected array $inMemoryEntities = [];

    /**
     * @var array<string, array<string, array{
     *     ownership_pct_bps: int,
     *     consolidation_method: string,
     *     effective_date: string
     * }>>
     */
    protected array $inMemoryLinks = [];

    /**
     * Daftarkan entitas secara in-memory (berguna untuk pengujian unit & simulasi).
     */
    public function registerEntity(
        string $code,
        string $name,
        string $country = 'ID',
        string $currency = 'IDR',
        bool $isParent = false,
        bool $isActive = true
    ): void {
        $code = strtoupper(trim($code));
        $this->inMemoryEntities[$code] = [
            'code' => $code,
            'name' => $name,
            'country' => strtoupper($country),
            'currency' => strtoupper($currency),
            'is_parent' => $isParent,
            'is_active' => $isActive,
        ];
    }

    /**
     * Daftarkan relasi kepemilikan saham langsung.
     */
    public function registerOwnershipLink(
        string $parentCode,
        string $childCode,
        int $ownershipPctBps,
        string $consolidationMethod = 'FULL',
        string $effectiveDate = '2026-01-01'
    ): void {
        $parent = strtoupper(trim($parentCode));
        $child = strtoupper(trim($childCode));

        if ($parent === $child) {
            throw new InvalidArgumentException("Entitas {$parent} tidak dapat memiliki saham pada dirinya sendiri.");
        }

        if ($ownershipPctBps <= 0 || $ownershipPctBps > 10000) {
            throw new InvalidArgumentException("Persentase kepemilikan basis points harus antara 1 dan 10000 (0.01% - 100.00%). Nilai: {$ownershipPctBps}");
        }

        if ($this->hasPath($child, $parent)) {
            throw new InvalidArgumentException("Siklus kepemilikan sirkular terdeteksi melibatkan entitas {$parent} dan {$child}.");
        }

        $this->inMemoryLinks[$parent][$child] = [
            'ownership_pct_bps' => $ownershipPctBps,
            'consolidation_method' => strtoupper($consolidationMethod),
            'effective_date' => $effectiveDate,
        ];
    }

    /**
     * Dapatkan detail entitas berdasarkan kode.
     *
     * @return array{code: string, name: string, country: string, currency: string, is_parent: bool, is_active: bool}|null
     */
    public function getEntity(string $code): ?array
    {
        $code = strtoupper(trim($code));

        if (isset($this->inMemoryEntities[$code])) {
            return $this->inMemoryEntities[$code];
        }

        $db = IntercompanyEntity::where('entity_code', $code)->first();
        if ($db) {
            return [
                'code' => $db->entity_code,
                'name' => $db->legal_name,
                'country' => $db->country_code,
                'currency' => $db->functional_currency,
                'is_parent' => (bool) $db->is_parent,
                'is_active' => (bool) $db->is_active,
            ];
        }

        return null;
    }

    /**
     * Dapatkan semua anak perusahaan langsung dari entitas induk.
     *
     * @return array<string, array{ownership_pct_bps: int, consolidation_method: string, effective_date: string}>
     */
    public function getDirectSubsidiaries(string $parentCode): array
    {
        $parentCode = strtoupper(trim($parentCode));
        $result = $this->inMemoryLinks[$parentCode] ?? [];

        // Gabungkan dengan data database jika ada
        $links = OwnershipLink::where('parent_entity_code', $parentCode)
            ->where('is_active', true)
            ->get();

        foreach ($links as $link) {
            $child = strtoupper($link->child_entity_code);
            if (! isset($result[$child])) {
                $result[$child] = [
                    'ownership_pct_bps' => (int) $link->ownership_pct_bps,
                    'consolidation_method' => (string) $link->consolidation_method,
                    'effective_date' => (string) $link->effective_date,
                ];
            }
        }

        return $result;
    }

    /**
     * Hitung kepemilikan efektif (effective ownership percentage) dari induk ke entitas target.
     * Menggunakan traversal jalur rekursif dan perkalian rasio rantai kepemilikan.
     */
    public function calculateEffectiveOwnership(string $rootParentCode, string $targetEntityCode): int
    {
        $root = strtoupper(trim($rootParentCode));
        $target = strtoupper(trim($targetEntityCode));

        if ($root === $target) {
            return 10000;
        }

        $visited = [];
        $totalEffectiveBps = $this->traverseEffectiveOwnership($root, $target, 10000, $visited);

        return min(10000, $totalEffectiveBps);
    }

    /**
     * Rekursi penelusuran bobot kepemilikan efektif.
     *
     * @param  array<string, bool>  $visited
     */
    protected function traverseEffectiveOwnership(
        string $current,
        string $target,
        int $accumulatedBps,
        array $visited
    ): int {
        if (isset($visited[$current])) {
            throw new InvalidArgumentException("Siklus kepemilikan sirkular terdeteksi melibatkan entitas {$current}.");
        }
        $visited[$current] = true;

        $subsidiaries = $this->getDirectSubsidiaries($current);
        $sumBps = 0;

        foreach ($subsidiaries as $childCode => $info) {
            $pathBps = intdiv($accumulatedBps * $info['ownership_pct_bps'], 10000);

            if ($childCode === $target) {
                $sumBps += $pathBps;
            } else {
                $sumBps += $this->traverseEffectiveOwnership($childCode, $target, $pathBps, $visited);
            }
        }

        return $sumBps;
    }

    /**
     * Tentukan klasifikasi metode konsolidasi PSAK 65 / IFRS 10.
     * - FULL: >= 5000 bps (> 50%)
     * - EQUITY: 2000 - 4999 bps (20% - 50%)
     * - COST: < 2000 bps (< 20%)
     */
    public function determineConsolidationMethod(int $effectiveOwnershipBps): string
    {
        if ($effectiveOwnershipBps >= 5000) {
            return 'FULL';
        }

        if ($effectiveOwnershipBps >= 2000) {
            return 'EQUITY';
        }

        return 'COST';
    }

    /**
     * Hitung porsi kepentingan non-pengendali (Non-Controlling Interest / NCI).
     * Sesuai PSAK 65: NCI = 10000 bps - Effective Parent Ownership bps.
     */
    public function calculateNciShare(int $effectiveOwnershipBps): int
    {
        return max(0, 10000 - $effectiveOwnershipBps);
    }

    /**
     * Bangun pohon konsolidasi menyeluruh dari entitas holding utama.
     *
     * @return array{
     *     root_entity: string,
     *     nodes: array<string, array{
     *         code: string,
     *         name: string,
     *         country: string,
     *         currency: string,
     *         effective_ownership_bps: int,
     *         nci_bps: int,
     *         method: string,
     *         depth: int
     *     }>,
     *     perimeter: array{
     *         full: array<string>,
     *         equity: array<string>,
     *         cost: array<string>
     *     }
     * }
     */
    public function buildConsolidationTree(string $rootParentCode): array
    {
        $root = strtoupper(trim($rootParentCode));
        $rootInfo = $this->getEntity($root);
        $rootName = $rootInfo['name'] ?? "Holding {$root}";

        $nodes = [
            $root => [
                'code' => $root,
                'name' => $rootName,
                'country' => $rootInfo['country'] ?? 'ID',
                'currency' => $rootInfo['currency'] ?? 'IDR',
                'effective_ownership_bps' => 10000,
                'nci_bps' => 0,
                'method' => 'HOLDING',
                'depth' => 0,
            ],
        ];

        $perimeter = [
            'full' => [$root],
            'equity' => [],
            'cost' => [],
        ];

        $queue = [[$root, 0]];
        $visited = [];

        while (! empty($queue)) {
            [$current, $depth] = array_shift($queue);
            $visited[$current] = true;

            $children = $this->getDirectSubsidiaries($current);
            foreach ($children as $childCode => $link) {
                if (! isset($nodes[$childCode])) {
                    $childEntity = $this->getEntity($childCode);
                    $effectiveBps = $this->calculateEffectiveOwnership($root, $childCode);
                    $method = $this->determineConsolidationMethod($effectiveBps);
                    $nciBps = $this->calculateNciShare($effectiveBps);

                    $nodes[$childCode] = [
                        'code' => $childCode,
                        'name' => $childEntity['name'] ?? "Subsidiary {$childCode}",
                        'country' => $childEntity['country'] ?? 'ID',
                        'currency' => $childEntity['currency'] ?? 'IDR',
                        'effective_ownership_bps' => $effectiveBps,
                        'nci_bps' => $nciBps,
                        'method' => $method,
                        'depth' => $depth + 1,
                    ];

                    if ($method === 'FULL') {
                        $perimeter['full'][] = $childCode;
                    } elseif ($method === 'EQUITY') {
                        $perimeter['equity'][] = $childCode;
                    } else {
                        $perimeter['cost'][] = $childCode;
                    }

                    if (! isset($visited[$childCode])) {
                        $queue[] = [$childCode, $depth + 1];
                    }
                }
            }
        }

        return [
            'root_entity' => $root,
            'nodes' => $nodes,
            'perimeter' => $perimeter,
        ];
    }

    /**
     * Dapatkan daftar entitas yang masuk dalam perimeter konsolidasi penuh (Line-by-line).
     *
     * @return array<string>
     */
    public function getFullConsolidationPerimeter(string $rootParentCode): array
    {
        $tree = $this->buildConsolidationTree($rootParentCode);

        return $tree['perimeter']['full'];
    }

    /**
     * Periksa apakah dua entitas berada di bawah holding yang sama (intra-group affiliation).
     */
    public function isAffiliated(string $rootParentCode, string $entityA, string $entityB): bool
    {
        $tree = $this->buildConsolidationTree($rootParentCode);
        $nodes = array_keys($tree['nodes']);

        return in_array(strtoupper(trim($entityA)), $nodes, true)
            && in_array(strtoupper(trim($entityB)), $nodes, true);
    }

    /**
     * Periksa apakah terdapat jalur kepemilikan berarah dari entitas awal ke entitas tujuan.
     */
    public function hasPath(string $from, string $to): bool
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        if ($from === $to) {
            return true;
        }

        $visited = [];
        $queue = [$from];

        while (! empty($queue)) {
            $curr = array_shift($queue);
            if ($curr === $to) {
                return true;
            }
            if (isset($visited[$curr])) {
                continue;
            }
            $visited[$curr] = true;

            $children = $this->getDirectSubsidiaries($curr);
            foreach (array_keys($children) as $child) {
                if ($child === $to) {
                    return true;
                }
                if (! isset($visited[$child])) {
                    $queue[] = $child;
                }
            }
        }

        return false;
    }
}
