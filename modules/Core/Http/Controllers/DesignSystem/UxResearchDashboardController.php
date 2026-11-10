<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\DesignSystem;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Modules\Core\Domain\Models\UxFinding;
use Modules\Core\Domain\Models\UxIteration;
use Modules\Shared\Application\Ui\CsvExporter;

final class UxResearchDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $iterations = UxIteration::orderBy('iteration_number')->get();
        $findings = UxFinding::with('component')
            ->orderByRaw("CASE severity WHEN 'blocker' THEN 1 WHEN 'major' THEN 2 WHEN 'minor' THEN 3 ELSE 4 END")
            ->paginate(15);

        // Agregasi metrik
        $avgSus = $iterations->avg('sus_score') ?? 0;
        $avgSuccessRate = $iterations->avg('task_success_rate') ?? 0;
        $totalFindings = UxFinding::count();
        $resolvedFindings = UxFinding::where('is_resolved', true)->count();

        // Data grafik tren SVG
        $chartLabels = $iterations->pluck('name')->toArray();
        $susValues = $iterations->map(fn ($it) => (int) round((float) $it->sus_score))->toArray();
        $successRateValues = $iterations->map(fn ($it) => (int) round((float) $it->task_success_rate))->toArray();

        return view('core::design-system.research.index', [
            'iterations' => $iterations,
            'findings' => $findings,
            'avgSus' => round((float) $avgSus, 1),
            'avgSuccessRate' => round((float) $avgSuccessRate, 1),
            'totalFindings' => $totalFindings,
            'resolvedFindings' => $resolvedFindings,
            'chartLabels' => $chartLabels,
            'susValues' => $susValues,
            'successRateValues' => $successRateValues,
        ]);
    }

    public function exportCsv(Request $request, CsvExporter $csvExporter): Response
    {
        $findings = UxFinding::with('component')->get();
        $rows = [];

        foreach ($findings as $f) {
            $rows[] = [
                'ID' => (string) $f->id,
                'Iterasi' => (string) $f->iteration_id,
                'Komponen' => $f->component?->name ?? 'Global',
                'Kategori' => $f->category,
                'Keparahan' => $f->severity,
                'Deskripsi' => $f->description,
                'Rekomendasi' => $f->recommendation ?? '-',
                'Status' => $f->is_resolved ? 'Selesai' : 'Terbuka',
                'Dibuat' => (string) $f->created_at,
            ];
        }

        $csv = $csvExporter->export($rows);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="ux-findings-'.date('Ymd-His').'.csv"',
        ]);
    }
}

