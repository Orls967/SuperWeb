<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

interface DocumentStoreInterface
{
    /**
     * Store and secure a document with checksum, validation, and retention.
     *
     * @param  UploadedFile|string  $file  Uploaded file instance or file content string
     * @param  string  $filename  Original or intended filename
     * @param  string  $documentType  Document category (e.g. 'CONTRACT', 'INVOICE_PDF', 'KYC_ID')
     * @param  Model|null  $documentable  Entity model this document attaches to
     * @param  User|null  $uploadedBy  User uploading the file
     * @param  array<string, mixed>  $metadata  Additional attributes
     * @param  int|null  $retentionYears  Retention period in years
     * @return object Saved Document record
     */
    public function store(
        UploadedFile|string $file,
        string $filename,
        string $documentType,
        ?Model $documentable = null,
        ?User $uploadedBy = null,
        array $metadata = [],
        ?int $retentionYears = 7
    ): object;

    /**
     * Verify the integrity of a stored document via SHA-256 checksum.
     */
    public function verifyChecksum(int|object $document): bool;
}
