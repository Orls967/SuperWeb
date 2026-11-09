<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * Aksi pengemasan bundle dokumentasi arsitektur, diagram SVG, dan katalog sistem.
 */
class ExportDocumentationBundleAction
{
    /**
     * @return array{file_count: int, total_bytes: int, manifest_hash: string}
     */
    public function generateManifest(string $docsDir = 'docs'): array
    {
        $target = base_path($docsDir);
        if (! is_dir($target)) {
            return ['file_count' => 0, 'total_bytes' => 0, 'manifest_hash' => ''];
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target));
        $files = new RegexIterator($iterator, '/^.+\.(md|svg|json)$/i');

        $fileCount = 0;
        $totalBytes = 0;
        $hashes = [];

        foreach ($files as $file) {
            $path = $file->getRealPath();
            $fileCount++;
            $size = (int) filesize($path);
            $totalBytes += $size;
            $hashes[] = hash_file('sha256', $path);
        }

        sort($hashes);
        $manifestHash = hash('sha256', implode('', $hashes));

        return [
            'file_count' => $fileCount,
            'total_bytes' => $totalBytes,
            'manifest_hash' => $manifestHash,
        ];
    }
}

