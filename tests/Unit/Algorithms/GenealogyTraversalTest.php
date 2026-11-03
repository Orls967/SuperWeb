<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Graph\GenealogyTraversal;
use Modules\Shared\Domain\Algorithms\Graph\GenealogyTraversalCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Graph\GenealogyTraversalResult;

covers(GenealogyTraversal::class, GenealogyTraversalCertificateVerifier::class, GenealogyTraversalResult::class);

describe('GenealogyTraversal & GenealogyTraversalCertificateVerifier', function (): void {
    test('traversal maju dan mundur mendeteksi keterjangkauan dan memverifikasi sertifikat penutupan', function (): void {
        // Graf uji:
        // A -> B -> C -> D
        // B -> E
        $forwardEdges = [
            'LOT:A' => [['type' => 'BATCH', 'id' => 'B']],
            'BATCH:B' => [['type' => 'PACK', 'id' => 'C'], ['type' => 'PACK', 'id' => 'E']],
            'PACK:C' => [['type' => 'RETAIL', 'id' => 'D']],
            'RETAIL:D' => [],
            'PACK:E' => [],
        ];

        $backwardEdges = [
            'RETAIL:D' => [['type' => 'PACK', 'id' => 'C']],
            'PACK:C' => [['type' => 'BATCH', 'id' => 'B']],
            'PACK:E' => [['type' => 'BATCH', 'id' => 'B']],
            'BATCH:B' => [['type' => 'LOT', 'id' => 'A']],
            'LOT:A' => [],
        ];

        $loader = function (string $type, string $id, string $direction) use ($forwardEdges, $backwardEdges): array {
            $key = "{$type}:{$id}";
            if ($direction === 'FORWARD') {
                return $forwardEdges[$key] ?? [];
            }

            return $backwardEdges[$key] ?? [];
        };

        $traversal = new GenealogyTraversal(customLoader: $loader);

        $forward = $traversal->traverseForward('LOT', 'A');
        expect($forward->reaches('PACK', 'C'))->toBeTrue()
            ->and($forward->reaches('RETAIL', 'D'))->toBeTrue()
            ->and($forward->reaches('PACK', 'E'))->toBeTrue()
            ->and($forward->certificateVerified)->toBeTrue();

        $backward = $traversal->traverseBackward('RETAIL', 'D');
        expect($backward->reaches('PACK', 'C'))->toBeTrue()
            ->and($backward->reaches('BATCH', 'B'))->toBeTrue()
            ->and($backward->reaches('LOT', 'A'))->toBeTrue()
            ->and($backward->reaches('PACK', 'E'))->toBeFalse()
            ->and($backward->certificateVerified)->toBeTrue();
    });

    test('verifikasi penolakan bila sertifikat penutupan tidak cocok', function (): void {
        $verifier = new GenealogyTraversalCertificateVerifier;
        $loader = fn (string $t, string $i, string $d) => [];

        $badResult = new GenealogyTraversalResult(
            rootType: 'LOT',
            rootId: 'A',
            direction: 'FORWARD',
            visitedNodes: ['LOT:A'],
            certificateHash: 'corrupted_hash',
            certificateVerified: true
        );

        $valid = $verifier->verify('LOT', 'A', 'FORWARD', $badResult, $loader);
        expect($valid)->toBeFalse();
    });
});
