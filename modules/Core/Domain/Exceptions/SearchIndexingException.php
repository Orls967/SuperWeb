<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

use RuntimeException;

class SearchIndexingException extends RuntimeException
{
    public static function indexJobFailed(string $jobId, string $reason): self
    {
        return new self(sprintf('Pekerjaan pengindeksan [%s] gagal: %s', $jobId, $reason));
    }

    public static function documentExtractionFailed(string $documentId, string $error): self
    {
        return new self(sprintf('Gagal mengekstraksi token dari dokumen [%s]: %s', $documentId, $error));
    }
}
