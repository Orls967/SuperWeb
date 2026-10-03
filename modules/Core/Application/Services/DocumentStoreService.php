<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Core\Contracts\DocumentStoreInterface;
use Modules\Core\Domain\Models\DocumentAttachment;

class DocumentStoreService implements DocumentStoreInterface
{
    /**
     * Dangerous executable extensions forbidden across all uploads.
     */
    protected const DISALLOWED_EXTENSIONS = [
        'php', 'phtml', 'phar', 'exe', 'bat', 'sh', 'bash', 'bin', 'cmd', 'js', 'vbs', 'jar',
    ];

    /**
     * Allowed mime types for standard business documents.
     */
    protected const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
        'image/webp',
        'text/plain',
        'text/csv',
    ];

    /**
     * Store and secure a document with checksum, validation, and retention.
     */
    public function store(
        UploadedFile|string $file,
        string $filename,
        string $documentType,
        ?Model $documentable = null,
        ?User $uploadedBy = null,
        array $metadata = [],
        ?int $retentionYears = 7
    ): DocumentAttachment {
        $filename = trim($filename);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // 1. Antivirus / Extension Guard
        if (in_array($extension, self::DISALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException("Ekstensi file .{$extension} berbahaya dan tidak diizinkan oleh sistem.");
        }

        // 2. Extract contents and mime type
        if ($file instanceof UploadedFile) {
            $content = $file->getContent();
            $mimeType = $file->getMimeType() ?: 'application/octet-stream';
            $size = $file->getSize();
        } else {
            $content = $file;
            $size = strlen($content);
            $mimeType = 'application/octet-stream';
            if ($extension === 'pdf') {
                $mimeType = 'application/pdf';
            } elseif (in_array($extension, ['jpg', 'jpeg'], true)) {
                $mimeType = 'image/jpeg';
            } elseif ($extension === 'png') {
                $mimeType = 'image/png';
            } elseif ($extension === 'csv') {
                $mimeType = 'text/csv';
            }
        }

        // 3. Compute SHA-256 Checksum
        $checksum = hash('sha256', $content);

        // 4. Determine secure storage path
        $uuid = (string) Str::uuid();
        $disk = 'local';
        $directory = 'documents/'.date('Y/m');
        $storedFilename = $uuid.($extension !== '' ? ".{$extension}" : '');
        $storedPath = "{$directory}/{$storedFilename}";

        // Write file to disk
        Storage::disk($disk)->put($storedPath, $content);

        // 5. Calculate Retention Date
        $retentionUntil = $retentionYears !== null ? now()->addYears($retentionYears)->toDateString() : null;

        // 6. Record Document in Database
        return DocumentAttachment::create([
            'uuid' => $uuid,
            'documentable_type' => $documentable ? get_class($documentable) : null,
            'documentable_id' => $documentable?->getKey(),
            'document_type' => strtoupper($documentType),
            'original_filename' => $filename,
            'stored_path' => $storedPath,
            'disk' => $disk,
            'mime_type' => $mimeType,
            'file_size_bytes' => $size,
            'checksum_sha256' => $checksum,
            'uploaded_by' => $uploadedBy?->id ?? (auth()->check() ? auth()->id() : null),
            'metadata' => $metadata,
            'retention_until' => $retentionUntil,
            'is_archived' => false,
        ]);
    }

    /**
     * Verify the integrity of a stored document via SHA-256 checksum.
     */
    public function verifyChecksum(int|object $document): bool
    {
        /** @var DocumentAttachment|null $doc */
        $doc = $document instanceof DocumentAttachment ? $document : DocumentAttachment::find($document);
        if (! $doc) {
            return false;
        }

        if (! Storage::disk($doc->disk)->exists($doc->stored_path)) {
            return false;
        }

        $content = Storage::disk($doc->disk)->get($doc->stored_path);
        $currentChecksum = hash('sha256', $content);

        return hash_equals($doc->checksum_sha256, $currentChecksum);
    }
}
