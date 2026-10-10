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
| - mempertahankan bagian ## Verifikasi yang sudah ada
|
| Desain terisolasi: Menggunakan PROGRESS fixture mandiri per-test di folder temp,
| tidak membaca docs/PROGRESS.md repositori asli.
*/

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/gate_test_'.uniqid();
    File::makeDirectory($this->tempDir, 0777, true);
});

afterEach(function (): void {
    if (isset($this->tempDir) && File::isDirectory($this->tempDir)) {
        File::deleteDirectory($this->tempDir);
    }
});

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

function createProgressFixtureContent(): string
{
    return <<<'MD'
### FASE R0 — LINGKUNGAN, GATE & PAGAR OTOMATIS
- [x] R0.1 Contoh item uji satu
  Bukti:
    - test: tests/Unit/SampleTest.php::it passes sample test
- [x] R0.2 Contoh item uji dua
  Bukti:
    - test: tests/Unit/SecondTest.php::it passes second test
MD;
}

function createSampleJunitXml(array $testCases = []): string
{
    if ($testCases === []) {
        $testCases = [
            ['name' => 'it passes sample test', 'file' => 'tests/Unit/SampleTest.php', 'status' => 'PASS'],
            ['name' => 'it passes second test', 'file' => 'tests/Unit/SecondTest.php', 'status' => 'PASS'],
        ];
    }

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

it('generates gate report when manifest and test suite are clean and match HEAD', function (): void {
    $headCommit = trim((string) shell_exec('git rev-parse HEAD 2>/dev/null')) ?: '1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b';
    $manifestFile = $this->tempDir.'/manifest.json';
    $junitFile = $this->tempDir.'/junit.xml';
    $progressFile = $this->tempDir.'/PROGRESS.md';
    $outputReport = $this->tempDir.'/docs/gates/fase-r0.md';

    File::put($manifestFile, json_encode(createValidManifestData($headCommit)));
    File::put($progressFile, createProgressFixtureContent());
    File::put($junitFile, createSampleJunitXml());

    $this->artisan('gate:report', [
        '--fase' => 'R0',
        '--manifest' => $manifestFile,
        '--junit' => $junitFile,
        '--progress' => $progressFile,
        '--output' => $outputReport,
    ])
        ->expectsOutputToContain('Laporan quality gate fase R0 berhasil ditulis')
        ->assertSuccessful();

    expect(File::exists($outputReport))->toBeTrue('File laporan harus terbentuk pada kondisi bersih.');

    $content = File::get($outputReport);
    expect($content)->toContain('# Quality Gate Report: Fase R0')
        ->and($content)->toContain($headCommit)
        ->and($content)->toContain('it passes sample test')
        ->and($content)->toContain('PASS 🟢');
});

it('refuses to generate report under each invalid gate condition', function (string $scenario, Closure $setup, string $expectedMessage): void {
    $manifestFile = $this->tempDir.'/manifest.json';
    $junitFile = $this->tempDir.'/junit.xml';
    $progressFile = $this->tempDir.'/PROGRESS.md';
    $outputReport = $this->tempDir.'/docs/gates/fase-r0.md';

    $headCommit = trim((string) shell_exec('git rev-parse HEAD 2>/dev/null')) ?: '1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b';

    File::put($progressFile, createProgressFixtureContent());

    $setup($manifestFile, $junitFile, $headCommit);

    $commandParams = [
        '--fase' => 'R0',
        '--manifest' => $manifestFile,
        '--junit' => $junitFile,
        '--progress' => $progressFile,
        '--output' => $outputReport,
    ];

    $this->artisan('gate:report', $commandParams)
        ->expectsOutputToContain($expectedMessage)
        ->assertFailed();

    expect(File::exists($outputReport))->toBeFalse("File laporan tidak boleh terbentuk pada skenario '{$scenario}'.");
})->with([
    'manifest missing' => [
        'scenario' => 'manifest missing',
        'setup' => function (string $manifestFile, string $junitFile, string $commit): void {
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
    'test bukti missing in junit' => [
        'scenario' => 'test bukti missing in junit',
        'setup' => function (string $manifestFile, string $junitFile, string $commit): void {
            $data = createValidManifestData($commit);
            File::put($manifestFile, json_encode($data));
            // Only provide 1 test case, missing the second test in progress fixture
            $xml = createSampleJunitXml([
                ['name' => 'it passes sample test', 'file' => 'tests/Unit/SampleTest.php', 'status' => 'PASS'],
            ]);
            File::put($junitFile, $xml);
        },
        'expectedMessage' => 'gagal atau tidak ditemukan di JUnit',
    ],
]);

it('preserves existing verification section when report is regenerated', function (): void {
    $headCommit = trim((string) shell_exec('git rev-parse HEAD 2>/dev/null')) ?: '1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b';
    $manifestFile = $this->tempDir.'/manifest.json';
    $junitFile = $this->tempDir.'/junit.xml';
    $progressFile = $this->tempDir.'/PROGRESS.md';
    $outputReport = $this->tempDir.'/docs/gates/fase-r0.md';

    File::put($manifestFile, json_encode(createValidManifestData($headCommit)));
    File::put($progressFile, createProgressFixtureContent());
    File::put($junitFile, createSampleJunitXml());

    // Pre-create output report with an existing verified section
    $existingReport = <<<'MD'
# Quality Gate Report: Fase R0

Old content that will be overwritten.

## 5. Verifikasi
*(Bagian ini wajib diisi oleh verifikator independen sebelum mengubah status fase ke ✅ sesuai P7).*

- **Tanggal Verifikasi:** 2026-10-11
- **Verifikator:** Auditor Independen Lead
- **Checklist Verifikator (C1–C14):**
  - [x] C1 Kode ada di modul pemilik yang benar
  - [x] C2 Tidak ada tabel/kolom liar tanpa prefiks registry
  - [ ] C3 Double-entry integer minor unit; saldo normal seimbang
- **Catatan Temuan / Rekomendasi:** Verifikasi tahap satu disetujui bersyarat.
MD;

    File::ensureDirectoryExists(dirname($outputReport));
    File::put($outputReport, $existingReport);

    $this->artisan('gate:report', [
        '--fase' => 'R0',
        '--manifest' => $manifestFile,
        '--junit' => $junitFile,
        '--progress' => $progressFile,
        '--output' => $outputReport,
    ])
        ->expectsOutputToContain('Laporan quality gate fase R0 berhasil ditulis')
        ->assertSuccessful();

    $content = File::get($outputReport);

    expect($content)->toContain('# Quality Gate Report: Fase R0')
        ->and($content)->toContain('- **Verifikator:** Auditor Independen Lead')
        ->and($content)->toContain('- [x] C1 Kode ada di modul pemilik yang benar')
        ->and($content)->toContain('Verifikasi tahap satu disetujui bersyarat.');
});

it('strictly removes --force option from gate:report command signature (V5)', function (): void {
    $command = app(\Illuminate\Contracts\Console\Kernel::class)->all()['gate:report'] ?? null;
    expect($command)->not->toBeNull()
        ->and($command->getDefinition()->hasOption('force'))->toBeFalse('Opsi --force DILARANG pada gate:report (V5).');
});

it('preserves ## Verifikasi section regardless of custom header title or level (V5)', function (): void {
    $generator = new \App\Quality\Gate\GateReportGenerator(base_path());
    $reflection = new ReflectionClass($generator);
    $method = $reflection->getMethod('extractExistingVerification');
    $method->setAccessible(true);

    $doc = <<<'MD'
# Header Laporan

Beberapa teks laporan.

### 5. Verifikasi Auditor
- **Tanggal:** 2026-10-11
- **Status:** LULUS
MD;

    $result = $method->invoke($generator, $doc);
    expect($result)->toContain('### 5. Verifikasi Auditor')
        ->and($result)->toContain('- **Status:** LULUS');
});
