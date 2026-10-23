<?php

declare(strict_types=1);

namespace App\Quality\OpenApi;

final class OpenApiValidationResult
{
    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $stats
     */
    public function __construct(
        private readonly bool $isValid,
        private readonly array $errors = [],
        private readonly array $warnings = [],
        private readonly array $stats = [],
    ) {}

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        return $this->stats;
    }
}
