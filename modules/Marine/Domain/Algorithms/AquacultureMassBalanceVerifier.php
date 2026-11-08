<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Algorithms;

use Modules\Shared\Domain\Algorithms\Marine\AquacultureMassBalanceVerifier as KernelMassBalanceVerifier;

/**
 * Domain Algorithm A15: Aquaculture Mass Balance Verifier (ASC/MSC Chain of Custody Standard).
 *
 * Meng-extend kernel algorithm dari shared layer tanpa duplikasi logika.
 */
class AquacultureMassBalanceVerifier extends KernelMassBalanceVerifier {}
