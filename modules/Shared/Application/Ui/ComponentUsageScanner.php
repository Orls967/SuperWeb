<?php

declare(strict_types=1);

namespace Modules\Shared\Application\Ui;

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

/**
 * ComponentUsageScanner
 *
 * Memindai seluruh template Blade aplikasi untuk memetakan pemanggilan
 * komponen kanonikal x-ds.*.
 */
class ComponentUsageScanner
{
    /**
     * Memindai direktori yang diberikan dan mengembalikan peta pemakaian komponen.
     *
     * @param list<string> $targetDirs
     * @return array{total_views: int, components_found: int, occurrences: int, usages: array<string, array<string, int>>}
     */
    public function scan(array $targetDirs = []): array
    {
        if (empty($targetDirs)) {
            $targetDirs = [
                base_path('resources/views'),
                base_path('modules'),
            ];
        }

        $finder = new Finder();
        $finder->files()->name('*.blade.php');

        foreach ($targetDirs as $dir) {
            if (File::isDirectory($dir)) {
                $finder->in($dir);
            }
        }

        $usageMap = []; // [component_name => [view_path => count]]
        $totalViews = 0;
        $totalOccurrences = 0;

        foreach ($finder as $file) {
            $totalViews++;
            $content = $file->getContents();
            $relPath = str_replace(base_path().'/', '', $file->getRealPath());

            // Regex untuk menangkap komponen x-ds.<nama>
            if (preg_match_all('/<x-ds\.([a-zA-Z0-9_-]+)/', $content, $matches)) {
                $counts = array_count_values($matches[1]);
                foreach ($counts as $compName => $count) {
                    $normName = strtolower($compName);
                    $usageMap[$normName][$relPath] = ($usageMap[$normName][$relPath] ?? 0) + $count;
                    $totalOccurrences += $count;
                }
            }
        }

        return [
            'total_views' => $totalViews,
            'components_found' => count($usageMap),
            'occurrences' => $totalOccurrences,
            'usages' => $usageMap,
        ];
    }
}
