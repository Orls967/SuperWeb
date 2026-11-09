<?php

declare(strict_types=1);

namespace App\Quality\Algorithms;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * Algoritma A3: Class Reachability Graph & Orphan Detector (W8-D04).
 * Membangun graf berarah dependensi kelas dari analisis AST/Token
 * dan mendeteksi kelas tak terjangkau (M-5).
 */
final class ClassReachabilityGraph
{
    /** @var array<string, list<string>> Class -> Dependencies */
    private array $adjacencyList = [];

    /** @var array<string, bool> */
    private array $knownClasses = [];

    /** @var list<string> */
    private array $rootClasses = [];

    /**
     * Membangun graf dari satu atau lebih direktori
     *
     * @param  list<string>  $directories
     */
    public function buildGraph(array $directories): void
    {
        $this->adjacencyList = [];
        $this->knownClasses = [];
        $this->rootClasses = [];

        foreach ($directories as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            $phpFiles = new RegexIterator($iterator, '/^.+\.php$/i');

            foreach ($phpFiles as $file) {
                $filePath = $file->getRealPath();
                $this->parseFile($filePath);
            }
        }
    }

    private function parseFile(string $filePath): void
    {
        $source = @file_get_contents($filePath);
        if ($source === false) {
            return;
        }

        $tokens = @token_get_all($source);
        $namespace = '';
        $className = null;
        $dependencies = [];

        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token)) {
                $id = $token[0];

                if ($id === T_NAMESPACE) {
                    $nsParts = [];
                    for ($j = $i + 1; $j < $count; $j++) {
                        if (is_array($tokens[$j]) && ($tokens[$j][0] === T_STRING || $tokens[$j][0] === T_NAME_QUALIFIED)) {
                            $nsParts[] = $tokens[$j][1];
                        } elseif ($tokens[$j] === ';') {
                            break;
                        }
                    }
                    $namespace = implode('\\', $nsParts);
                } elseif ($id === T_CLASS && $className === null) {
                    // Cek apakah didahului oleh :: (contoh Foo::class) atau new (anonymous class)
                    $isClassConstant = false;
                    $isAnonymous = false;
                    for ($k = $i - 1; $k >= 0; $k--) {
                        if (is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) {
                            continue;
                        }
                        if (is_array($tokens[$k]) && $tokens[$k][0] === T_DOUBLE_COLON) {
                            $isClassConstant = true;
                        }
                        if (is_array($tokens[$k]) && $tokens[$k][0] === T_NEW) {
                            $isAnonymous = true;
                        }
                        break;
                    }
                    if ($isClassConstant || $isAnonymous) {
                        continue;
                    }

                    // Cari nama kelas
                    for ($j = $i + 1; $j < $count; $j++) {
                        if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                            $className = $tokens[$j][1];
                            break;
                        }
                    }
                } elseif ($id === T_USE) {
                    // Ambil imported class
                    $useParts = [];
                    for ($j = $i + 1; $j < $count; $j++) {
                        if (is_array($tokens[$j]) && ($tokens[$j][0] === T_STRING || $tokens[$j][0] === T_NAME_QUALIFIED)) {
                            $useParts[] = $tokens[$j][1];
                        } elseif ($tokens[$j] === ';' || $tokens[$j] === '{' || $tokens[$j] === ',') {
                            break;
                        }
                    }
                    if (! empty($useParts)) {
                        $dependencies[] = implode('\\', $useParts);
                    }
                } elseif ($id === T_NAME_QUALIFIED || $id === T_NAME_FULLY_QUALIFIED) {
                    $dependencies[] = ltrim($token[1], '\\');
                }
            }
        }

        if ($className !== null) {
            $fqcn = $namespace !== '' ? "{$namespace}\\{$className}" : $className;
            $this->knownClasses[$fqcn] = true;
            $this->adjacencyList[$fqcn] = array_unique($dependencies);

            // Klasifikasikan root: Controller, Command, Listener, ServiceProvider, Policy, Request, Test
            if (
                str_ends_with($fqcn, 'Controller')
                || str_ends_with($fqcn, 'Command')
                || str_ends_with($fqcn, 'ServiceProvider')
                || str_ends_with($fqcn, 'Listener')
                || str_ends_with($fqcn, 'Subscriber')
                || str_ends_with($fqcn, 'Policy')
                || str_ends_with($fqcn, 'Request')
                || str_ends_with($fqcn, 'Middleware')
                || str_ends_with($fqcn, 'Seeder')
                || str_ends_with($fqcn, 'Test')
            ) {
                $this->rootClasses[] = $fqcn;
            }
        }
    }

    /**
     * Mendeteksi kelas yang tidak terjangkau (orphan) dari root classes
     *
     * @return list<string>
     */
    public function detectOrphans(): array
    {
        if (empty($this->rootClasses)) {
            // Jika tidak ada root eksplisit di subset direktori,
            // kelas yang tidak direferensikan oleh kelas mana pun di graf adalah orphan
            $referenced = [];
            foreach ($this->adjacencyList as $deps) {
                foreach ($deps as $dep) {
                    $referenced[$dep] = true;
                    foreach (array_keys($this->knownClasses) as $known) {
                        if ($dep === $known || str_ends_with($known, '\\'.$dep)) {
                            $referenced[$known] = true;
                        }
                    }
                }
            }

            $orphans = [];
            foreach (array_keys($this->knownClasses) as $class) {
                if (! isset($referenced[$class])) {
                    $orphans[] = $class;
                }
            }

            return $orphans;
        }

        $visited = [];
        $queue = [];

        // Inisialisasi BFS dari seluruh root classes
        foreach ($this->rootClasses as $root) {
            $queue[] = $root;
            $visited[$root] = true;
        }

        while (! empty($queue)) {
            $current = array_shift($queue);
            $deps = $this->adjacencyList[$current] ?? [];

            foreach ($deps as $dep) {
                // Normalisasi dep jika match dengan known class
                foreach (array_keys($this->knownClasses) as $known) {
                    if ($dep === $known || str_ends_with($known, '\\'.$dep)) {
                        if (! isset($visited[$known])) {
                            $visited[$known] = true;
                            $queue[] = $known;
                        }
                    }
                }
            }
        }

        $orphans = [];
        foreach (array_keys($this->knownClasses) as $class) {
            if (! isset($visited[$class])) {
                $orphans[] = $class;
            }
        }

        return $orphans;
    }

    public function generateReport(): ReachabilityReport
    {
        $orphans = $this->detectOrphans();
        $reachable = [];
        foreach (array_keys($this->knownClasses) as $c) {
            if (! in_array($c, $orphans, true)) {
                $reachable[] = $c;
            }
        }

        $edgeCount = 0;
        foreach ($this->adjacencyList as $deps) {
            $edgeCount += count($deps);
        }

        $cert = hash('sha256', json_encode([
            'nodes' => count($this->knownClasses),
            'edges' => $edgeCount,
            'orphans' => $orphans,
        ]));

        return new ReachabilityReport(
            reachableClasses: $reachable,
            orphanClasses: $orphans,
            adjacencyList: $this->adjacencyList,
            totalNodes: count($this->knownClasses),
            totalEdges: $edgeCount,
            certificate: $cert,
        );
    }
}
