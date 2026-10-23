<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Console\Commands\ArchScanCommand;
use Tests\TestCase;

uses(TestCase::class);

it('ensures zero architecture scan debt across all rules A1 through A13 in baseline', function (): void {
    $root = dirname(__DIR__, 2);
    $baselinePath = $root.'/'.ArchScanCommand::DEFAULT_BASELINE;
    expect(file_exists($baselinePath))->toBeTrue("Baseline file not found at: {$baselinePath}");

    $data = json_decode((string) file_get_contents($baselinePath), true);
    expect($data)->toBeArray();
    expect($data['rules'] ?? null)->toBeArray();

    for ($i = 1; $i <= 13; $i++) {
        $ruleKey = "A{$i}";
        expect($data['rules'])->toHaveKey($ruleKey);
        expect($data['rules'][$ruleKey]['total'] ?? null)
            ->toBe(0, "Architecture debt for rule {$ruleKey} must be exactly 0, got ".($data['rules'][$ruleKey]['total'] ?? 'null'));
        expect($data['rules'][$ruleKey]['entries'] ?? null)
            ->toBe([], "Entries for rule {$ruleKey} must be empty.");
    }
});

it('ensures every module contract interface has a resolvable container binding', function (): void {
    $root = dirname(__DIR__, 2);
    $pattern = $root.'/modules/*/Contracts/*{Interface,Contract}.php';
    $files = glob($pattern, GLOB_BRACE) ?: [];

    expect($files)->not->toBeEmpty('Expected module contract interface files to exist.');

    $unbound = [];

    foreach ($files as $file) {
        $content = (string) file_get_contents($file);
        if (preg_match('/namespace\s+([^;]+);/', $content, $mNamespace)
            && preg_match('/interface\s+([A-Za-z0-9_]+)/', $content, $mInterface)) {
            $fqcn = $mNamespace[1].'\\'.$mInterface[1];

            if (! interface_exists($fqcn)) {
                continue;
            }

            $resolvable = false;
            try {
                if (app()->bound($fqcn)) {
                    $resolvable = true;
                } else {
                    $instance = app()->make($fqcn);
                    if ($instance instanceof $fqcn) {
                        $resolvable = true;
                    }
                }
            } catch (\Throwable) {
                $resolvable = false;
            }

            if (! $resolvable) {
                $unbound[] = $fqcn;
            }
        }
    }

    expect($unbound)->toBeEmpty(
        'Unbound or unresolvable contract interfaces found: '.implode(', ', $unbound)
    );
});
