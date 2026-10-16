<?php

declare(strict_types=1);

namespace App\Quality\Inventory;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/**
 * Generator bagian faktual docs/CODEBASE.md (K-03, PROGRESS R5.1).
 * Menghasilkan: modul, prefiks, jumlah tabel/migrasi, rute + middleware, command & jadwal, serta akun ledger.
 */
final class CodebaseInventoryGenerator
{
    public const START_MARKER = '<!-- FACTUAL_INVENTORY:START -->';

    public const END_MARKER = '<!-- FACTUAL_INVENTORY:END -->';

    public function __construct(
        private readonly ?string $basePath = null
    ) {}

    public function getBasePath(): string
    {
        if ($this->basePath !== null) {
            return $this->basePath;
        }

        if (function_exists('base_path') && app() instanceof Application) {
            return base_path();
        }

        return dirname(__DIR__, 3);
    }

    /**
     * Menghasilkan teks markdown faktual lengkap yang diapit START_MARKER dan END_MARKER.
     */
    public function generateFactualMarkdown(): string
    {
        $modulesSection = $this->generateModulesSection();
        $routesSection = $this->generateRoutesSection();
        $commandsSection = $this->generateCommandsSection();
        $ledgerSection = $this->generateLedgerSection();

        $content = implode("\n\n", [
            self::START_MARKER,
            '## 6. Inventaris Faktual Otomatis (docs:inventory)',
            '> Dokumen ini dihasilkan secara otomatis oleh `php artisan docs:inventory` sesuai invarian K-03 (Fase R5.1). Seluruh perubahan modul, rute, command, jadwal, dan akun ledger harus disinkronkan melalui command tersebut.',
            $modulesSection,
            $routesSection,
            $commandsSection,
            $ledgerSection,
            self::END_MARKER,
        ]);

        return $content;
    }

    /**
     * Menulis atau memperbarui bagian faktual pada docs/CODEBASE.md.
     */
    public function writeToCodebase(?string $path = null): bool
    {
        $filePath = $path ?? ($this->getBasePath().'/docs/CODEBASE.md');
        if (! file_exists($filePath)) {
            return false;
        }

        $currentContent = (string) file_get_contents($filePath);
        $newInventory = $this->generateFactualMarkdown();

        if (str_contains($currentContent, self::START_MARKER) && str_contains($currentContent, self::END_MARKER)) {
            $pattern = '/'.preg_quote(self::START_MARKER, '/').'.*?'.preg_quote(self::END_MARKER, '/').'/s';
            $updatedContent = (string) preg_replace($pattern, $newInventory, $currentContent);
        } else {
            $updatedContent = rtrim($currentContent)."\n\n".$newInventory."\n";
        }

        return file_put_contents($filePath, $updatedContent) !== false;
    }

    /**
     * Memeriksa apakah docs/CODEBASE.md sudah identik dengan output generator.
     */
    public function isSynchronized(?string $path = null): bool
    {
        $filePath = $path ?? ($this->getBasePath().'/docs/CODEBASE.md');
        if (! file_exists($filePath)) {
            return false;
        }

        $currentContent = (string) file_get_contents($filePath);
        if (! str_contains($currentContent, self::START_MARKER) || ! str_contains($currentContent, self::END_MARKER)) {
            return false;
        }

        $startPos = strpos($currentContent, self::START_MARKER);
        $endPos = strpos($currentContent, self::END_MARKER);

        if ($startPos === false || $endPos === false || $endPos < $startPos) {
            return false;
        }

        $existingInventory = substr($currentContent, $startPos, ($endPos + strlen(self::END_MARKER)) - $startPos);
        $expectedInventory = $this->generateFactualMarkdown();

        return trim($existingInventory) === trim($expectedInventory);
    }

    /**
     * 1. Modul, prefiks tabel resmi, dan jumlah file migrasi.
     */
    private function generateModulesSection(): string
    {
        $modulesPath = $this->getBasePath().'/modules';
        $prefixesConfig = (array) config('modules.table_prefixes', []);

        /** @var array<string, list<string>> $ownerToPrefixes */
        $ownerToPrefixes = [];
        foreach ($prefixesConfig as $prefix => $owner) {
            $ownerToPrefixes[$owner][] = (string) $prefix;
        }

        $moduleDirs = is_dir($modulesPath) ? (scandir($modulesPath) ?: []) : [];
        $modules = [];

        foreach ($moduleDirs as $dir) {
            if ($dir === '.' || $dir === '..' || ! is_dir($modulesPath.'/'.$dir)) {
                continue;
            }

            $modName = $dir;
            $prefixes = $ownerToPrefixes[$modName] ?? [];
            sort($prefixes);
            $prefixStr = empty($prefixes) ? '—' : '`'.implode('`, `', $prefixes).'`';

            $migrationsDir = $modulesPath.'/'.$dir.'/database/migrations';
            $migrationCount = 0;
            if (is_dir($migrationsDir)) {
                $files = scandir($migrationsDir) ?: [];
                foreach ($files as $file) {
                    if (str_ends_with($file, '.php')) {
                        $migrationCount++;
                    }
                }
            }

            $modules[] = [
                'name' => $modName,
                'prefix' => $prefixStr,
                'migrations' => $migrationCount,
            ];
        }

        usort($modules, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        $lines = [
            '### 6.1 Peta Modul, Prefiks Tabel & Jumlah Migrasi',
            '| No | Modul | Prefiks Tabel Resmi (`config/modules.php`) | Jumlah Migrasi |',
            '|---|---|---|---|',
        ];

        $totalMigrations = 0;
        foreach ($modules as $index => $mod) {
            $num = $index + 1;
            $totalMigrations += $mod['migrations'];
            $lines[] = "| {$num} | **{$mod['name']}** | {$mod['prefix']} | {$mod['migrations']} |";
        }

        $totalModules = count($modules);
        $lines[] = "| | **Total: {$totalModules} Modul** | | **{$totalMigrations} Migrasi** |";

        return implode("\n", $lines);
    }

    /**
     * 2. Rute per modul + middleware & role.
     */
    private function generateRoutesSection(): string
    {
        $routes = Route::getRoutes()->getRoutes();

        /** @var array<string, list<array{method: string, uri: string, name: string, action: string, middleware: string}>> $routesByModule */
        $routesByModule = [];

        foreach ($routes as $route) {
            $uri = $route->uri();
            // Filter rute internal tooling / dev
            if (str_starts_with($uri, '_') || $uri === 'sanctum/csrf-cookie') {
                continue;
            }

            $methods = array_values(array_diff($route->methods(), ['HEAD']));
            $methodStr = implode('|', $methods);
            $action = $route->getActionName();
            $name = $route->getName() ?: '-';

            $middlewareList = $route->gatherMiddleware();
            $cleanMiddleware = array_map(static function ($m): string {
                if (is_string($m)) {
                    $parts = explode('\\', $m);

                    return end($parts);
                }

                return 'Closure';
            }, $middlewareList);
            $middlewareStr = implode(', ', $cleanMiddleware) ?: 'none';

            // Tentukan modul pemilik
            $module = 'App';
            if (preg_match('/^Modules\\\\([^\\\\]+)/', $action, $m)) {
                $module = $m[1];
            } elseif (preg_match('/^modules\/([^\/]+)/', $uri, $m)) {
                $module = ucfirst($m[1]);
            }

            $routesByModule[$module][] = [
                'method' => $methodStr,
                'uri' => $uri,
                'name' => $name,
                'action' => $action,
                'middleware' => $middlewareStr,
            ];
        }

        ksort($routesByModule);

        $lines = [
            '### 6.2 Ringkasan Rute per Modul & Middleware',
            '| Modul | Jumlah Rute | Sampel Rute & URI | Middleware Utama |',
            '|---|---|---|---|',
        ];

        $totalRoutes = 0;
        foreach ($routesByModule as $modName => $modRoutes) {
            $count = count($modRoutes);
            $totalRoutes += $count;

            $sampleUris = array_slice(array_unique(array_column($modRoutes, 'uri')), 0, 3);
            $sampleStr = '`'.implode('`, `', $sampleUris).'`'.(count($sampleUris) < count($modRoutes) ? ' ...' : '');

            $allMw = [];
            foreach ($modRoutes as $r) {
                foreach (explode(', ', $r['middleware']) as $mw) {
                    if ($mw !== 'none') {
                        $allMw[$mw] = true;
                    }
                }
            }
            $mwList = array_slice(array_keys($allMw), 0, 3);
            $mwStr = empty($mwList) ? 'none' : implode(', ', $mwList);

            $lines[] = "| **{$modName}** | {$count} | {$sampleStr} | {$mwStr} |";
        }

        $lines[] = "| **Total** | **{$totalRoutes} Rute** | | |";

        return implode("\n", $lines);
    }

    /**
     * 3. Artisan commands & jadwal eksekusi dari routes/console.php.
     */
    private function generateCommandsSection(): string
    {
        // 1. Ambil jadwal dari routes/console.php
        $consolePath = $this->getBasePath().'/routes/console.php';
        $schedules = [];
        if (file_exists($consolePath)) {
            $lines = file($consolePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (preg_match('/Schedule::command\([\'"]([^\'"]+)[\'"]\)(.*);/', $trimmed, $matches)) {
                    $cmdName = $matches[1];
                    $frequency = trim(trim($matches[2], '->'));
                    $schedules[$cmdName] = $frequency ?: 'terjadwal';
                }
            }
        }

        // 2. Ambil seluruh application command
        $allCommands = Artisan::all();
        $appCommands = [];

        foreach ($allCommands as $name => $cmd) {
            // Filter command internal vendor / framework
            if (
                str_starts_with($name, '_') ||
                str_starts_with($name, 'boost:') ||
                $name === 'pail' ||
                str_starts_with($name, 'breeze:') ||
                str_starts_with($name, 'cache:') ||
                str_starts_with($name, 'model:') ||
                str_starts_with($name, 'notifications:') ||
                str_starts_with($name, 'make:') ||
                str_starts_with($name, 'migrate:') ||
                str_starts_with($name, 'config:') ||
                str_starts_with($name, 'route:') ||
                str_starts_with($name, 'view:') ||
                str_starts_with($name, 'db:') ||
                str_starts_with($name, 'optimize') ||
                str_starts_with($name, 'package:') ||
                str_starts_with($name, 'vendor:') ||
                str_starts_with($name, 'key:') ||
                str_starts_with($name, 'env:') ||
                str_starts_with($name, 'storage:') ||
                str_starts_with($name, 'stub:') ||
                str_starts_with($name, 'pest:') ||
                str_starts_with($name, 'queue:') ||
                str_starts_with($name, 'event:') ||
                str_starts_with($name, 'schedule:') ||
                str_starts_with($name, 'auth:') ||
                str_starts_with($name, 'channel:') ||
                str_starts_with($name, 'about') ||
                str_starts_with($name, 'clear-compiled') ||
                str_starts_with($name, 'completion') ||
                str_starts_with($name, 'help') ||
                str_starts_with($name, 'list')
            ) {
                continue;
            }

            $description = method_exists($cmd, 'getDescription') ? $cmd->getDescription() : '';
            $schedule = $schedules[$name] ?? 'manual';

            $appCommands[$name] = [
                'name' => $name,
                'description' => $description ?: 'Perintah aplikasi/modul',
                'schedule' => $schedule,
            ];
        }

        ksort($appCommands);

        $lines = [
            '### 6.3 Artisan Commands & Jadwal Eksekusi',
            '| Command | Jadwal (`routes/console.php`) | Deskripsi |',
            '|---|---|---|',
        ];

        foreach ($appCommands as $cmd) {
            $schedBadge = $cmd['schedule'] === 'manual' ? '`manual`' : "**`{$cmd['schedule']}`**";
            $desc = str_replace('|', '/', $cmd['description']);
            $lines[] = "| `{$cmd['name']}` | {$schedBadge} | {$desc} |";
        }

        $totalCmd = count($appCommands);
        $totalSched = count(array_filter($appCommands, static fn ($c): bool => $c['schedule'] !== 'manual'));
        $lines[] = "| **Total: {$totalCmd} Command** | **{$totalSched} Terjadwal** | |";

        return implode("\n", $lines);
    }

    /**
     * 4. Akun ledger per modul dari LedgerAccountsSeeder.
     */
    private function generateLedgerSection(): string
    {
        $registries = LedgerAccountsSeeder::ACCOUNT_REGISTRIES;

        $lines = [
            '### 6.4 Bagan Akun Standar (Ledger) per Modul',
            '| Modul | Kelas Registry | Jumlah Akun | Contoh Kode Akun |',
            '|---|---|---|---|',
        ];

        $totalAccounts = 0;
        $registrySummaries = [];

        foreach ($registries as $registryClass) {
            $parts = explode('\\', $registryClass);
            $className = end($parts);
            $moduleName = $parts[1] ?? 'Unknown';

            /** @var list<array{code: string, name: string, kind: mixed}> $definitions */
            $definitions = method_exists($registryClass, 'definitions') ? $registryClass::definitions() : [];
            $count = count($definitions);
            $totalAccounts += $count;

            $sampleCodes = array_slice(array_column($definitions, 'code'), 0, 2);
            $sampleStr = empty($sampleCodes) ? '—' : '`'.implode('`, `', $sampleCodes).'`'.($count > 2 ? ' ...' : '');

            $registrySummaries[] = [
                'module' => $moduleName,
                'class' => $className,
                'count' => $count,
                'sample' => $sampleStr,
            ];
        }

        usort($registrySummaries, static fn (array $a, array $b): int => strcmp($a['module'], $b['module']));

        foreach ($registrySummaries as $reg) {
            $lines[] = "| **{$reg['module']}** | `{$reg['class']}` | {$reg['count']} | {$reg['sample']} |";
        }

        $totalRegs = count($registrySummaries);
        $lines[] = "| **Total: {$totalRegs} Registry** | | **{$totalAccounts} Akun** | |";

        return implode("\n", $lines);
    }
}
