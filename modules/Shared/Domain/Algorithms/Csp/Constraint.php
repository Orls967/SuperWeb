<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Csp;

use Closure;

final readonly class Constraint
{
    /**
     * @param  string  $var1  Nama variabel pertama
     * @param  string  $var2  Nama variabel kedua
     * @param  Closure(mixed, mixed): bool  $predicate  Predikat biner ($val1, $val2) -> bool
     * @param  string  $name  Nama / label constraint
     */
    public function __construct(
        public string $var1,
        public string $var2,
        public Closure $predicate,
        public string $name = '',
    ) {}

    public function check(mixed $val1, mixed $val2): bool
    {
        return ($this->predicate)($val1, $val2);
    }
}
