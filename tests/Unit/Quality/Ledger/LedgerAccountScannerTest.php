<?php

declare(strict_types=1);

use App\Quality\Ledger\LedgerAccountScanner;

it('extracts account codes from forCode, debit, and credit calls in production files', function (): void {
    $files = [
        sourceFile('modules/Hotel/Application/Services/BookingService.php', "<?php\nPostingEntryDTO::forCode('htl:room_revenue:IDR', 'IDR', 100);"),
        sourceFile('modules/Fleet/Application/Services/FleetService.php', '<?php\nPostingEntryDTO::forCode("ar:fleet:party:{$contract->party_id}:IDR", "IDR", 100);'),
        sourceFile('modules/Resto/Application/Actions/OrderAction.php', "<?php\nPosting::lines()->debit('expense:resto:cogs:IDR', 100)->credit('revenue:resto:food:IDR', 100);"),
        sourceFile('modules/Hotel/tests/Feature/HotelTest.php', "<?php\nPostingEntryDTO::forCode('test:only:IDR', 'IDR', 100);"), // test files ignored
    ];

    $accounts = LedgerAccountScanner::referencedAccounts($files);

    expect($accounts)->toBe([
        'ar:fleet:party:*:IDR',
        'expense:resto:cogs:IDR',
        'htl:room_revenue:IDR',
        'revenue:resto:food:IDR',
    ]);
});

it('finds unseeded accounts by comparing referenced codes with seeded accounts', function (): void {
    $referenced = [
        'ar:fleet:party:*:IDR',
        'htl:room_revenue:IDR',
        'wallet:user:*:IDR',
    ];

    $seeded = [
        'htl:room_revenue:IDR',
        'wallet:user:1:IDR',
        'wallet:user:2:IDR',
    ];

    $missing = LedgerAccountScanner::unseededAccounts($referenced, $seeded);

    expect($missing)->toBe(['account:ar:fleet:party:*:IDR']);
});
