<?php

declare(strict_types=1);

use App\Quality\Support\NameReferences;

it('collects plain, aliased, grouped, function and trait imports', function (): void {
    $file = sourceFile('modules/A/X.php', <<<'PHP'
        <?php
        namespace Modules\A;
        use Modules\B\Domain\Models\Order as BOrder;
        use Modules\C\Domain\{Enums\Status, Models\Item};
        use function Modules\D\helper;
        final class X
        {
            use Modules\E\Domain\Traits\HasCode, Auditable;
            public function run(): void { $f = function () use ($x) {}; }
        }
        PHP);

    $imports = array_map(fn (array $import): string => "{$import['name']} as {$import['alias']}", NameReferences::imports($file));

    expect($imports)->toBe([
        'Modules\B\Domain\Models\Order as BOrder',
        'Modules\C\Domain\Enums\Status as Status',
        'Modules\C\Domain\Models\Item as Item',
        'Modules\D\helper as helper',
        'Modules\E\Domain\Traits\HasCode as HasCode',
        'Auditable as Auditable',
    ]);
});

it('resolves short and aliased names through imports', function (): void {
    $file = sourceFile('modules/A/X.php', "<?php\nuse Modules\\Banking\\Domain\\Enums\\TransactionType as Tx;\nuse Modules\\Hotel\\Enums;");

    expect(NameReferences::resolve($file, 'Tx'))->toBe('Modules\Banking\Domain\Enums\TransactionType')
        ->and(NameReferences::resolve($file, 'Enums\HotelTransactionType'))->toBe('Modules\Hotel\Enums\HotelTransactionType')
        ->and(NameReferences::resolve($file, '\Global\Name'))->toBe('Global\Name');
});
