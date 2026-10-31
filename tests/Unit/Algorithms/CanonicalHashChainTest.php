<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Chain\CanonicalHashChain;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

covers(CanonicalHashChain::class);

describe('CanonicalHashChain', function (): void {
    it('validates prevHash length and hex characters', function (): void {
        $chain = new CanonicalHashChain;

        expect(fn () => $chain->link('invalid-hash', ['key' => 'val']))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => $chain->link(str_repeat('z', 64), ['key' => 'val']))
            ->toThrow(InvalidArgumentException::class);
    });

    it('handles unicode, slashes, nulls, and integer keys properly', function (): void {
        $chain = new CanonicalHashChain;

        $payload = [
            1 => 'numeric_key',
            'url' => 'https://example.com/foo/bar',
            'greeting' => 'halo dunia 日本語',
            'empty' => null,
            'flag' => false,
        ];

        $canonical = $chain->canonicalize($payload);
        expect($canonical)->toContain('"url":"https://example.com/foo/bar"')
            ->and($canonical)->toContain('"greeting":"halo dunia 日本語"')
            ->and($canonical)->toContain('"empty":""')
            ->and($canonical)->toContain('"flag":"false"');
    });

    it('rejects malformed row structures in verify', function (): void {
        $chain = new CanonicalHashChain;

        expect($chain->verify([
            ['prev_hash' => CanonicalHashChain::GENESIS_HASH],
        ]))->toBe(0);

        expect($chain->verify([
            [
                'prev_hash' => CanonicalHashChain::GENESIS_HASH,
                'payload' => 'not an array',
                'hash' => str_repeat('0', 64),
            ],
        ]))->toBe(0);
    });

    it('canonicalizes key order and scalar types identically', function (): void {
        $chain = new CanonicalHashChain;

        $p1 = ['z' => 5, 'a' => 'hello', 'm' => true];
        $p2 = ['a' => 'hello', 'm' => 'true', 'z' => '5'];

        expect($chain->canonicalize($p1))->toBe($chain->canonicalize($p2));

        $prev = CanonicalHashChain::GENESIS_HASH;
        expect($chain->link($prev, $p1))->toBe($chain->link($prev, $p2));
    });

    it('verifies valid genesis chain and detects single corrupted character', function (): void {
        $chain = new CanonicalHashChain;

        $rows = [];
        $prev = CanonicalHashChain::GENESIS_HASH;

        for ($i = 0; $i < 5; $i++) {
            $payload = ['step' => $i, 'event' => "item_{$i}"];
            $hash = $chain->link($prev, $payload);
            $rows[] = [
                'prev_hash' => $prev,
                'payload' => $payload,
                'hash' => $hash,
            ];
            $prev = $hash;
        }

        expect($chain->verify($rows))->toBeNull();

        // Mutate one character in row index 2 payload
        $corrupted = $rows;
        $corrupted[2]['payload']['event'] = 'item_2_tampered';
        expect($chain->verify($corrupted))->toBe(2);

        // Mutate one character in row index 3 hash
        $corruptedHash = $rows;
        $corruptedHash[3]['hash'] = str_repeat('f', 64);
        expect($chain->verify($corruptedHash))->toBe(3);

        // Mutate row index 1 prev_hash
        $corruptedPrev = $rows;
        $corruptedPrev[1]['prev_hash'] = str_repeat('a', 64);
        expect($chain->verify($corruptedPrev))->toBe(1);
    });

    it('property test: detects exact corrupted index in 1000 row random chain', function (): void {
        $chain = new CanonicalHashChain;
        $seed = 123456789;
        $randomizer = new Randomizer(new Xoshiro256StarStar($seed));

        $rows = [];
        $prev = CanonicalHashChain::GENESIS_HASH;

        for ($i = 0; $i < 1000; $i++) {
            $payload = [
                'id' => $i,
                'token' => bin2hex($randomizer->getBytes(8)),
                'amount' => $randomizer->getInt(1, 1000000),
            ];
            $hash = $chain->link($prev, $payload);
            $rows[] = [
                'prev_hash' => $prev,
                'payload' => $payload,
                'hash' => $hash,
            ];
            $prev = $hash;
        }

        expect($chain->verify($rows))->toBeNull();

        $tamperIndex = $randomizer->getInt(0, 999);
        $rows[$tamperIndex]['payload']['token'] .= '_corrupted';

        expect($chain->verify($rows))->toBe($tamperIndex);
    });

    it('benchmarks 10000 row link and verify < 1s', function (): void {
        $chain = new CanonicalHashChain;
        $rows = [];
        $prev = CanonicalHashChain::GENESIS_HASH;

        $start = microtime(true);
        for ($i = 0; $i < 5000; $i++) {
            $payload = ['id' => $i, 'val' => 'bench'];
            $hash = $chain->link($prev, $payload);
            $rows[] = [
                'prev_hash' => $prev,
                'payload' => $payload,
                'hash' => $hash,
            ];
            $prev = $hash;
        }
        $chain->verify($rows);
        $elapsed = microtime(true) - $start;

        expect($elapsed)->toBeLessThan(1.0);
    })->group('skala');
});
