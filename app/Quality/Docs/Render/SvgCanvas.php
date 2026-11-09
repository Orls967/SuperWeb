<?php

declare(strict_types=1);

namespace App\Quality\Docs\Render;

/**
 * Pure PHP SVG Canvas (W8-D09, IZIN-2).
 * Merender elemen visual dasar ke format SVG XML valid tanpa ekstensi grafis eksternal.
 */
class SvgCanvas
{
    /** @var list<string> */
    private array $elements = [];

    public function __construct(
        public readonly int $width = 800,
        public readonly int $height = 600,
        public readonly string $background = '#ffffff'
    ) {}

    public function drawRect(
        int $x,
        int $y,
        int $width,
        int $height,
        int $rx = 4,
        string $fill = '#f8fafc',
        string $stroke = '#334155',
        int $strokeWidth = 2
    ): self {
        $this->elements[] = sprintf(
            '<rect x="%d" y="%d" width="%d" height="%d" rx="%d" fill="%s" stroke="%s" stroke-width="%d" />',
            $x,
            $y,
            $width,
            $height,
            $rx,
            htmlspecialchars($fill),
            htmlspecialchars($stroke),
            $strokeWidth
        );

        return $this;
    }

    public function drawText(
        int $x,
        int $y,
        string $text,
        int $fontSize = 14,
        string $textAnchor = 'start',
        string $fill = '#0f172a',
        string $fontFamily = 'system-ui, -apple-system, sans-serif'
    ): self {
        $this->elements[] = sprintf(
            '<text x="%d" y="%d" font-size="%d" text-anchor="%s" fill="%s" font-family="%s">%s</text>',
            $x,
            $y,
            $fontSize,
            htmlspecialchars($textAnchor),
            htmlspecialchars($fill),
            htmlspecialchars($fontFamily),
            htmlspecialchars($text)
        );

        return $this;
    }

    public function drawArrow(
        int $x1,
        int $y1,
        int $x2,
        int $y2,
        string $stroke = '#0284c7',
        int $strokeWidth = 2
    ): self {
        $this->elements[] = sprintf(
            '<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="%s" stroke-width="%d" marker-end="url(#arrowhead)" />',
            $x1,
            $y1,
            $x2,
            $y2,
            htmlspecialchars($stroke),
            $strokeWidth
        );

        return $this;
    }

    public function drawCircle(
        int $cx,
        int $cy,
        int $r,
        string $fill = '#e2e8f0',
        string $stroke = '#64748b'
    ): self {
        $this->elements[] = sprintf(
            '<circle cx="%d" cy="%d" r="%d" fill="%s" stroke="%s" stroke-width="2" />',
            $cx,
            $cy,
            $r,
            htmlspecialchars($fill),
            htmlspecialchars($stroke)
        );

        return $this;
    }

    public function render(): string
    {
        $svg = [];
        $svg[] = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d">',
            $this->width,
            $this->height,
            $this->width,
            $this->height
        );

        // Marker defs
        $svg[] = '<defs>';
        $svg[] = '<marker id="arrowhead" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">';
        $svg[] = '<polygon points="0 0, 10 3.5, 0 7" fill="#0284c7" />';
        $svg[] = '</marker>';
        $svg[] = '</defs>';

        // Background
        $svg[] = sprintf('<rect width="%d" height="%d" fill="%s" />', $this->width, $this->height, htmlspecialchars($this->background));

        // Elements
        foreach ($this->elements as $element) {
            $svg[] = $element;
        }

        $svg[] = '</svg>';

        return implode("\n", $svg);
    }
}
