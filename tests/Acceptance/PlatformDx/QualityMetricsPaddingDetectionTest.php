<?php

declare(strict_types=1);

namespace Tests\Acceptance\PlatformDx;

use App\Quality\Algorithms\ClassReachabilityGraph;
use App\Quality\Algorithms\DecisionDensityCounter;
use App\Quality\Algorithms\DuplicateWindowDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Fixtures\Quality\Padding\DuplicatedAlgorithmBlock;
use Tests\Fixtures\Quality\Padding\EmptyAnemicDto;
use Tests\Fixtures\Quality\Padding\OrphanUnreachableService;
use Tests\TestCase;

class QualityMetricsPaddingDetectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_detector_catches_token_duplication_fixture_m4(): void
    {
        $detector = new DuplicateWindowDetector(windowSize: 20);
        $fixturePath = base_path('tests/Fixtures/Quality/Padding/DuplicatedAlgorithmBlock.php');
        $code = file_get_contents($fixturePath);

        $report = $detector->analyzeCode($code, $fixturePath);

        // Harus mendeteksi duplikasi token antara calculateFirst dan calculateSecond
        expect($report->hasDuplicates())->toBeTrue();
        expect($report->duplicateCount)->toBeGreaterThan(0);
        expect($report->duplicateRatio)->toBeGreaterThan(0.15);
    }

    public function test_counter_evaluates_decision_density_and_flags_anemic_classes_m3(): void
    {
        $counter = new DecisionDensityCounter();

        // 1. DTO anemik tanpa keputusan logika
        $anemicCode = file_get_contents(base_path('tests/Fixtures/Quality/Padding/EmptyAnemicDto.php'));
        $anemicResult = $counter->analyze($anemicCode);
        expect($anemicResult->decisionPoints)->toBe(0);
        expect($anemicResult->isAnemic())->toBeTrue();

        // 2. Kode dengan percabangan padat
        $denseCode = file_get_contents(base_path('tests/Fixtures/Quality/Padding/DuplicatedAlgorithmBlock.php'));
        $denseResult = $counter->analyze($denseCode);
        expect($denseResult->decisionPoints)->toBeGreaterThan(2);
        expect($denseResult->density)->toBeGreaterThan(0.10);
    }

    public function test_reachability_graph_catches_orphan_unreachable_class_m5(): void
    {
        $graph = new ClassReachabilityGraph();
        $graph->buildGraph([
            base_path('tests/Fixtures/Quality/Padding'),
        ]);

        $orphanClass = OrphanUnreachableService::class;
        $orphans = $graph->detectOrphans();

        expect($orphans)->toContain($orphanClass);
    }

    public function test_quality_phase_metrics_command_enforces_t10_anti_padding_rules(): void
    {
        $exitCode = Artisan::call('quality:phase-metrics', [
            '--path' => 'tests/Fixtures/Quality/Padding',
            '--check' => true,
        ]);

        // Karena fixture padding sengaja diuji, command harus mengembalikan exit code != 0
        // menandakan deteksi sukses pelanggaran M-3, M-4, atau M-5
        expect($exitCode)->not->toBe(0);

        $output = Artisan::output();
        expect($output)->toContain('M-3');
        expect($output)->toContain('M-4');
        expect($output)->toContain('M-5');
    }
}

