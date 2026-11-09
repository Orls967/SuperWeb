<?php

declare(strict_types=1);

namespace App\Quality\Docs\Render;

/**
 * Renderer Diagram Kelas UML murni PHP ke vektor SVG (IZIN-2).
 */
class ClassDiagramRenderer
{
    /**
     * @param array<string, array{methods: list<string>, properties: list<string>}> $classes
     * @param list<array{from: string, to: string, type: string}> $relationships
     */
    public function renderClasses(string $title, array $classes, array $relationships): string
    {
        $classCount = count($classes);
        $cols = 3;
        $rows = (int) ceil($classCount / $cols);

        $width = 1100;
        $height = max(600, $rows * 240 + 120);

        $canvas = new SvgCanvas(width: $width, height: $height);
        $canvas->drawText(x: (int) ($width / 2), y: 35, text: $title, fontSize: 18, textAnchor: 'middle');

        $classPositions = [];
        $col = 0;
        $row = 0;

        foreach ($classes as $className => $details) {
            $x = 60 + ($col * 330);
            $y = 80 + ($row * 220);

            $classPositions[$className] = ['x' => $x + 140, 'y' => $y + 80];

            // Title
            $canvas->drawRect(x: $x, y: $y, width: 280, height: 30, rx: 4, fill: '#f1f5f9', stroke: '#475569');
            $canvas->drawText(x: $x + 140, y: $y + 20, text: $className, fontSize: 13, textAnchor: 'middle', fill: '#0f172a');

            // Props & methods
            $bodyHeight = max(80, (count($details['properties']) + count($details['methods'])) * 18 + 20);
            $canvas->drawRect(x: $x, y: $y + 30, width: 280, height: $bodyHeight, rx: 2, fill: '#ffffff', stroke: '#94a3b8');

            $lineY = $y + 48;
            foreach (array_slice($details['properties'], 0, 4) as $prop) {
                $canvas->drawText(x: $x + 10, y: $lineY, text: '+ ' . $prop, fontSize: 11, fill: '#0369a1');
                $lineY += 17;
            }

            $lineY += 5;
            foreach (array_slice($details['methods'], 0, 4) as $method) {
                $canvas->drawText(x: $x + 10, y: $lineY, text: '+ ' . $method . '()', fontSize: 11, fill: '#15803d');
                $lineY += 17;
            }

            $col++;
            if ($col >= $cols) {
                $col = 0;
                $row++;
            }
        }

        foreach ($relationships as $rel) {
            $p1 = $classPositions[$rel['from']] ?? null;
            $p2 = $classPositions[$rel['to']] ?? null;

            if ($p1 && $p2) {
                $canvas->drawArrow(x1: $p1['x'], y1: $p1['y'], x2: $p2['x'], y2: $p2['y'], stroke: '#475569');
            }
        }

        return $canvas->render();
    }
}

