<?php

declare(strict_types=1);

namespace Modules\Inventory\Domain\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException {}
