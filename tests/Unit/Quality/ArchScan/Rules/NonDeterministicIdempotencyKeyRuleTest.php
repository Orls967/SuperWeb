<?php

declare(strict_types=1);

use App\Quality\ArchScan\Rules\NonDeterministicIdempotencyKeyRule;

it('reports random or time-based keys in named arguments, array keys and assignments', function (): void {
    $code = <<<'PHP'
        <?php
        $ledger->post(new PostingDTO(type: 'x', description: 'y', idempotencyKey: "resto:order:cogs:{$order->id}:".Str::uuid(), entries: []));
        $gateway->charge(['idempotency_key' => 'pts_'.Str::random(8), 'amount' => 10]);
        $idempotencyKey = "VEN-REL-{$code}-".time();
        $this->idempotencyKey = 'HTL-ROY-'.now()->format('Ym');
        PHP;

    $signatures = ruleSignatures(new NonDeterministicIdempotencyKeyRule, 'modules/Resto/Application/Actions/X.php', $code);

    expect($signatures)->toBe([
        'idempotency key = "resto:order:cogs:{$order->id}:".Str::uuid()',
        "idempotency key = 'pts_'.Str::random(8)",
        'idempotency key = "VEN-REL-{$code}-".time()',
        "idempotency key = 'HTL-ROY-'.now()->format('Ym')",
    ]);
});

it('follows a key variable to its assignment, including random fallbacks', function (): void {
    $code = <<<'PHP'
        <?php
        function pay(?string $requestKey): void
        {
            $key = $requestKey ?? (string) Str::uuid();
            $ledger->post(new PostingDTO(type: 'x', description: 'y', idempotencyKey: $key, entries: []));
        }
        PHP;

    $signatures = ruleSignatures(new NonDeterministicIdempotencyKeyRule, 'modules/Store/Application/Services/X.php', $code);

    expect($signatures)->toBe(['idempotency key = $key']);
});

it('does not report keys derived from business identity', function (): void {
    $code = <<<'PHP'
        <?php
        function bill(Invoice $invoice, string $period): void
        {
            $key = "mall:invoice:{$invoice->id}:{$period}";
            $ledger->post(new PostingDTO(type: 'x', description: 'y', idempotencyKey: $key, entries: []));
            $gateway->charge(['idempotency_key' => "pay:intent:{$invoice->id}", 'created_at' => now()]);
        }
        PHP;

    $signatures = ruleSignatures(new NonDeterministicIdempotencyKeyRule, 'modules/Mall/Application/Actions/X.php', $code);

    expect($signatures)->toBe([]);
});
