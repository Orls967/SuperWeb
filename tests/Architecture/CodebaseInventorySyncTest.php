<?php

declare(strict_types=1);

use App\Quality\Inventory\CodebaseInventoryGenerator;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/*
| PROGRESS R5.1: CodebaseInventorySyncTest (K-03, DoR Fase R5).
| Memastikan bagian faktual docs/CODEBASE.md dihasilkan secara otomatis
| dan selalu sinkron dengan kondisi nyata codebase saat ini.
*/

uses(TestCase::class);

it('ensures docs/CODEBASE.md has the automatic inventory markers', function (): void {
    $codebasePath = base_path('docs/CODEBASE.md');
    expect(file_exists($codebasePath))->toBeTrue();

    $content = (string) file_get_contents($codebasePath);
    expect($content)->toContain(CodebaseInventoryGenerator::START_MARKER)
        ->and($content)->toContain(CodebaseInventoryGenerator::END_MARKER);
});

it('ensures php artisan docs:inventory passes and verifies synchronization with CODEBASE.md', function (): void {
    $exitCode = Artisan::call('docs:inventory', ['--check' => true]);
    expect($exitCode)->toBe(0);

    $generator = app(CodebaseInventoryGenerator::class);
    expect($generator->isSynchronized(base_path('docs/CODEBASE.md')))->toBeTrue();
});

it('fails when docs/CODEBASE.md factual content differs from generated inventory', function (): void {
    $generator = app(CodebaseInventoryGenerator::class);
    $generated = $generator->generateFactualMarkdown();

    $content = (string) file_get_contents(base_path('docs/CODEBASE.md'));
    $startPos = strpos($content, CodebaseInventoryGenerator::START_MARKER);
    $endPos = strpos($content, CodebaseInventoryGenerator::END_MARKER);

    expect($startPos)->not->toBeFalse()
        ->and($endPos)->not->toBeFalse();

    $extracted = substr($content, $startPos, ($endPos + strlen(CodebaseInventoryGenerator::END_MARKER)) - $startPos);

    expect(trim($extracted))->toBe(trim($generated));
});
