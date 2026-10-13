<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->scanRoot = 'storage/framework/testing/arch-scan-'.bin2hex(random_bytes(4));
    $this->baselinePath = $this->scanRoot.'/baseline.json';
    $this->controller = base_path($this->scanRoot.'/modules/Pricing/Http/Controllers/PriceController.php');
    File::ensureDirectoryExists(dirname($this->controller));
});

afterEach(function (): void {
    File::deleteDirectory(base_path($this->scanRoot));
});

function writeArchScanFixtureController(string $path, string $errorPath): void
{
    file_put_contents($path, "<?php\n\ndeclare(strict_types=1);\n\nnamespace Modules\\Pricing\\Http\\Controllers;\n\nfinal class PriceController\n{\n    public function store(): mixed\n    {\n        return {$errorPath};\n    }\n}\n");
}

it('fails and names the violation when it is not covered by the baseline', function (): void {
    writeArchScanFixtureController($this->controller, "back()->errors()->add('price', 'x')");

    $this->artisan('arch:scan', ['--path' => [$this->scanRoot], '--baseline' => $this->baselinePath])
        ->expectsOutputToContain('PriceController.php:11 :: back()->errors()')
        ->assertExitCode(1);
});

it('refuses to add a violation to the baseline without a decision reference', function (): void {
    writeArchScanFixtureController($this->controller, "back()->errors()->add('price', 'x')");

    $this->artisan('arch:scan', ['--path' => [$this->scanRoot], '--baseline' => $this->baselinePath, '--update-baseline' => true])
        ->expectsOutputToContain('Baseline tidak boleh naik')
        ->assertExitCode(1);

    expect(base_path($this->baselinePath))->not->toBeFile();
});

it('records the decision when a violation is added deliberately and then passes', function (): void {
    writeArchScanFixtureController($this->controller, "back()->errors()->add('price', 'x')");

    $this->artisan('arch:scan', ['--path' => [$this->scanRoot], '--baseline' => $this->baselinePath, '--update-baseline' => true, '--allow-new' => 'DECISIONS.md#2026-10-10-uji'])
        ->assertExitCode(0);
    $this->artisan('arch:scan', ['--path' => [$this->scanRoot], '--baseline' => $this->baselinePath])
        ->assertExitCode(0);

    $baseline = json_decode((string) file_get_contents(base_path($this->baselinePath)), true);
    expect($baseline['rules']['A13']['total'])->toBe(1)
        ->and($baseline['approved_additions'][0]['decision'])->toBe('DECISIONS.md#2026-10-10-uji');
});

it('demands a lower baseline once a violation is fixed', function (): void {
    writeArchScanFixtureController($this->controller, "back()->errors()->add('price', 'x')");
    $this->artisan('arch:scan', ['--path' => [$this->scanRoot], '--baseline' => $this->baselinePath, '--update-baseline' => true, '--allow-new' => 'DECISIONS.md#2026-10-10-uji']);
    writeArchScanFixtureController($this->controller, "back()->withErrors(['price' => 'x'])");

    $this->artisan('arch:scan', ['--path' => [$this->scanRoot], '--baseline' => $this->baselinePath])
        ->expectsOutputToContain('Turunkan baseline')
        ->assertExitCode(1);
    $this->artisan('arch:scan', ['--path' => [$this->scanRoot], '--baseline' => $this->baselinePath, '--update-baseline' => true])
        ->assertExitCode(0);

    $baseline = json_decode((string) file_get_contents(base_path($this->baselinePath)), true);
    expect($baseline['rules']['A13']['total'])->toBe(0);
});
