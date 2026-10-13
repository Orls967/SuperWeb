<?php

declare(strict_types=1);

use App\Quality\Support\Declarations;

it('extracts parameters of methods, closures and arrow functions with their types and attributes', function (): void {
    $file = sourceFile('modules/A/X.php', <<<'PHP'
        <?php
        final class X
        {
            public function __construct(private readonly int|float $amount = 0, array $options = ['a' => [1, 2]]) {}
            public function run(#[NotAControl('alasan cukup panjang')] ?bool $flag, string ...$names): void
            {
                $fn = fn (float $value): float => $value;
                $closure = function (Model &$model) use ($fn) {};
            }
        }
        PHP);

    $parameters = array_map(
        fn (array $parameter): string => "{$parameter['function']}:".implode('|', $parameter['types'])." \${$parameter['name']}",
        Declarations::parameters($file),
    );

    expect($parameters)->toBe([
        '__construct:int|float $amount',
        '__construct:array $options',
        'run:bool $flag',
        'run:string $names',
        '{closure}:float $value',
        '{closure}:model $model',
    ])->and(Declarations::parameters($file)[2]['attributes'])->toBe("#[NotAControl('alasan cukup panjang')]");
});

it('extracts typed properties without confusing static calls, new static or static variables', function (): void {
    $file = sourceFile('modules/A/X.php', <<<'PHP'
        <?php
        final class X
        {
            public const LIMIT = 10;
            private ?float $balance = null;
            protected static int $count = 0;
            public readonly string $name;
            public function make(): static { return new static(static::LIMIT); }
            public static function create(): self { static $cache = null; return new self(); }
        }
        PHP);

    $properties = array_map(
        fn (array $property): string => implode('|', $property['types'])." \${$property['name']}",
        Declarations::properties($file),
    );

    expect($properties)->toBe(['float $balance', 'int $count', 'string $name']);
});
