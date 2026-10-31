<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use InvalidArgumentException;

/**
 * Work Breakdown Structure (WBS) Weighted Tree with Dual-Traversal Verification.
 *
 * Validates that sibling weights sum to 10,000 bps at each internal node,
 * computes exact bottom-up progress using half-up fixed scaling,
 * independently recomputes top-down leaf contribution, and verifies
 * certificate consistency (PR2 Certification).
 *
 * Complexity: O(N) where N is node count.
 *
 * @author Google Deepmind & Antigravity
 */
final class WeightedTree
{
    /**
     * Compute and verify overall tree progress.
     *
     * @throws InvalidArgumentException
     */
    public static function computeProgress(WeightedTreeNode $root): WeightedTreeResult
    {
        // 1. Recursive sibling weight validation
        self::validateTree($root);

        // 2. Exact bottom-up traversal
        /** @var array<string, int> $nodeProgress */
        $nodeProgress = [];
        $bottomUpProgress = self::traverseBottomUp($root, $nodeProgress);

        // 3. Independent top-down traversal for PR2 certification
        /** @var array<string, int> $leafGlobalWeights */
        $leafGlobalWeights = [];
        $topDownProgress = self::traverseTopDown($root, 10_000, $leafGlobalWeights);

        $discrepancy = abs($bottomUpProgress - $topDownProgress);
        $certificate = new WeightedTreeCertificate(
            rootId: $root->id,
            bottomUpProgressBps: $bottomUpProgress,
            topDownProgressBps: $topDownProgress,
            discrepancyBps: $discrepancy,
            nodeProgressBps: $nodeProgress,
            leafGlobalWeightsBps: $leafGlobalWeights
        );

        WeightedTreeCertificateVerifier::verify($certificate);

        return new WeightedTreeResult(
            progressBps: $bottomUpProgress,
            nodeProgressBps: $nodeProgress,
            certificate: $certificate
        );
    }

    /**
     * Validates that for every internal node, sibling weights sum to exactly 10,000 bps.
     * Also checks for unique node IDs.
     */
    private static function validateTree(WeightedTreeNode $node, array &$seenIds = []): void
    {
        if (isset($seenIds[$node->id])) {
            throw new InvalidArgumentException("Duplicate node ID detected in tree: {$node->id}");
        }
        $seenIds[$node->id] = true;

        if (! $node->isLeaf()) {
            $sumBps = 0;
            foreach ($node->children as $child) {
                $sumBps += $child->weightBps;
            }

            if ($sumBps !== 10_000) {
                throw new InvalidArgumentException("Children weights of node {$node->id} must sum to 10000 bps, got {$sumBps}");
            }

            foreach ($node->children as $child) {
                self::validateTree($child, $seenIds);
            }
        }
    }

    /**
     * Bottom-up recursion calculating progress from leaves up to root.
     *
     * @param  array<string, int>  $nodeProgress
     */
    private static function traverseBottomUp(WeightedTreeNode $node, array &$nodeProgress): int
    {
        if ($node->isLeaf()) {
            $progress = $node->progressBps ?? 0;
            $nodeProgress[$node->id] = $progress;

            return $progress;
        }

        $totalContribution = 0;
        foreach ($node->children as $child) {
            $childProgress = self::traverseBottomUp($child, $nodeProgress);
            $contrib = FixedScale::mulDivHalfUp($childProgress, $child->weightBps, 10_000);
            $totalContribution += $contrib;
        }

        // Clamp to 10000 bps maximum
        $totalContribution = min(10_000, max(0, $totalContribution));
        $nodeProgress[$node->id] = $totalContribution;

        return $totalContribution;
    }

    /**
     * Top-down recursion calculating global weights and independent root progress.
     *
     * @param  array<string, int>  $leafGlobalWeights
     */
    private static function traverseTopDown(
        WeightedTreeNode $node,
        int $globalWeightBps,
        array &$leafGlobalWeights
    ): int {
        if ($node->isLeaf()) {
            $leafGlobalWeights[$node->id] = $globalWeightBps;
            $progress = $node->progressBps ?? 0;

            return FixedScale::mulDivHalfUp($progress, $globalWeightBps, 10_000);
        }

        $sumProgress = 0;
        foreach ($node->children as $child) {
            $childGlobalWeight = FixedScale::mulDivHalfUp($globalWeightBps, $child->weightBps, 10_000);
            $sumProgress += self::traverseTopDown($child, $childGlobalWeight, $leafGlobalWeights);
        }

        return min(10_000, max(0, $sumProgress));
    }
}
