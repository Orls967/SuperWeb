<?php

declare(strict_types=1);

namespace Modules\Core\Exceptions;

use Modules\Banking\Domain\Exceptions\IdempotencyConflictException as BankingIdempotencyConflictException;

class IdempotencyConflictException extends BankingIdempotencyConflictException {}
