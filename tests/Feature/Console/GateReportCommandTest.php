<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
| PROGRESS R0.2: GateReportCommandTest.
| Memvalidasi perintah `php artisan gate:report --fase=N` dari output asli JUnit.
| Memastikan laporan tidak bisa dibuat bila gate gagal (K-02, K-04).
*/

it('refuses to generate report when JUnit XML file does not exist', function (): void {
    $this->artisan('gate:report', [
        '--fase' => 'R0',
        '--junit' => 'storage/logs/non_existent_path_to_junit.xml',
    ])
        ->expectsOutputToContain('File log JUnit XML tidak ditemukan')
        ->assertFailed();
});

it('refuses to generate report when test suite has failures (gate failed)', function (): void {
    $tempDir = sys_get_temp_dir().'/gate_test_'.uniqid();
    File::makeDirectory($tempDir, 0777, true);

    $failingXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="TestSuite" tests="10" assertions="25" errors="0" failures="2" time="2.14">
    <testcase name="test_success" class="Tests\Feature\SampleTest" time="0.10" assertions="2" />
    <testcase name="test_broken" class="Tests\Feature\SampleTest" time="0.15" assertions="1">
      <failure type="ExpectationFailedException">Failed asserting that false is true.</failure>
    </testcase>
  </testsuite>
</testsuites>
XML;

    $junitFile = $tempDir.'/failing-junit.xml';
    $outputReport = $tempDir.'/docs/gates/fase-r0.md';
    File::put($junitFile, $failingXml);

    $this->artisan('gate:report', [
        '--fase' => 'R0',
        '--junit' => $junitFile,
        '--output' => $outputReport,
    ])
        ->expectsOutputToContain('Quality gate GAGAL: JUnit mencatat 2 kegagalan')
        ->assertFailed();

    expect(File::exists($outputReport))->toBeFalse('File laporan tidak boleh dibuat bila gate gagal.');

    File::deleteDirectory($tempDir);
});

it('generates gate report when test suite passes and matches JUnit fixture data', function (): void {
    $tempDir = sys_get_temp_dir().'/gate_test_'.uniqid();
    File::makeDirectory($tempDir, 0777, true);

    $passingXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="TestSuite" tests="42" assertions="128" errors="0" failures="0" time="3.75">
    <testcase name="it maps every registered route in route-roles.php" class="Tests\Architecture\RouteAuthorizationMatrixTest" file="tests/Architecture/RouteAuthorizationMatrixTest.php" time="0.12" assertions="5" />
    <testcase name="it executes commands" class="Tests\Feature\CommandTest" file="tests/Feature/CommandTest.php" time="0.45" assertions="3" />
  </testsuite>
</testsuites>
XML;

    $junitFile = $tempDir.'/passing-junit.xml';
    $outputReport = $tempDir.'/docs/gates/fase-r0.md';
    File::put($junitFile, $passingXml);

    $this->artisan('gate:report', [
        '--fase' => 'R0',
        '--junit' => $junitFile,
        '--output' => $outputReport,
    ])
        ->expectsOutputToContain('Laporan quality gate fase R0 berhasil ditulis ke')
        ->assertSuccessful();

    expect(File::exists($outputReport))->toBeTrue();

    $content = File::get($outputReport);
    expect($content)
        ->toContain('# Quality Gate Report: Fase R0')
        ->toContain('- **Total Test:** 42')
        ->toContain('- **Total Assertion:** 128')
        ->toContain('- **Durasi:** 3.75s')
        ->toContain('## 2. Hasil Perintah Protokol P4')
        ->toContain('`composer gate`')
        ->toContain('`php artisan arch:scan`')
        ->toContain('## 5. Verifikasi')
        ->toContain('- **Checklist Verifikator (C1–C14):**');

    File::deleteDirectory($tempDir);
});

it('supports dry-run mode printing report to stdout without writing file', function (): void {
    $tempDir = sys_get_temp_dir().'/gate_test_'.uniqid();
    File::makeDirectory($tempDir, 0777, true);

    $passingXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="TestSuite" tests="5" assertions="12" errors="0" failures="0" time="0.55">
    <testcase name="sample_test" class="Tests\SampleTest" time="0.10" assertions="2" />
  </testsuite>
</testsuites>
XML;

    $junitFile = $tempDir.'/passing-junit.xml';
    $outputReport = $tempDir.'/docs/gates/fase-r0.md';
    File::put($junitFile, $passingXml);

    $this->artisan('gate:report', [
        '--fase' => 'R0',
        '--junit' => $junitFile,
        '--output' => $outputReport,
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('# Quality Gate Report: Fase R0')
        ->expectsOutputToContain('- **Total Test:** 5')
        ->assertSuccessful();

    expect(File::exists($outputReport))->toBeFalse('Dry-run tidak boleh menulis ke disk.');

    File::deleteDirectory($tempDir);
});
