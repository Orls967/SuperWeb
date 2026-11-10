<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Simulation\Verifiers;

use Modules\Core\Domain\Algorithms\Simulation\ValueObjects\DiscreteEvent;

/**
 * Verifier matematis independen untuk properti invariant binary min-heap.
 * Memastikan bahwa elemen induk selalu memiliki prioritas (atau kunci urutan) <= anak.
 */
final class HeapInvarianceVerifier
{
    /**
     * @param  array<int, DiscreteEvent>  $heapArray
     * @return array{valid: bool, violations: array<int, string>}
     */
    public function verifyHeapProperty(array $heapArray): array
    {
        $violations = [];
        $count = count($heapArray);

        for ($i = 0; $i < $count; $i++) {
            $parent = $heapArray[$i];

            $leftChildIdx = 2 * $i + 1;
            $rightChildIdx = 2 * $i + 2;

            if ($leftChildIdx < $count) {
                $leftChild = $heapArray[$leftChildIdx];
                if ($this->compare($parent, $leftChild) > 0) {
                    $violations[] = "Pelanggaran Heap Invariant: Induk [idx={$i}, sec={$parent->virtualSecond}, prio={$parent->priority}] > Anak Kiri [idx={$leftChildIdx}, sec={$leftChild->virtualSecond}, prio={$leftChild->priority}]";
                }
            }

            if ($rightChildIdx < $count) {
                $rightChild = $heapArray[$rightChildIdx];
                if ($this->compare($parent, $rightChild) > 0) {
                    $violations[] = "Pelanggaran Heap Invariant: Induk [idx={$i}, sec={$parent->virtualSecond}, prio={$parent->priority}] > Anak Kanan [idx={$rightChildIdx}, sec={$rightChild->virtualSecond}, prio={$rightChild->priority}]";
                }
            }
        }

        return [
            'valid' => count($violations) === 0,
            'violations' => $violations,
        ];
    }

    private function compare(DiscreteEvent $a, DiscreteEvent $b): int
    {
        if ($a->virtualSecond !== $b->virtualSecond) {
            return $a->virtualSecond <=> $b->virtualSecond;
        }
        if ($a->priority !== $b->priority) {
            return $a->priority <=> $b->priority;
        }

        return $a->sequence <=> $b->sequence;
    }
}
