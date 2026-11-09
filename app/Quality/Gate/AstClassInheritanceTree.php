<?php

declare(strict_types=1);

namespace App\Quality\Gate;

/**
 * Pembangun pohon hirarki warisan kelas (Class Inheritance Hierarchy Tree).
 */
class AstClassInheritanceTree
{
    /** @var array<string, list<string>> Parent -> Children */
    private array $tree = [];

    /** @var array<string, ?string> Child -> Parent */
    private array $parents = [];

    public function registerInheritance(string $childClass, ?string $parentClass): void
    {
        $this->parents[$childClass] = $parentClass;

        if ($parentClass !== null) {
            $this->tree[$parentClass][] = $childClass;
        }
    }

    /**
     * @return list<string> Rantai leluhur dari anak hingga root
     */
    public function getAncestryChain(string $class): array
    {
        $chain = [];
        $curr = $class;

        while (isset($this->parents[$curr]) && $this->parents[$curr] !== null) {
            $parent = $this->parents[$curr];
            $chain[] = $parent;
            $curr = $parent;
        }

        return $chain;
    }

    public function getDepth(string $class): int
    {
        return count($this->getAncestryChain($class));
    }
}

