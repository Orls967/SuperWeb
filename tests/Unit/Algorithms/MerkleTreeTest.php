<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Hash\MerkleCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Hash\MerkleTree;
use Modules\Shared\Domain\Algorithms\Hash\MerkleTreeResult;

covers(MerkleTree::class, MerkleTreeResult::class, MerkleCertificateVerifier::class);

describe('MerkleTree Algorithm (D86-4)', function (): void {
    it('matches mandatory test vectors from prompt exactly', function (): void {
        $treeAlgorithm = new MerkleTree;
        $verifier = new MerkleCertificateVerifier;

        // Vektor wajib 4 daun: "1:500", "2:300", "3:200", "4:1000"
        $leaves4 = ['1:500', '2:300', '3:200', '4:1000'];
        $tree4 = $treeAlgorithm->build($leaves4);

        // Daun0 sha256
        $expectedLeaf0 = '717fe51487c76cc00d488b8732176a4cde5af558fa448d43456e0cef035597c4';
        expect(hash('sha256', $leaves4[0]))->toBe($expectedLeaf0);

        // Root 4 daun
        $expectedRoot4 = 'b275384498573111f4ae43c74765a6b2b3edbe3be4fc87f14ae49f797bf5eacb';
        expect($tree4->root)->toBe($expectedRoot4);

        // Bukti dan verifikasi semua 4 daun
        foreach ($leaves4 as $index => $leaf) {
            $proof = $treeAlgorithm->getProof($tree4, $index);
            expect($verifier->verify($leaf, $proof, $tree4->root))->toBeTrue();
        }

        // Vektor wajib 3 daun pertama: "1:500", "2:300", "3:200"
        $leaves3 = ['1:500', '2:300', '3:200'];
        $tree3 = $treeAlgorithm->build($leaves3);

        $expectedRoot3 = '0a1f0ee26b0d17d73015540eca2c076c67bf266d288b96bd79f35f995ed568da';
        expect($tree3->root)->toBe($expectedRoot3);

        // Bukti dan verifikasi semua 3 daun
        foreach ($leaves3 as $index => $leaf) {
            $proof = $treeAlgorithm->getProof($tree3, $index);
            expect($verifier->verify($leaf, $proof, $tree3->root))->toBeTrue();
        }
    });

    it('rejects tampered leaf data or invalid root hash', function (): void {
        $treeAlgorithm = new MerkleTree;
        $verifier = new MerkleCertificateVerifier;

        $leaves = ['1:500', '2:300', '3:200', '4:1000'];
        $tree = $treeAlgorithm->build($leaves);
        $proof0 = $treeAlgorithm->getProof($tree, 0);

        // Daun dirusak: bobot diubah
        expect($verifier->verify('1:999999', $proof0, $tree->root))->toBeFalse();

        // Root dirusak
        $corruptRoot = substr($tree->root, 0, -2).'ff';
        expect($verifier->verify($leaves[0], $proof0, $corruptRoot))->toBeFalse();
    });

    it('throws exception on invalid proof format or corrupted hash', function (): void {
        $verifier = new MerkleCertificateVerifier;

        expect(fn () => $verifier->verify('leaf', [['position' => 'center', 'hash' => str_repeat('a', 64)]], str_repeat('0', 64)))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => $verifier->verify('leaf', [['position' => 'left', 'hash' => 'invalid-hex']], str_repeat('0', 64)))
            ->toThrow(InvalidArgumentException::class);
    });
});
