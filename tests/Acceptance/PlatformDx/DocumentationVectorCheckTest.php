<?php

declare(strict_types=1);

namespace Tests\Acceptance\PlatformDx;

use App\Quality\Docs\Render\MermaidToSvgCompiler;
use App\Quality\Docs\Render\SvgCanvas;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DocumentationVectorCheckTest extends TestCase
{
    public function test_svg_canvas_renders_valid_scalable_xml(): void
    {
        $canvas = new SvgCanvas(width: 800, height: 600);
        $canvas->drawRect(x: 10, y: 10, width: 200, height: 100, rx: 8, fill: '#f8fafc', stroke: '#334155');
        $canvas->drawText(x: 110, y: 60, text: 'Platform Engine', fontSize: 14, textAnchor: 'middle');
        $canvas->drawArrow(x1: 210, y1: 60, x2: 400, y2: 60, stroke: '#0284c7');

        $svg = $canvas->render();

        expect($svg)->toContain('<svg xmlns="http://www.w3.org/2000/svg"');
        expect($svg)->toContain('Platform Engine');
        expect($svg)->toContain('</svg>');

        // Verifikasi validitas XML
        $xml = simplexml_load_string($svg);
        expect($xml)->not->toBeFalse();
    }

    public function test_mermaid_compiler_converts_flowchart_to_vector_svg(): void
    {
        $mermaid = <<<'MERMAID'
        flowchart TD
            A[Start] --> B[Process Engine]
            B --> C{Decision}
            C -->|Yes| D[Done]
            C -->|No| B
        MERMAID;

        $compiler = new MermaidToSvgCompiler;
        $svg = $compiler->compile($mermaid);

        expect($svg)->toContain('<svg');
        expect($svg)->toContain('Process Engine');
        expect($svg)->toContain('Decision');
        expect($svg)->toContain('Done');
    }

    public function test_docs_check_command_verifies_diagram_freshness(): void
    {
        $exitCode = Artisan::call('docs:check', [
            '--path' => 'docs/design',
        ]);

        expect($exitCode)->toBe(0);
    }
}
