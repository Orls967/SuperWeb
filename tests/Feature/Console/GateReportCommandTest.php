<?php

declare(strict_types=1);

use App\Quality\Gate\GateManifest;
use Illuminate\Support\Facades\File;

/*
| PROGRESS R0.2: GateReportCommandTest.
| Memvalidasi perintah `php artisan gate:report --fase=N` dengan penolakan ketat:
| - manifest hilang / korup / langkah wajib hilang / step gagal / commit mismatch / dirty
| - junit hilang / failure
| - test bukti tidak ditemukan / gagal di JUnit
*/

function createValidManifestData(string $commit, array $overrides = []): array
{
    $steps = [];
    foreach (GateManifest::REQUIRED_STEP_KEYS as $key) {
        $steps[] = [
            'name' => $key,
            'command' => $key,
            'exit_code' => 0,
            'duration' => 0.1,
            'status' => 'PASS',
        ];
    }

    return array_merge([
        'commit' => $commit,
        'is_dirty' => false,
        'timestamp' => '2026-10-10T12:00:00+00:00',
        'overall_status' => 'PASS',
        'steps' => $steps,
    ], $overrides);
}

function createSampleJunitXml(array $testCases = []): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
    $xml .= '<testsuites>'."\n";
    $xml .= '  <testsuite name="TestSuite" tests="'.count($testCases).'" assertions="10" errors="0" failures="0" time="1.0">'."\n";
    foreach ($testCases as $tc) {
        $xml .= '    <testcase name="'.htmlspecialchars((string) $tc['name']).'" file="'.htmlspecialchars((string) ($tc['file'] ?? '')).'" class="Tests\\Sample" time="0.01">'."\n";
        if (($tc['status'] ?? 'PASS') === 'FAIL') {
            $xml .= '      <failure type="ExpectationFailedException">Test failed assertion</failure>'."\n";
        }
        $xml .= '    </testcase>'."\n";
    }
    $xml .= '  </testsuite>'."\n";
    $xml .= '</testsuites>'."\n";

    return $xml;
}

it('refuses to generate report under each invalid gate condition', function (string $scenario, Closure $setup, string $expectedMessage): void {
    $tempDir = sys_get_temp_dir().'/gate_test_'.uniqid();
    File::makeDirectory($tempDir, 0777, true);

    $manifestFile = $tempDir.'/manifest.json';
    $junitFile = $tempDir.'/junit.xml';
    $outputReport = $tempDir.'/docs/gates/fase-r0.md';

    $headCommit = trim((string) shell_exec('git rev-parse HEAD 2>/dev/null')) ?: '1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b';

    $setup($manifestFile, $junitFile, $headCommit);

    $commandParams = [
        '--fase' => 'R0',
        '--manifest' => $manifestFile,
        '--junit' => $junitFile,
        '--output' => $outputReport,
    ];

    $this->artisan('gate:report', $commandParams)
        ->expectsOutputToContain($expectedMessage)
        ->assertFailed();

    expect(File::exists($outputReport))->toBeFalse("File laporan tidak boleh terbentuk pada skenario '{$scenario}'.");

    File::deleteDirectory($tempDir);
})->with([
    'manifest missing' => [
        'scenario' => 'manifest missing',
        'setup' => function (string $manifestFile, string $junitFile, string $commit): void {
            // Manifest not created
            File::put($junitFile, createSampleJunitXml());
        },
        'expectedMessage' => 'File gate manifest tidak ditemukan',
    ],
    'manifest corrupt' => [
        'scenario' => 'manifest corrupt',
        'setup' => function (string $manifestFile, string $junitFile, string $commit): void {
            File::put($manifestFile, 'NOT_VALID_JSON{{{');
            File::put($junitFile, createSampleJunitXml());
        },
        'expectedMessage' => 'Format gate manifest rusak atau tidak valid',
    ],
    'mandatory step missing' => [
        'scenario' => 'mandatory step missing',
        'setup' => function (string $manifestFile, string $junitFile, string $commit): void {
            $data = createValidManifestData($commit);
            // remove npm run build step
            $data['steps'] = array_filter($data['steps'], fn ($s) => $s['name'] !== 'npm run build');
            File::put($manifestFile, json_encode($data));
            File::put($junitFile, createSampleJunitXml());
        },
        'expectedMessage' => 'Langkah wajib gate hilang dari manifest',
    ],
    'gate step failed' => [
        'scenario' => 'gate step failed',
        'setup' => function (string $manifestFile, string $junitFile, string $commit): void {
            $data = createValidManifestData($commit);
            $data['steps'][0]['exit_code'] = 1;
            $data['steps'][0]['status'] = 'FAIL';
            $data['overall_status'] = 'FAIL';
            File::put($manifestFile, json_encode($data));
            File::put($junitFile, createSampleJunitXml());
        },
        'expectedMessage' => 'Quality gate GAGAL: langkah',
    ],
    'commit mismatch' => [
        'scenario' => 'commit mismatch',
        'setup' => function (string $manifestFile, string $junitFile, string $commit): void {
            $data = createValidManifestData('old_stale_commit_hash_123456');
            File::put($manifestFile, json_encode($data));
            File::put($junitFile, createSampleJunitXml());
        },
        'expectedMessage' => 'tidak cocok dengan HEAD',
    ],
    'working tree dirty' => [
        'scenario' => 'working tree dirty',
        'setup' => function (string $manifestFile, string $junitFile, string $commit): void {
            $data = createValidManifestData($commit, ['is_dirty' => true]);
            File::put($manifestFile, json_encode($data));
            File::put($junitFile, createSampleJunitXml());
        },
        'expectedMessage' => 'Gate dijalankan pada working tree yang kotor (dirty)',
    ],
    'junit missing' => [
        'scenario' => 'junit missing',
        'setup' => function (string $manifestFile, string $junitFile, string $commit): void {
            $data = createValidManifestData($commit);
            File::put($manifestFile, json_encode($data));
            // JUnit file not created
        },
        'expectedMessage' => 'File log JUnit XML tidak ditemukan',
    ],
    'junit has failures' => [
        'scenario' => 'junit has failures',
        'setup' => function (string $manifestFile, string $junitFile, string $commit): void {
            $data = createValidManifestData($commit);
            File::put($manifestFile, json_encode($data));
            $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="TestSuite" tests="5" assertions="10" errors="0" failures="1" time="1.0">
    <testcase name="test_failing" class="Tests\Sample" time="0.01">
      <failure type="ExpectationFailedException">Assertion failed</failure>
    </testcase>
  </testsuite>
</testsuites>
XML;
            File::put($junitFile, $xml);
        },
        'expectedMessage' => 'Quality gate GAGAL: JUnit mencatat',
    ],
]);

it('generates gate report when manifest and test suite are clean and match HEAD', function (): void {
    $tempDir = sys_get_temp_dir().'/gate_test_'.uniqid();
    File::makeDirectory($tempDir, 0777, true);

    $headCommit = trim((string) shell_exec('git rev-parse HEAD 2>/dev/null')) ?: '1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b';
    $manifestFile = $tempDir.'/manifest.json';
    $junitFile = $tempDir.'/junit.xml';
    $outputReport = $tempDir.'/docs/gates/fase-r0.md';

    $data = createValidManifestData($headCommit);
    File::put($manifestFile, json_encode($data));

    // JUnit with passing test cases for all proof tests in Fase R0
    $passingXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="TestSuite" tests="20" assertions="50" errors="0" failures="0" time="12.5">
    <testcase name="it ensures composer.json and README.md align on PHP ^8.4 requirement" file="tests/Architecture/DocsVersionConsistencyTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="it generates gate report when manifest and test suite are clean and match HEAD" file="tests/Feature/Console/GateReportCommandTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="it keeps production classes at their case-sensitive PSR-4 path" file="tests/Architecture/Psr4ComplianceTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="it enforces proof block integrity and phase verification in docs/PROGRESS.md" file="tests/Architecture/ProgressIntegrityTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="passes when valid proof block is provided for a checked item" file="tests/Unit/Quality/Progress/ProgressIntegrityScannerTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="it ensures LAPORAN_AUDIT_GELOMBANG_2.md is explicitly marked as archived and invalid" file="tests/Architecture/DocsVersionConsistencyTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="it ensures CODEBASE.md does not reference removed non-existent accounts" file="tests/Architecture/DocsVersionConsistencyTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="it declares every artisan command name exactly once" file="tests/Architecture/CommandSignatureUniqueTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="runs the integration and platform economy audits and passes on consistent data" file="modules/Integration/tests/Feature/ApiAuditCommandTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="it verifies pull request template contains V1-V12 and C1-C14 checklists" file="tests/Architecture/GateTemplateContractTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="it verifies docs/gates/README.md provides guidance on reading gate reports" file="tests/Architecture/GateTemplateContractTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="it keeps module code within the arch:scan ratchet baseline" file="tests/Architecture/ArchScanBaselineTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="fails and names the violation when it is not covered by the baseline" file="tests/Feature/Console/ArchScanCommandTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="it maps every registered route in route-roles.php" file="tests/Architecture/RouteAuthorizationMatrixTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="it enforces that route authorization never weakens compared to baseline" file="tests/Architecture/RouteAuthorizationMatrixTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="it keeps pending dynamic routes within the ratchet baseline" file="tests/Architecture/RouteAuthorizationMatrixTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="it enforces that unauthorized roles receive 403 on role-protected routes" file="tests/Architecture/RouteAuthorizationMatrixTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="it confirms authorized roles are not forbidden (not 403) on role-protected routes" file="tests/Architecture/RouteAuthorizationMatrixTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="it confirms bank:reconcile passes on clean state and fails when data is corrupted" file="tests/Architecture/AuditCommandContractTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="it confirms api:audit passes on clean state and fails when signature is corrupted" file="tests/Architecture/AuditCommandContractTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="it keeps audit commands without corruption fixtures within the ratchet baseline" file="tests/Architecture/AuditCommandContractTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="it keeps unseeded ledger accounts within the ratchet baseline" file="tests/Architecture/LedgerAccountRegistryTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="it keeps accounts with inverted normal balances within the ratchet baseline" file="tests/Architecture/LedgerNormalBalanceTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="it keeps modules/Integration frozen except for external adapters and intg_ tables" file="tests/Architecture/IntegrationFreezeTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="it keeps tests within the hygiene ratchet baseline" file="tests/Architecture/TestHygieneTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="it verifies MutationTestCommand is registered and constructs correct Pest CLI invocation" file="tests/Architecture/MutationTestingContractTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="it verifies CI workflow defines mutation testing job with PCOV and 60% minimum threshold" file="tests/Architecture/MutationTestingContractTest.php" class="Tests\Sample" time="0.05" />
    <testcase name="it verifies Pest CLI supports mutation testing options" file="tests/Architecture/MutationTestingContractTest.php" class="Tests\Sample" time="0.05" />
  </testsuite>
</testsuites>
XML;
    File::put($junitFile, $passingXml);

    $this->artisan('gate:report', [
        '--fase' => 'R0',
        '--manifest' => $manifestFile,
        '--junit' => $junitFile,
        '--output' => $outputReport,
    ])
        ->expectsOutputToContain('Laporan quality gate fase R0 berhasil ditulis')
        ->assertSuccessful();

    expect(File::exists($outputReport))->toBeTrue('File laporan harus terbentuk pada kondisi bersih.');

    $content = File::get($outputReport);
    expect($content)->toContain('# Quality Gate Report: Fase R0')
        ->and($content)->toContain($headCommit)
        ->and($content)->toContain('composer validate --strict')
        ->and($content)->toContain('npm run build');

    File::deleteDirectory($tempDir);
});
