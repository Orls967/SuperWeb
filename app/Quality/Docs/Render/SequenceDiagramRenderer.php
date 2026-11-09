<?php

declare(strict_types=1);

namespace App\Quality\Docs\Render;

/**
 * Renderer Diagram Sekuensial (Sequence Diagram) murni PHP ke vektor SVG (IZIN-2).
 */
class SequenceDiagramRenderer
{
    public function __construct(
        private readonly MermaidToSvgCompiler $compiler = new MermaidToSvgCompiler
    ) {}

    /**
     * @param  list<string>  $participants
     * @param  list<array{from: string, to: string, message: string, is_dotted?: bool}>  $messages
     */
    public function renderSequence(string $title, array $participants, array $messages): string
    {
        $partCount = count($participants);
        $msgCount = count($messages);

        $width = max(800, $partCount * 180 + 100);
        $height = max(500, $msgCount * 60 + 160);

        $canvas = new SvgCanvas(width: $width, height: $height);

        // Judul
        $canvas->drawText(x: (int) ($width / 2), y: 35, text: $title, fontSize: 18, textAnchor: 'middle');

        // Partisipan
        $positions = [];
        $x = 80;
        foreach ($participants as $idx => $name) {
            $positions[$name] = $x + 60;
            $canvas->drawRect(x: $x, y: 60, width: 120, height: 40, rx: 6, fill: '#e0f2fe', stroke: '#0284c7');
            $canvas->drawText(x: $x + 60, y: 85, text: $name, fontSize: 13, textAnchor: 'middle');

            // Lifeline vertical
            $canvas->drawRect(x: $x + 59, y: 100, width: 2, height: $height - 150, rx: 0, fill: '#cbd5e1', stroke: '#cbd5e1');

            $x += 180;
        }

        // Pesan
        $y = 140;
        foreach ($messages as $msg) {
            $fromX = $positions[$msg['from']] ?? 100;
            $toX = $positions[$msg['to']] ?? 300;

            $canvas->drawArrow(x1: $fromX, y1: $y, x2: $toX, y2: $y, stroke: '#0369a1');
            $midX = (int) (($fromX + $toX) / 2);
            $canvas->drawText(x: $midX, y: $y - 8, text: $msg['message'], fontSize: 11, textAnchor: 'middle', fill: '#475569');

            $y += 55;
        }

        return $canvas->render();
    }
}
