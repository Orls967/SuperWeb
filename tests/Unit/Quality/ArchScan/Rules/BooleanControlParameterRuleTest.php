<?php

declare(strict_types=1);

use App\Quality\ArchScan\Rules\BooleanControlParameterRule;

it('reports boolean controls trusted from the caller in the application layer', function (): void {
    $code = <<<'PHP'
        <?php
        final class PayoutService
        {
            public function release(int $payoutId, bool $approved, ?bool $kycVerified = null, bool $consentGranted = false): void {}
        }
        PHP;

    $signatures = ruleSignatures(new BooleanControlParameterRule, 'modules/Agri/Application/Services/PayoutService.php', $code);

    expect($signatures)->toBe([
        'bool $approved @ release()',
        'bool $kycVerified @ release()',
        'bool $consentGranted @ release()',
    ]);
});

it('does not report display filters, other types, or code outside Application', function (): void {
    $filters = "<?php\nfinal class Q { public function list(bool \$includeArchived, string \$approved): void {} }";
    $controller = "<?php\nfinal class C { public function store(bool \$approved): void {} }";

    expect(ruleSignatures(new BooleanControlParameterRule, 'modules/Agri/Application/Queries/Q.php', $filters))->toBe([])
        ->and(ruleSignatures(new BooleanControlParameterRule, 'modules/Agri/Http/Controllers/C.php', $controller))->toBe([]);
});

it('accepts the NotAControl exemption only with a written reason', function (): void {
    $code = <<<'PHP'
        <?php
        final class ReportQuery
        {
            public function run(
                #[NotAControl('Hanya menyaring daftar yang tampil di layar')] bool $approved,
                #[NotAControl('')] bool $verified,
            ): void {}
        }
        PHP;

    $signatures = ruleSignatures(new BooleanControlParameterRule, 'modules/Agri/Application/Queries/ReportQuery.php', $code);

    expect($signatures)->toBe(['bool $verified @ run()']);
});
