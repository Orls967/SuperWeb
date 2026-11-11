<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Boundaries;

use App\Http\Controllers\Controller;
use App\Quality\Modules\BoundariesScanner;
use Illuminate\Http\Request;
use Modules\Core\Domain\Algorithms\Boundaries\TablePrefixOwnershipResolver;
use Modules\Core\Domain\Enums\ViolationStatus;
use Modules\Core\Domain\Models\CoreBoundaryViolation;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ModuleMapExportController extends Controller
{
    public function __construct(
        private readonly BoundariesScanner $scanner,
    ) {}

    public function exportViolations(Request $request): StreamedResponse
    {
        $violations = CoreBoundaryViolation::orderBy('id')->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="boundary_violations.csv"',
        ];

        return response()->stream(function () use ($violations): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Rule ID', 'Category', 'Client Module', 'Owner Module', 'File Path', 'Line Number', 'Status', 'ADR Anchor', 'Expires At']);

            foreach ($violations as $v) {
                fputcsv($handle, [
                    $v->id,
                    $v->rule_id,
                    $v->category?->value ?? (string) $v->category,
                    $v->client_module,
                    $v->owner_module,
                    $v->file_path,
                    $v->line_number,
                    $v->status?->value ?? (string) $v->status,
                    $v->adr_anchor ?? '-',
                    $v->expires_at?->toIso8601String() ?? '-',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    public function exportWaivers(Request $request): StreamedResponse
    {
        $waivers = CoreBoundaryViolation::where('status', ViolationStatus::WAIVED->value)
            ->orderBy('expires_at')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="boundary_waivers.csv"',
        ];

        return response()->stream(function () use ($waivers): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Rule ID', 'Client Module', 'Owner Module', 'Signature', 'ADR Anchor', 'Reason', 'Waived By', 'Waived At', 'Expires At', 'Days Remaining']);

            foreach ($waivers as $w) {
                $daysRemaining = $w->expires_at !== null ? max(0, (int) now()->diffInDays($w->expires_at, false)) : 0;

                fputcsv($handle, [
                    $w->id,
                    $w->rule_id,
                    $w->client_module,
                    $w->owner_module,
                    $w->signature,
                    $w->adr_anchor ?? '-',
                    $w->waiver_reason ?? '-',
                    $w->waived_by ?? '-',
                    $w->waived_at?->toIso8601String() ?? '-',
                    $w->expires_at?->toIso8601String() ?? '-',
                    $daysRemaining,
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    public function exportCoverage(Request $request): StreamedResponse
    {
        $analysis = $this->scanner->scan(base_path(), (array) config('modules'));
        $scores = $analysis['coverage']['perModuleScores'];
        ksort($scores);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="contract_coverage.csv"',
        ];

        return response()->stream(function () use ($scores, $analysis): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Module', 'Dependencies Count', 'Coverage Permille', 'Coverage Percentage']);

            foreach ($scores as $module => $score) {
                $depsCount = count($analysis['dependencyGraph'][$module] ?? []);
                $percentage = sprintf('%.1f%%', $score / 10);

                fputcsv($handle, [
                    $module,
                    $depsCount,
                    $score,
                    $percentage,
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    public function exportTableOwnership(Request $request): StreamedResponse
    {
        $config = (array) config('modules', require base_path('config/modules.php'));
        $resolver = new TablePrefixOwnershipResolver(
            $config['table_prefixes'] ?? [],
            $config['legacy_tables'] ?? [],
            $config['shared_tables'] ?? [],
        );

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="table_ownership.csv"',
        ];

        return response()->stream(function () use ($resolver, $config): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Prefix / Table', 'Owner Module', 'Type']);

            foreach ($resolver->prefixes() as $prefix => $owner) {
                fputcsv($handle, [$prefix, $owner, 'Prefix Registered']);
            }

            foreach ($config['legacy_tables'] ?? [] as $table => $owner) {
                fputcsv($handle, [$table, $owner, 'Legacy Table']);
            }

            fclose($handle);
        }, 200, $headers);
    }
}

