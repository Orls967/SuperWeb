<?php

declare(strict_types=1);

namespace Modules\Shared\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Actions\DesignSystem\ThemeTokenAction;

final class DsTokensBuildCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ds:tokens:build
        {--dry-run : Cetak CSS token ke output konsol tanpa menulis file}
        {--out= : Path relatif atau absolut file CSS keluaran}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kompilasi token tema Design System (light, dark, high-contrast) ke file CSS terstandar';

    public function handle(ThemeTokenAction $tokenAction): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $customOut = $this->option('out');
        $outputPath = $customOut ? (str_starts_with((string) $customOut, '/') ? (string) $customOut : base_path((string) $customOut)) : null;

        $result = $tokenAction->buildCssFile($outputPath);

        if ($dryRun) {
            $this->info(sprintf('Dry-run selesai: CSS dengan %d baris terverifikasi untuk tema: %s.', $result['lines_count'], implode(', ', $result['themes'])));

            return 0;
        }

        $target = $result['file_path'];
        $bytes = file_exists($target) ? filesize($target) : 0;
        $this->info(sprintf('Token tema Design System berhasil dikompilasi ke: %s (%d bytes, %d baris).', $target, $bytes, $result['lines_count']));

        return 0;
    }
}
