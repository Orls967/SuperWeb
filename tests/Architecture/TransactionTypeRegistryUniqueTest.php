<?php

declare(strict_types=1);

use Modules\Banking\Application\Registry\TransactionTypeRegistry;
use Modules\Shared\Contracts\TransactionTypeRegistry as RegistryInterface;

it('ensures all registered transaction type enums implement TransactionTypeRegistry', function (): void {
    $registries = TransactionTypeRegistry::registries();

    expect($registries)->not->toBeEmpty();

    foreach ($registries as $enumClass) {
        expect(enum_exists($enumClass))->toBeTrue("Enum {$enumClass} harus ada")
            ->and(is_subclass_of($enumClass, RegistryInterface::class))->toBeTrue("{$enumClass} harus mengimplementasikan TransactionTypeRegistry");
    }
});

it('ensures all transaction type values are at most 32 characters', function (): void {
    $registries = TransactionTypeRegistry::registries();

    foreach ($registries as $enumClass) {
        /** @var list<BackedEnum> $cases */
        $cases = $enumClass::cases();
        foreach ($cases as $case) {
            $val = (string) $case->value;
            expect(strlen($val))->toBeLessThanOrEqual(32, "Nilai {$enumClass}::{$case->name} ('{$val}') melebihi batas 32 karakter");
        }
    }
});

it('ensures all transaction type values are unique across all module registries', function (): void {
    $registries = TransactionTypeRegistry::registries();
    $seen = [];

    foreach ($registries as $enumClass) {
        /** @var list<BackedEnum> $cases */
        $cases = $enumClass::cases();
        foreach ($cases as $case) {
            $val = (string) $case->value;
            $prev = $seen[$val] ?? '';
            expect(isset($seen[$val]))->toBeFalse("Nilai duplikat '{$val}' ditemukan pada {$enumClass}::{$case->name} (sebelumnya di {$prev})");
            $seen[$val] = "{$enumClass}::{$case->name}";
        }
    }
});
