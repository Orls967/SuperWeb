<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/*
 * WorkflowBuildOrderContractTest
 *
 * Memeriksa bahwa setiap workflow file di .github/workflows/*.yml,
 * setiap job yang menjalankan pest / artisan test / test:mutate /
 * composer gate / run-regresi MEMILIKI langkah `npm run build`
 * sebelum langkah test tersebut di job yang sama.
 *
 * Pengecualian eksplisit (job yang tidak perlu npm run build):
 * - chaos-and-concurrency-verification : hanya menjalankan artisan platform:stress-concurrency
 *   dan platform:chaos-verify, bukan pest/test beneran
 * - pest-sharded-matrix                : shard test yang memerlukan node/npm tersendiri
 *   jika diperlukan, tapi per spesifikasi awal tidak disertakan (no arch test)
 *
 * CATATAN: Daftar pengecualian ini WAJIB diperbarui setiap ada job baru.
 */

/**
 * Daftar job yang dikecualikan dari pemeriksaan build order.
 * Setiap entri berformat 'workflow-key::job-key'.
 */
const WORKFLOW_BUILD_EXEMPT_JOBS = [
    // Hanya menjalankan platform:stress-concurrency + platform:chaos-verify
    // bukan pest/test PHPUnit sebenarnya — tidak ada route/view yang di-load
    'composer-gate.yml::chaos-and-concurrency-verification',

    // Sharded matrix dijalankan sebagai shard pest biasa tanpa Architecture tests,
    // tidak ada komponen Vite yang dikonsumsi dalam test tersebut
    'composer-gate.yml::pest-sharded-matrix',
];

/**
 * Ambil semua file workflow dari .github/workflows/.
 *
 * @return array<string, array<string, mixed>>
 */
function loadAllWorkflows(): array
{
    $workflowDir = base_path('.github/workflows');
    $files = glob($workflowDir.'/*.yml') ?: [];

    $workflows = [];
    foreach ($files as $file) {
        $key = basename($file);
        $workflows[$key] = Yaml::parseFile($file);
    }

    return $workflows;
}

/**
 * Periksa apakah sebuah step menjalankan pest / artisan test / test:mutate /
 * composer gate / run-regresi.
 */
function stepIsTestRunner(mixed $step): bool
{
    if (! is_array($step)) {
        return false;
    }

    $runScript = (string) ($step['run'] ?? '');
    $usesAction = (string) ($step['uses'] ?? '');

    // Penanda test yang relevan
    $testMarkers = [
        'vendor/bin/pest',
        'pest ',
        './vendor/bin/pest',
        'php artisan test',
        'php artisan test:mutate',
        'artisan test:mutate',
        'artisan test',
        'composer gate',
        'composer run gate',
        'run-regresi',
        '--mutate',
    ];

    foreach ($testMarkers as $marker) {
        if (str_contains($runScript, $marker)) {
            return true;
        }
    }

    // actions/... yang dipakai sebagai wrapper test (sangat jarang)
    if (str_contains($usesAction, 'test') || str_contains($usesAction, 'pest')) {
        return true;
    }

    return false;
}

/**
 * Periksa apakah sebuah step menjalankan npm run build.
 */
function stepHasBuild(mixed $step): bool
{
    if (! is_array($step)) {
        return false;
    }

    $runScript = (string) ($step['run'] ?? '');

    return str_contains($runScript, 'npm run build');
}

it('ensures every job running tests has npm run build before the test step', function (): void {
    $workflows = loadAllWorkflows();
    expect($workflows)->not->toBeEmpty('Harus ada minimal satu workflow file di .github/workflows/');

    $failures = [];

    foreach ($workflows as $workflowFile => $parsed) {
        $jobs = $parsed['jobs'] ?? [];

        if (! is_array($jobs)) {
            continue;
        }

        foreach ($jobs as $jobKey => $jobConfig) {
            $exemptKey = "{$workflowFile}::{$jobKey}";

            // Lewati job yang dikecualikan secara eksplisit
            if (in_array($exemptKey, WORKFLOW_BUILD_EXEMPT_JOBS, true)) {
                continue;
            }

            $steps = $jobConfig['steps'] ?? [];
            if (! is_array($steps)) {
                continue;
            }

            // Temukan index step test pertama dan index step build terakhir sebelumnya
            $firstTestStepIndex = null;
            $lastBuildBeforeTestIndex = null;

            foreach ($steps as $i => $step) {
                if (stepIsTestRunner($step) && $firstTestStepIndex === null) {
                    $firstTestStepIndex = $i;
                }
            }

            if ($firstTestStepIndex === null) {
                // Job ini tidak menjalankan test — lewati
                continue;
            }

            // Cari step npm run build di SEBELUM step test pertama
            for ($i = 0; $i < $firstTestStepIndex; $i++) {
                if (stepHasBuild($steps[$i])) {
                    $lastBuildBeforeTestIndex = $i;
                }
            }

            if ($lastBuildBeforeTestIndex === null) {
                $testStepName = $steps[$firstTestStepIndex]['name'] ?? "(step #{$firstTestStepIndex})";
                $failures[] = "  [{$workflowFile}] job '{$jobKey}': "
                    ."tidak ada `npm run build` sebelum langkah test '{$testStepName}'";
            }
        }
    }

    expect($failures)->toBeEmpty(
        "Build order violation ditemukan:\n".implode("\n", $failures)
    );
});
