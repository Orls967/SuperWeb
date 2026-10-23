<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Application\Actions;

use InvalidArgumentException;
use Modules\Manufacturing\Domain\Models\Bom;
use Modules\Shared\Application\BaseAction;

final class ExplodeMultiLevelBomAction extends BaseAction
{
    /**
     * Recursively explodes a BOM into a flat list of component requirements with scrap factors.
     *
     * @return array{
     *     output_material_id: int,
     *     requested_qty: float,
     *     components: list<array{
     *         material_id: int,
     *         qty: float,
     *         scrap_percent: float,
     *         level: int,
     *         is_leaf: bool
     *     }>
     * }
     */
    public function execute(Bom $bom, float $quantity = 1.0, int $maxDepth = 10): array
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Kuantitas perakitan BOM harus lebih dari 0.');
        }

        $components = [];
        $this->explodeRecursive($bom, $quantity, 1, $maxDepth, $components);

        $this->audit('mfg.bom_exploded', $bom, null, [
            'output_material_id' => $bom->output_material_id,
            'quantity' => $quantity,
            'component_count' => count($components),
        ]);

        return [
            'output_material_id' => (string) $bom->output_material_id,
            'requested_qty' => $quantity,
            'components' => $components,
        ];
    }

    /**
     * @param  list<array{material_id: string, qty: float, scrap_percent: float, level: int, is_leaf: bool}>  $accumulator
     */
    private function explodeRecursive(Bom $bom, float $parentQty, int $currentLevel, int $maxDepth, array &$accumulator): void
    {
        if ($currentLevel > $maxDepth) {
            return;
        }

        $bom->loadMissing('lines.inputMaterial');

        foreach ($bom->lines as $line) {
            $required = $line->requiredQty($parentQty);
            $subBom = Bom::query()
                ->where('output_material_id', $line->input_material_id)
                ->where('is_active', true)
                ->first();

            $isLeaf = $subBom === null || $currentLevel === $maxDepth;

            $accumulator[] = [
                'material_id' => (string) $line->input_material_id,
                'qty' => round($required, 6),
                'scrap_percent' => (float) $line->scrap_percent,
                'level' => $currentLevel,
                'is_leaf' => $isLeaf,
            ];

            if ($subBom !== null && $currentLevel < $maxDepth) {
                $this->explodeRecursive($subBom, $required, $currentLevel + 1, $maxDepth, $accumulator);
            }
        }
    }
}
