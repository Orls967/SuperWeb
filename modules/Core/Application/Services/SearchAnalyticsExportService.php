<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Modules\Core\Domain\Models\CoreSearchQueryLog;

/**
 * Service ekspor data analitik pencarian dan visualisasi SVG murni PHP (tanpa pustaka JS luar).
 */
class SearchAnalyticsExportService
{
    /**
     * Menghasilkan representasi CSV data kueri telemetri pencarian.
     */
    public function exportCsv(int $days = 30): string
    {
        $since = now()->subDays($days);
        $logs = CoreSearchQueryLog::where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(1000)
            ->get();

        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['ID', 'User Role', 'Redacted Query', 'Results Count', 'Latency (ms)', 'Timestamp']);

        foreach ($logs as $log) {
            fputcsv($output, [
                $log->id,
                $log->user_role,
                $log->redacted_query,
                $log->results_count,
                $log->execution_time_ms,
                $log->created_at->toIso8601String(),
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return (string) $csv;
    }

    /**
     * Menghasilkan grafik batang SVG murni PHP untuk distribusi latensi pencarian.
     *
     * @param  array<string, int|float>  $distribution  [bucket_name => count]
     */
    public function renderLatencySvgChart(array $distribution, int $width = 600, int $height = 250): string
    {
        if (empty($distribution)) {
            return '<svg width="'.$width.'" height="'.$height.'"><text x="20" y="30" fill="#94a3b8">Tidak ada data</text></svg>';
        }

        $maxVal = max(1.0, (float) max($distribution));
        $padding = 40;
        $barWidth = (int) (($width - ($padding * 2)) / max(1, count($distribution))) - 10;
        $chartHeight = $height - ($padding * 2);

        $barsSvg = '';
        $x = $padding;

        foreach ($distribution as $label => $val) {
            $valFloat = (float) $val;
            $barHeight = (int) (($valFloat / $maxVal) * $chartHeight);
            $y = $height - $padding - $barHeight;

            $barsSvg .= sprintf(
                '<rect x="%d" y="%d" width="%d" height="%d" fill="#3b82f6" rx="3" opacity="0.85" />',
                $x,
                $y,
                $barWidth,
                $barHeight
            );

            // Label teks bawah
            $textX = $x + (int) ($barWidth / 2);
            $barsSvg .= sprintf(
                '<text x="%d" y="%d" font-size="10" fill="#94a3b8" text-anchor="middle">%s</text>',
                $textX,
                $height - 15,
                htmlspecialchars(mb_substr((string) $label, 0, 8), ENT_QUOTES, 'UTF-8')
            );

            // Nilai di atas batang
            $barsSvg .= sprintf(
                '<text x="%d" y="%d" font-size="10" fill="#e2e8f0" text-anchor="middle">%s</text>',
                $textX,
                max(15, $y - 6),
                (string) $val
            );

            $x += $barWidth + 10;
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" class="w-full h-auto" role="img" aria-label="Grafik Distribusi Latensi"><rect width="100%%" height="100%%" fill="#0f172a" rx="8" />%s</svg>',
            $width,
            $height,
            $barsSvg
        );
    }
}
