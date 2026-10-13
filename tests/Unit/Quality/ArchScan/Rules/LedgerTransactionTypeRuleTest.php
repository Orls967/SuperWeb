<?php

declare(strict_types=1);

use App\Quality\ArchScan\Rules\LedgerTransactionTypeRule;

enum TooLongFixtureTransactionType: string
{
    case Settlement = 'energy_ppa_net_metering_settlement';
    case Short = 'energy_ppa_settlement';
}

it('reports long, unregistered and dynamic posting types', function (): void {
    $code = <<<'PHP'
        <?php
        $a = new PostingDTO(type: 'ENERGY_PPA_NET_METERING_SETTLEMENT', description: 'x', idempotencyKey: 'k1', entries: []);
        $b = new PostingDTO('HOSP_DEPOSIT_REFUND', 'x', 'k2', []);
        $c = new PostingDTO(description: 'x', type: $type, idempotencyKey: 'k3', entries: []);
        $d = new PostingDTO(type: TooLongFixtureTransactionType::Settlement->value, description: 'x', idempotencyKey: 'k4', entries: []);
        PHP;

    $violations = (new LedgerTransactionTypeRule(['payment', 'refund']))->check(sourceFile('modules/Egy/Application/Services/X.php', $code), qualityTestRegistry());

    expect(array_map(fn ($violation) => $violation->message, $violations))->toBe([
        "Tipe 'ENERGY_PPA_NET_METERING_SETTLEMENT' 34 karakter (> 32): gagal di MySQL/PostgreSQL.",
        "Tipe 'HOSP_DEPOSIT_REFUND' tidak terdaftar di registry enum tipe transaksi.",
        'Tipe posting dinamis/tidak berasal dari enum tipe transaksi; tidak bisa diverifikasi.',
        "Nilai Settlement = 'energy_ppa_net_metering_settlement' 34 karakter (> 32).",
    ]);
});

it('accepts registered literals and enum values that fit the column', function (): void {
    $code = <<<'PHP'
        <?php
        $a = new PostingDTO(type: 'payment', description: 'x', idempotencyKey: 'k1', entries: []);
        $b = new PostingDTO(type: TooLongFixtureTransactionType::Short->value, description: 'x', idempotencyKey: 'k2', entries: []);
        $c = new PostingEntryDTO(null, 'wallet:user:1:IDR', 'IDR', 100);
        PHP;

    $signatures = ruleSignatures(new LedgerTransactionTypeRule(['payment', 'refund']), 'modules/Egy/Application/Services/X.php', $code);

    expect($signatures)->toBe([]);
});

it('defaults the registry to the Banking TransactionType enum', function (): void {
    $code = "<?php\n\$a = new PostingDTO(type: 'payment', description: 'x', idempotencyKey: 'k', entries: []);";

    $signatures = ruleSignatures(new LedgerTransactionTypeRule, 'modules/Store/Application/Services/X.php', $code);

    expect($signatures)->toBe([]);
});
