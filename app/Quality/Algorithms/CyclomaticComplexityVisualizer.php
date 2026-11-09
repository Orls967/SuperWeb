<?php

declare(strict_types=1);

namespace App\Quality\Algorithms;

/**
 * Visualizer Graf Kompleksitas Siklomatis ke format DOT Graphviz dan representasi node-link.
 */
final class CyclomaticComplexityVisualizer
{
    /**
     * @param list<array{from: string, to: string, label?: string}> $edges
     */
    public function generateDotGraph(string $graphName, array $edges): string
    {
        $dot = [];
        $dot[] = "digraph \"{$graphName}\" {";
        $dot[] = "    rankdir=TB;";
        $dot[] = "    node [shape=box, style=filled, fillcolor=\"#f1f5f9\", color=\"#0284c7\"];";

        foreach ($edges as $e) {
            $label = isset($e['label']) ? " [label=\"{$e['label']}\"]" : '';
            $dot[] = "    \"{$e['from']}\" -> \"{$e['to']}\"{$label};";
        }

        $dot[] = "}";

        return implode("\n", $dot);
    }
}

