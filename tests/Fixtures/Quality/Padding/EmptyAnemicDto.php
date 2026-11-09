<?php

declare(strict_types=1);

namespace Tests\Fixtures\Quality\Padding;

/**
 * Fixture DTO pasif/anemik sengaja untuk pengujian aturan M-3 (T-10).
 * Hanya memuat properti publik tanpa metode komputasi logika bisnis.
 */
class EmptyAnemicDto
{
    public string $fieldOne;

    public string $fieldTwo;

    public string $fieldThree;

    public int $fieldFour;

    public bool $fieldFive;

    public ?array $fieldSix = null;

    public ?string $fieldSeven = null;

    public ?int $fieldEight = null;

    public ?float $fieldNine = null;

    public ?string $fieldTen = null;
}
