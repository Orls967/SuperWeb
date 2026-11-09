<?php

declare(strict_types=1);

namespace App\Quality\Docs\Render;

/**
 * Native Mermaid to SVG Compiler (W8-D09, IZIN-2).
 * Mengompilasi subset flowchart dan stateDiagram Mermaid menjadi D1 exact vector SVG.
 */
class MermaidToSvgCompiler
{
    /**
     * @return string Valid SVG XML
     */
    public function compile(string $mermaidSource): string
    {
        $lines = explode("\n", trim($mermaidSource));
        $nodes = [];
        $edges = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, 'flowchart') || str_starts_with($trimmed, 'graph')) {
                continue;
            }

            // Parse edges: A[Label] -->|Text| B[Label] or A --> B
            if (preg_match('/([a-zA-Z0-9_]+)(?:\[(.*?)\]|\{(.*?)\})?\s*-->\s*(?:\|(.*?)\|)?\s*([a-zA-Z0-9_]+)(?:\[(.*?)\]|\{(.*?)\})?/', $trimmed, $matches)) {
                $fromId = $matches[1];
                $fromLabel = ! empty($matches[2]) ? $matches[2] : (! empty($matches[3]) ? $matches[3] : $fromId);
                $edgeText = $matches[4] ?? '';
                $toId = $matches[5];
                $toLabel = ! empty($matches[6]) ? $matches[6] : (! empty($matches[7]) ? $matches[7] : $toId);

                if (! isset($nodes[$fromId]) || $nodes[$fromId] === $fromId) {
                    $nodes[$fromId] = $fromLabel;
                }
                if (! isset($nodes[$toId]) || $nodes[$toId] === $toId) {
                    $nodes[$toId] = $toLabel;
                }

                $edges[] = [
                    'from' => $fromId,
                    'to' => $toId,
                    'label' => $edgeText,
                ];
            }
        }

        // Layout engine sederhana deterministik
        $nodeCount = count($nodes);
        $canvasHeight = max(400, $nodeCount * 90 + 100);
        $canvasWidth = 700;
        $canvas = new SvgCanvas(width: $canvasWidth, height: $canvasHeight);

        $nodePositions = [];
        $y = 60;
        foreach ($nodes as $id => $label) {
            $nodePositions[$id] = ['x' => 200, 'y' => $y];
            $canvas->drawRect(x: 100, y: $y - 25, width: 220, height: 50, rx: 6, fill: '#f1f5f9', stroke: '#0284c7');
            $canvas->drawText(x: 210, y: $y + 5, text: $label, fontSize: 13, textAnchor: 'middle');
            $y += 85;
        }

        foreach ($edges as $edge) {
            $from = $nodePositions[$edge['from']] ?? null;
            $to = $nodePositions[$edge['to']] ?? null;

            if ($from && $to) {
                $canvas->drawArrow(x1: 210, y1: $from['y'] + 25, x2: 210, y2: $to['y'] - 25);
                if ($edge['label'] !== '') {
                    $canvas->drawText(x: 225, y: (int) (($from['y'] + $to['y']) / 2), text: $edge['label'], fontSize: 11, fill: '#64748b');
                }
            }
        }

        return $canvas->render();
    }
}
