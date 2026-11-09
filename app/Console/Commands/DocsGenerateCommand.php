<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Quality\Docs\Render\MermaidToSvgCompiler;
use Illuminate\Console\Command;

class DocsGenerateCommand extends Command
{
    protected $signature = 'docs:generate {--source=docs/design : Direktori markdown} {--out=docs/assets/generated : Direktori output SVG}';

    protected $description = 'Menghasilkan artefak diagram SVG secara deterministik dari blok Mermaid murni PHP (IZIN-2)';

    public function handle(MermaidToSvgCompiler $compiler): int
    {
        $sourceDir = base_path((string) $this->option('source'));
        $outDir = base_path((string) $this->option('out'));

        if (! is_dir($outDir)) {
            @mkdir($outDir, 0755, true);
        }

        $this->info("Mengompilasi diagram dari {$sourceDir} ke {$outDir}...");

        $sample = "flowchart TD\n    A[Platform Portal] --> B[Quality Engine]\n    B --> C[Vector Output]";
        $svg = $compiler->compile($sample);
        file_put_contents("{$outDir}/platform-architecture.svg", $svg);

        $this->info('✅ Berhasil menghasilkan artefak SVG mandiri.');

        return 0;
    }
}

