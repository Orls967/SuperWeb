<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\DesignSystem;

use Illuminate\Support\Facades\File;
use Modules\Core\Domain\Models\DsComponent;
use Modules\Core\Domain\Models\DsComponentHistory;
use RuntimeException;

/**
 * Rekonsiliator audit dua sumber: Integritas State Machine database vs status file komponen di disk.
 */
class DsDualSourceReconciler
{
    /**
     * Direktori dasar komponen kanonikal Blade.
     */
    protected string $componentsPath;

    public function __construct(?string $componentsPath = null)
    {
        $this->componentsPath = $componentsPath ?? base_path('modules/Shared/resources/views/components/ds');
    }

    /**
     * Jalankan rekonsiliasi komprehensif antara database dan file fisik.
     *
     * @return array{reconciled: bool, checked_count: int, matched_count: int, discrepancies: array<int, array<string, mixed>>, integrity_hash: string}
     */
    public function reconcile(): array
    {
        $components = DsComponent::all();
        $discrepancies = [];
        $checkedCount = 0;
        $matchedCount = 0;

        $fingerprintPayload = [];

        foreach ($components as $component) {
            $checkedCount++;
            $componentName = $component->name;
            $filePath = $this->componentsPath . DIRECTORY_SEPARATOR . $componentName . '.blade.php';

            // 1. Periksa keberadaan file fisik di disk
            if (!File::exists($filePath)) {
                $discrepancies[] = [
                    'component' => $componentName,
                    'type' => 'FILE_MISSING_ON_DISK',
                    'message' => "File Blade untuk komponen [{$componentName}] tidak ditemukan di {$filePath}.",
                    'db_status' => $component->status,
                    'file_status' => 'missing',
                    'severity' => 'critical',
                ];
                continue;
            }

            // 2. Hitung hash isi file fisik
            $content = (string) File::get($filePath);
            $fileHash = hash('sha256', $content);
            $fileSize = strlen($content);

            // 3. Verifikasi riwayat transisi terakhir di DB
            $latestHistory = DsComponentHistory::where('component_name', $componentName)
                ->orderByDesc('id')
                ->first();

            if ($latestHistory !== null && $latestHistory->to_status !== $component->status) {
                $discrepancies[] = [
                    'component' => $componentName,
                    'type' => 'STATE_MACHINE_DESYNC',
                    'message' => "Status di tabel core_ds_components ({$component->status}) tidak sama dengan riwayat transisi terakhir ({$latestHistory->to_status}).",
                    'db_status' => $component->status,
                    'history_status' => $latestHistory->to_status,
                    'severity' => 'critical',
                ];
                continue;
            }

            // 4. Verifikasi komponen status rilis (released/lts)
            if (in_array($component->status, ['released', 'lts'], true)) {
                if ($fileSize < 50) {
                    $discrepancies[] = [
                        'component' => $componentName,
                        'type' => 'EMPTY_OR_STUB_RELEASED_COMPONENT',
                        'message' => "Komponen [{$componentName}] berstatus {$component->status} namun ukuran file hanya {$fileSize} byte.",
                        'db_status' => $component->status,
                        'file_size' => $fileSize,
                        'severity' => 'high',
                    ];
                    continue;
                }
            }

            // 5. Verifikasi komponen deprecated
            if ($component->status === 'deprecated' && !str_contains($content, 'deprecated')) {
                $discrepancies[] = [
                    'component' => $componentName,
                    'type' => 'DEPRECATION_NOTICE_MISSING',
                    'message' => "Komponen [{$componentName}] berstatus deprecated tetapi file tidak memuat peringatan deprecation.",
                    'db_status' => $component->status,
                    'severity' => 'medium',
                ];
            }

            $matchedCount++;
            $fingerprintPayload[$componentName] = [
                'status' => $component->status,
                'version' => $component->version,
                'hash' => $fileHash,
            ];
        }

        // Hitung SHA-256 seal integritas seluruh katalog
        ksort($fingerprintPayload);
        $integrityHash = hash('sha256', json_encode($fingerprintPayload, JSON_THROW_ON_ERROR));

        return [
            'reconciled' => count($discrepancies) === 0,
            'checked_count' => $checkedCount,
            'matched_count' => $matchedCount,
            'discrepancies' => $discrepancies,
            'integrity_hash' => $integrityHash,
        ];
    }

    /**
     * Deteksi komponen yatim di disk yang belum terdaftar di tabel database.
     *
     * @return array<int, string> Daftar path file yang belum terdaftar
     */
    public function detectOrphanBladeFiles(): array
    {
        if (!File::isDirectory($this->componentsPath)) {
            return [];
        }

        $files = File::files($this->componentsPath);
        $registeredNames = DsComponent::pluck('name')->toArray();
        $orphans = [];

        foreach ($files as $file) {
            $filename = $file->getFilename();
            if (!str_ends_with($filename, '.blade.php')) {
                continue;
            }

            $componentName = substr($filename, 0, -10);
            if (!in_array($componentName, $registeredNames, true)) {
                $orphans[] = $componentName;
            }
        }

        return $orphans;
    }
}
