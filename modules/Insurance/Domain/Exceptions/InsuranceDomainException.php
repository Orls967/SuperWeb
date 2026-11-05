<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Exceptions;

use DomainException;

/** Dasar semua galat aturan bisnis Insurance; controller mengubahnya menjadi redirect + pesan (U-5). */
class InsuranceDomainException extends DomainException {}
