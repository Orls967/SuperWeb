<?php

declare(strict_types=1);

namespace App\Quality\Gate;

use Closure;

/**
 * Default implementation of ProcessRunnerInterface executing shell commands via proc_open.
 */
final class ShellProcessRunner implements ProcessRunnerInterface
{
    /**
     * @param  array<string, string>  $env
     * @param  (Closure(string, string): void)|null  $logger
     * @return array{exit_code: int, output: string}
     */
    public function run(string $command, string $cwd, array $env = [], ?Closure $logger = null): array
    {
        $pipes = [];
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $cwd,
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

        return [
            'exit_code' => $exitCode,
            'output' => $output,
        ];
    }
}
