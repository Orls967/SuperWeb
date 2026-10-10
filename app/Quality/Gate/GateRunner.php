<?php

declare(strict_types=1);

namespace App\Quality\Gate;

use Closure;
use Illuminate\Support\Facades\File;

/**
 * Executes the full quality gate pipeline with genuine metric capture (PROGRESS R0.2, P4).
 */
final class GateRunner
{
    /**
     * The 9 mandatory gate steps and their exact commands.
     * Flag --dirty is strictly prohibited.
     */
    public const MANDATORY_STEPS = [
        [
            'name' => 'composer validate --strict',
            'command' => 'composer validate --strict',
        ],
        [
            'name' => 'pest',
            'command' => 'vendor/bin/pest --parallel --log-junit=storage/logs/pest-junit.xml',
        ],
        [
            'name' => 'pint --test',
            'command' => 'vendor/bin/pint --test',
        ],
        [
            'name' => 'arch:scan --json',
            'command' => 'php artisan arch:scan --json',
        ],
        [
            'name' => 'migrate:fresh --seed',
            'command' => 'php artisan migrate:fresh --seed --force',
        ],
        [
            'name' => 'bank:reconcile',
            'command' => 'php artisan bank:reconcile',
        ],
        [
            'name' => 'chain:audit-all',
            'command' => 'php artisan chain:audit-all',
        ],
        [
            'name' => 'super:health-check',
            'command' => 'php artisan super:health-check',
        ],
        [
            'name' => 'npm run build',
            'command' => 'npm run build',
        ],
    ];

    public function __construct(
        private readonly ?string $repoRoot = null,
    ) {}

    /**
     * Prepares isolated clean SQLite database storage/gate/gate.sqlite for gate runs (V7).
     */
    public function prepareGateDatabase(string $root): string
    {
        $gateDir = $root.'/storage/gate';
        File::ensureDirectoryExists($gateDir);
        $gateDb = $gateDir.'/gate.sqlite';
        if (File::exists($gateDb)) {
            File::delete($gateDb);
        }
        touch($gateDb);

        return $gateDb;
    }

    /**
     * Executes all mandatory gate steps, writes storage/logs/gate-manifest.json,
     * and returns the overall exit code (0 on success, >0 on failure).
     *
     * @param  (Closure(string, string): void)|null  $logger  Optional callback: fn(string $type, string $buffer)
     */
    public function run(?string $manifestPath = null, ?Closure $logger = null): int
    {
        $root = $this->repoRoot ?? (function_exists('app') && app()->has('path.base') ? base_path() : dirname(__DIR__, 3));
        $headCommit = $this->resolveCommitHash($root);
        $isDirty = $this->resolveIsDirty($root);
        $timestamp = date('c');

        $gateDb = $this->prepareGateDatabase($root);

        $executedSteps = [];
        $overallStatus = 'PASS';
        $finalExitCode = 0;

        foreach (self::MANDATORY_STEPS as $stepDef) {
            $name = $stepDef['name'];
            $cmd = $stepDef['command'];

            if ($logger !== null) {
                $logger('info', "==> [Gate Step] {$name} ({$cmd})");
            }

            $start = microtime(true);
            $pipes = [];
            $env = [];
            foreach ($_SERVER as $k => $v) {
                if (is_scalar($v)) {
                    $env[$k] = (string) $v;
                }
            }
            if ($name === 'pest') {
                $env['APP_ENV'] = 'testing';
                $env['DB_CONNECTION'] = 'sqlite';
                $env['DB_DATABASE'] = ':memory:';
                $env['BCRYPT_ROUNDS'] = '4';
            } elseif (in_array($name, ['migrate:fresh --seed', 'bank:reconcile', 'chain:audit-all', 'super:health-check'], true)) {
                $env['DB_CONNECTION'] = 'sqlite';
                $env['DB_DATABASE'] = $gateDb;
            }
            $process = proc_open(
                $cmd,
                [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ],
                $pipes,
                $root,
                $env
            );

            $output = '';
            if (is_resource($process)) {
                fclose($pipes[0]);
                stream_set_blocking($pipes[1], false);
                stream_set_blocking($pipes[2], false);

                while (! feof($pipes[1]) || ! feof($pipes[2])) {
                    $read = [];
                    if (! feof($pipes[1])) {
                        $read[] = $pipes[1];
                    }
                    if (! feof($pipes[2])) {
                        $read[] = $pipes[2];
                    }

                    if ($read === []) {
                        break;
                    }

                    $write = null;
                    $except = null;
                    $ready = @stream_select($read, $write, $except, 0, 200000);

                    if ($ready === false) {
                        break;
                    }

                    if ($ready > 0) {
                        foreach ($read as $pipe) {
                            $chunk = fread($pipe, 8192);
                            if ($chunk !== false && $chunk !== '') {
                                $output .= $chunk;
                                if ($logger !== null) {
                                    $logger($pipe === $pipes[1] ? 'stdout' : 'stderr', $chunk);
                                }
                            }
                        }
                    } else {
                        $pstatus = proc_get_status($process);
                        if (! ($pstatus['running'] ?? true)) {
                            while (($chunk = fread($pipes[1], 8192)) !== false && $chunk !== '') {
                                $output .= $chunk;
                                if ($logger !== null) {
                                    $logger('stdout', $chunk);
                                }
                            }
                            while (($chunk = fread($pipes[2], 8192)) !== false && $chunk !== '') {
                                $output .= $chunk;
                                if ($logger !== null) {
                                    $logger('stderr', $chunk);
                                }
                            }
                            break;
                        }
                    }
                }
                fclose($pipes[1]);
                fclose($pipes[2]);
                $exitCode = proc_close($process);
            } else {
                $exitCode = 1;
            }

            $duration = microtime(true) - $start;
            $stepStatus = $exitCode === 0 ? 'PASS' : 'FAIL';

            if ($name === 'arch:scan --json' && $exitCode === 0) {
                File::ensureDirectoryExists($root.'/storage/logs');
                File::put($root.'/storage/logs/arch-scan.json', $output);
            }

            $executedSteps[] = [
                'name' => $name,
                'command' => $cmd,
                'exit_code' => $exitCode,
                'duration' => round($duration, 3),
                'status' => $stepStatus,
            ];

            if ($exitCode !== 0) {
                $overallStatus = 'FAIL';
                $finalExitCode = $exitCode;
                if ($logger !== null) {
                    $logger('error', "--> Step '{$name}' FAILED with exit code {$exitCode}.");
                }
                break;
            }
        }

        $manifest = new GateManifest(
            commit: $headCommit,
            isDirty: $isDirty,
            timestamp: $timestamp,
            overallStatus: $overallStatus,
            steps: $executedSteps,
        );

        $dir = dirname($manifestFile);
        if (! is_dir($dir)) {
            File::makeDirectory($dir, 0777, true, true);
        }

        file_put_contents(
            $manifestFile,
            json_encode($manifest->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return $finalExitCode;
    }

    private function resolveCommitHash(string $root): string
    {
        $hash = shell_exec('git -C '.escapeshellarg($root).' rev-parse HEAD 2>/dev/null');
        if ($hash !== null && trim($hash) !== '') {
            return trim($hash);
        }

        return '0000000000000000000000000000000000000000';
    }

    private function resolveIsDirty(string $root): bool
    {
        $status = shell_exec('git -C '.escapeshellarg($root).' status --porcelain 2>/dev/null');
        if ($status === null || trim($status) === '') {
            return false;
        }

        $lines = array_filter(
            explode("\n", trim($status)),
            function (string $line): bool {
                $file = trim(substr($line, 3));
                if ($file === '') {
                    return false;
                }
                if (str_starts_with($file, 'storage/gate/') || str_starts_with($file, 'storage/logs/')) {
                    return false;
                }
                if ($file === 'database/database.sqlite' || str_ends_with($file, '.sqlite') || str_contains($file, '.sqlite-')) {
                    return false;
                }

                return true;
            }
        );

        return $lines !== [];
    }
}
