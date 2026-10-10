<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Attributes;

use Attribute;

/**
 * Marks a boolean parameter whose name looks like a control (`$approved`, `$verified`, …)
 * but only filters or formats data, so `arch:scan` rule A6 does not report it.
 * The reason is mandatory and is reviewed by the verifier (PROGRESS.md §P7 C3).
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class NotAControl
{
    public function __construct(public readonly string $reason) {}
}
