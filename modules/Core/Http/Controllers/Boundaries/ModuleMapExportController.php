<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Boundaries;

use App\Http\Controllers\Controller;
use App\Quality\Modules\BoundariesScanner;
use Illuminate\Http\Request;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Domain\Algorithms\Boundaries\TablePrefixOwnershipResolver;
use Modules\Core\Domain\Enums\ViolationStatus;
use Modules\Core\Domain\Models\CoreBoundaryViolation;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ModuleMapExportController extends Controller
{
    public function __construct(
        private readonly BoundariesScanner $scanner,
        private readonly ?ClockInterface $clock = null,
    ) {}

    public function exportViolations(Request $request): StreamedResponse
    {
        $violations = CoreBoundaryViolation::orderBy('id')->get();

        return $this->streamCsv(
            'boundary_violations.csv',
            ['ID', 'Rule ID', 'Category', 'Client Module', 'Owner Module', 'File Path', 'Line Number', 'Status', 'ADR Anchor', 'Expires At'],
            function ($handle) use ($violations): void {
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
            }
        );
    }

    public function exportWaivers(Request $request): StreamedResponse
    {
        $now = ($this->clock ?? app(ClockInterface::class))->now();
        $waivers = CoreBoundaryViolation::where('status', ViolationStatus::WAIVED->value)
            ->orderBy('expires_at')
            ->get();

        return $this->streamCsv(
            'boundary_waivers.csv',
            ['ID', 'Rule ID', 'Client Module', 'Owner Module', 'Signature', 'ADR Anchor', 'Reason', 'Waived By', 'Waived At', 'Expires At', 'Days Remaining'],
            function ($handle) use ($waivers, $now): void {
                foreach ($waivers as $w) {
                    $daysRemaining = $w->expires_at !== null ? max(0, (int) $now->diffInDays($w->expires_at, false)) : 0;
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
            }
        );
    }

    public function exportCoverage(Request $request): StreamedResponse
    {
        $analysis = $this->scanner->scan(base_path(), (array) config('modules'));
        $scores = $analysis['coverage']['perModuleScores'];
        ksort($scores);

        return $this->streamCsv(
            'contract_coverage.csv',
            ['Module', 'Dependencies Count', 'Coverage Permille', 'Coverage Percentage'],
            function ($handle) use ($scores, $analysis): void {
                foreach ($scores as $module => $score) {
                    fputcsv($handle, [
                        $module,
                        count($analysis['dependencyGraph'][$module] ?? []),
                        $score,
                        sprintf('%.1f%%', $score / 10),
                    ]);
                }
            }
        );
    }

    public function exportTableOwnership(Request $request): StreamedResponse
    {
        $config = (array) config('modules', require base_path('config/modules.php'));
        $resolver = new TablePrefixOwnershipResolver(
            $config['table_prefixes'] ?? [],
            $config['legacy_tables'] ?? [],
            $config['shared_tables'] ?? [],
        );

        return $this->streamCsv(
            'table_ownership.csv',
            ['Prefix / Table', 'Owner Module', 'Type'],
            function ($handle) use ($resolver, $config): void {
                foreach ($resolver->prefixes() as $prefix => $owner) {
                    fputcsv($handle, [$prefix, $owner, 'Prefix Registered']);
                }

                foreach ($config['legacy_tables'] ?? [] as $table => $owner) {
                    fputcsv($handle, [$table, $owner, 'Legacy Table']);
                }
            }
        );
    }

    /**
     * @param  list<string>  $headers
     * @param  callable(resource): void  $writer
     */
    private function streamCsv(string $filename, array $headers, callable $writer): StreamedResponse
    {
        return response()->stream(function () use ($headers, $writer): void {
            $handle = fopen('php://output', 'w');
            if ($handle !== false) {
                fputcsv($handle, $headers);
                $writer($handle);
                fclose($handle);
            }
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
