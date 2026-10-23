<?php

declare(strict_types=1);

namespace Modules\Banking\Domain\Exceptions;

use RuntimeException;

class IdempotencyConflictException extends RuntimeException
{
    public function __construct(string $key, string $expectedHash = '', string $actualHash = '')
    {
        $message = "Idempotency conflict untuk key '{$key}': payload berbeda dari transaksi sebelumnya yang sudah tercatat.";
        if ($expectedHash !== '' && $actualHash !== '') {
            $message .= " (hash tersimpan: {$expectedHash}, hash baru: {$actualHash})";
        }
        parent::__construct($message);
    }
}
