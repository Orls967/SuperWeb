<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\TaskInbox;

use DomainException;

/**
 * Service validasi keamanan lampiran dokumen pendukung tugas persetujuan (Attachment Sanitizer & Validator).
 */
class TaskAttachmentValidator
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'xlsx', 'docx', 'csv'];

    private const MAX_SIZE_BYTES = 10485760; // 10 Megabytes

    /**
     * Memvalidasi file lampiran tugas berdasarkan nama, ekstensi, dan ukuran byte.
     *
     * @param  array{filename: string, size_bytes: int, mime_type?: string}  $attachmentMeta
     *
     * @throws DomainException jika berkas melanggar kebijakan keamanan
     */
    public function validate(array $attachmentMeta): bool
    {
        $filename = $attachmentMeta['filename'] ?? '';
        $size = (int) ($attachmentMeta['size_bytes'] ?? 0);

        if (empty($filename)) {
            throw new DomainException('Nama file lampiran tidak boleh kosong.');
        }

        if ($size > self::MAX_SIZE_BYTES) {
            throw new DomainException(sprintf('Ukuran file [%s] melebihi batas maksimum 10MB.', $filename));
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new DomainException(
                sprintf('Ekstensi file [%s] tidak diizinkan. Ekstensi yang didukung: %s.', $extension, implode(', ', self::ALLOWED_EXTENSIONS))
            );
        }

        // Deteksi potensi serangan Path Traversal
        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            throw new DomainException('Terdeteksi pola path traversal pada nama file.');
        }

        return true;
    }
}
