<?php

declare(strict_types=1);

namespace App\Quality\Docs\Render;

/**
 * Renderer Diagram Entitas-Relasi (ERD) murni PHP ke vektor SVG (IZIN-2).
 */
class EntityRelationshipDiagramRenderer
{
    /**
     * @param array<string, list<string>> $entities Map entityName -> list of column definitions
     * @param list<array{from: string, to: string, relation: string}> $relations
     */
    public function renderErd(string $title, array $entities, array $relations): string
    {
        $entityCount = count($entities);
        $cols = 3;
        $rows = (int) ceil($entityCount / $cols);

        $width = 1000;
        $height = max(600, $rows * 220 + 120);

        $canvas = new SvgCanvas(width: $width, height: $height);
        $canvas->drawText(x: (int) ($width / 2), y: 35, text: $title, fontSize: 18, textAnchor: 'middle');

        $entityPositions = [];
        $colIdx = 0;
        $rowIdx = 0;

        foreach ($entities as $name => $attributes) {
            $x = 60 + ($colIdx * 300);
            $y = 80 + ($rowIdx * 200);

            $entityPositions[$name] = ['x' => $x + 125, 'y' => $y + 75];

            // Header box
            $canvas->drawRect(x: $x, y: $y, width: 250, height: 32, rx: 4, fill: '#0284c7', stroke: '#0369a1');
            $canvas->drawText(x: $x + 125, y: $y + 21, text: $name, fontSize: 13, textAnchor: 'middle', fill: '#ffffff');

            // Body box
            $attrHeight = max(50, count($attributes) * 20 + 15);
            $canvas->drawRect(x: $x, y: $y + 32, width: 250, height: $attrHeight, rx: 2, fill: '#f8fafc', stroke: '#cbd5e1');

            $attrY = $y + 50;
            foreach (array_slice($attributes, 0, 7) as $attr) {
                $canvas->drawText(x: $x + 15, y: $attrY, text: $attr, fontSize: 11, fill: '#334155');
                $attrY += 18;
            }

            $colIdx++;
            if ($colIdx >= $cols) {
                $colIdx = 0;
                $rowIdx++;
            }
        }

        // Draw relationship lines
        foreach ($relations as $rel) {
            $p1 = $entityPositions[$rel['from']] ?? null;
            $p2 = $entityPositions[$rel['to']] ?? null;

            if ($p1 && $p2) {
                $canvas->drawArrow(x1: $p1['x'], y1: $p1['y'], x2: $p2['x'], y2: $p2['y'], stroke: '#64748b');
            }
        }

        return $canvas->render();
    }
}

