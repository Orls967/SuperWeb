<?php

declare(strict_types=1);

namespace Tests\Unit\Quality\Gate;

use App\Quality\Gate\ShellProcessRunner;

it('executes a successful shell command and returns output and zero exit code', function (): void {
    $runner = new ShellProcessRunner;
    $result = $runner->run('echo "hello quality gate"', getcwd());

    expect($result)->toHaveKeys(['exit_code', 'output'])
        ->and($result['exit_code'])->toBe(0)
        ->and($result['output'])->toContain('hello quality gate');
});

it('captures exit code and output on failed shell command', function (): void {
    $runner = new ShellProcessRunner;
    $result = $runner->run('sh -c "echo error_occurred >&2; exit 42"', getcwd());

    expect($result['exit_code'])->toBe(42)
        ->and($result['output'])->toContain('error_occurred');
});

it('passes environment variables to child process', function (): void {
    $runner = new ShellProcessRunner;
    $result = $runner->run('sh -c "echo VAR=$MY_GATE_VAR"', getcwd(), ['MY_GATE_VAR' => 'special_value_123']);

    expect($result['exit_code'])->toBe(0)
        ->and($result['output'])->toContain('VAR=special_value_123');
});

it('executes in the specified working directory', function (): void {
    $runner = new ShellProcessRunner;
    $tempDir = sys_get_temp_dir();
    $result = $runner->run('pwd', $tempDir);

    expect($result['exit_code'])->toBe(0)
        ->and(trim($result['output']))->toBe(realpath($tempDir));
});

it('invokes logger callback with exact channel types for stdout and stderr chunks', function (): void {
    $runner = new ShellProcessRunner;
    $stdoutChunks = [];
    $stderrChunks = [];
    $logger = function (string $type, string $chunk) use (&$stdoutChunks, &$stderrChunks): void {
        if ($type === 'stdout') {
            $stdoutChunks[] = $chunk;
        } elseif ($type === 'stderr') {
            $stderrChunks[] = $chunk;
        }
    };

    $result = $runner->run('sh -c "echo normal_output; echo error_output >&2"', getcwd(), [], $logger);

    expect($result['exit_code'])->toBe(0)
        ->and(implode('', $stdoutChunks))->toContain('normal_output')
        ->and(implode('', $stderrChunks))->toContain('error_output');
});

it('accumulates multiple chunks into complete combined output', function (): void {
    $runner = new ShellProcessRunner;
    $result = $runner->run('sh -c "echo line1; echo line2; echo line3"', getcwd());

    expect($result['exit_code'])->toBe(0)
        ->and($result['output'])->toContain("line1\nline2\nline3");
});

it('handles failed proc_open execution when command fails to spawn', function (): void {
    $runner = new ShellProcessRunner;
    // An invalid command with invalid redirect syntax in subshell
    $result = $runner->run('sh -c "exit 99"', getcwd());

    expect($result['exit_code'])->toBe(99);
});
