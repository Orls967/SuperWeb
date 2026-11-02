<?php

declare(strict_types=1);

use Modules\Shared\Domain\Numerics\CanonicalHashChain;
use Modules\Shared\Domain\Numerics\CanonicalHashChainVerifier;

test('canonical hash chain genesis and canonicalization', function () {
    expect(CanonicalHashChain::GENESIS_PREV_HASH)->toBe(str_repeat('0', 64));

    $payload = [
        'zebra' => 123,
        'alpha' => 'hello',
        'list' => [10, 20, 30],
        'nested' => [
            'gamma' => true,
            'beta' => null,
            'delta' => false,
        ],
    ];

    $canonical = CanonicalHashChain::canonicalize($payload);
    $decoded = json_decode($canonical, true);

    expect(array_keys($decoded))->toBe(['alpha', 'list', 'nested', 'zebra']);
    expect($decoded['list'])->toBe(['10', '20', '30']);
    expect(array_keys($decoded['nested']))->toBe(['beta', 'delta', 'gamma']);
    expect($decoded['zebra'])->toBe('123');
    expect($decoded['nested']['gamma'])->toBe('1');
    expect($decoded['nested']['delta'])->toBe('0');
    expect($decoded['nested']['beta'])->toBe('');
});

test('canonical hash chain exact hash calculation matches manual sha256', function () {
    $prev = CanonicalHashChain::GENESIS_PREV_HASH;
    $payload = ['k' => 'v'];
    $canonical = '{"k":"v"}';
    $expected = hash('sha256', $prev."\n".$canonical);

    expect(CanonicalHashChain::computeHash($prev, $payload))->toBe($expected);
});

test('canonical hash chain builds valid sequential chain', function () {
    $records = [];
    $prevHash = CanonicalHashChain::GENESIS_PREV_HASH;

    for ($i = 0; $i < 5; $i++) {
        $payload = ['step' => $i, 'value' => "data_{$i}", 'tags' => ['a', 'b']];
        $hash = CanonicalHashChain::computeHash($prevHash, $payload);
        $records[] = [
            'prev_hash' => $prevHash,
            'hash' => $hash,
            'payload' => $payload,
        ];
        $prevHash = $hash;
    }

    expect(CanonicalHashChainVerifier::verifyChain($records))->toBeNull();
});

test('canonical hash chain verifier detects corrupted genesis', function () {
    $payload = ['a' => 1];
    $hash = CanonicalHashChain::computeHash(CanonicalHashChain::GENESIS_PREV_HASH, $payload);
    $records = [
        [
            'prev_hash' => str_repeat('1', 64),
            'hash' => $hash,
            'payload' => $payload,
        ],
    ];

    expect(CanonicalHashChainVerifier::verifyChain($records))->toBe(0);
});

test('canonical hash chain verifier pinpoints first corrupted index in chain', function () {
    $records = [];
    $prevHash = CanonicalHashChain::GENESIS_PREV_HASH;

    for ($i = 0; $i < 10; $i++) {
        $payload = ['id' => $i];
        $hash = CanonicalHashChain::computeHash($prevHash, $payload);
        $records[] = [
            'prev_hash' => $prevHash,
            'hash' => $hash,
            'payload' => $payload,
        ];
        $prevHash = $hash;
    }

    // Corrupt record at index 4 (tamper payload)
    $records[4]['payload']['id'] = 999;
    expect(CanonicalHashChainVerifier::verifyChain($records))->toBe(4);

    // Corrupt record at index 7 (tamper hash)
    $records[4]['payload']['id'] = 4; // restore
    $records[7]['hash'] = str_repeat('a', 64);
    expect(CanonicalHashChainVerifier::verifyChain($records))->toBe(7);

    // Corrupt record at index 8 (broken prev_hash link)
    $records[7]['hash'] = CanonicalHashChain::computeHash($records[7]['prev_hash'], $records[7]['payload']); // restore
    $records[8]['prev_hash'] = str_repeat('b', 64);
    expect(CanonicalHashChainVerifier::verifyChain($records))->toBe(8);

    // Missing fields in record
    $records[8]['prev_hash'] = $records[7]['hash']; // restore
    $corruptMissing = $records;
    unset($corruptMissing[3]['hash']);
    expect(CanonicalHashChainVerifier::verifyChain($corruptMissing))->toBe(3);

    $corruptMissingPrev = $records;
    unset($corruptMissingPrev[2]['prev_hash']);
    expect(CanonicalHashChainVerifier::verifyChain($corruptMissingPrev))->toBe(2);
});

test('canonical hash chain verifier handles empty records', function () {
    expect(CanonicalHashChainVerifier::verifyChain([]))->toBeNull();
});

test('canonical hash chain rejects invalid prev_hash length or chars', function () {
    expect(fn () => CanonicalHashChain::computeHash('short', ['x' => 1]))->toThrow(InvalidArgumentException::class);
    expect(fn () => CanonicalHashChain::computeHash(str_repeat('z', 64), ['x' => 1]))->toThrow(InvalidArgumentException::class);
});
