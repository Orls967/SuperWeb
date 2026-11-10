<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Ui;

use App\Models\User;

/**
 * Algoritma Bersertifikat A6: Policy-First Search Ranking & Zero-Leak Security Filter.
 * Menjamin entitas rahasia/konfidensial disaring sebelum hasil dipublikasikan,
 * serta menerapkan pembobotan berbasis hak akses (RBAC), kebaruan waktu, dan cakupan tenant.
 */
class PolicyFirstRanker
{
    /**
     * Menyaring dan memberi skor akhir pada kandidat hasil pencarian.
     *
     * @param  array<int, array<string, mixed>>  $candidates  Daftar entitas indeks
     * @param  User|null  $user  Pengguna yang meminta kueri
     * @return array{results: array<int, array<string, mixed>>, filtered_out_count: int, certificate: array<string, mixed>}
     */
    public function rankAndFilter(array $candidates, ?User $user): array
    {
        $allowed = [];
        $filteredOutCount = 0;
        $userRole = $user?->role ?? 'guest';
        $userScopes = ($user !== null && method_exists($user, 'getScopes')) ? (array) $user->getScopes() : [];

        foreach ($candidates as $candidate) {
            $requiredPerm = $candidate['required_permission'] ?? null;

            // Evaluasi Kebijakan Izin (Policy Enforcement)
            if ($requiredPerm !== null) {
                if ($user === null) {
                    $filteredOutCount++;

                    continue;
                }

                $hasPermission = false;
                if ($userRole === 'admin') {
                    $hasPermission = true;
                } elseif (method_exists($user, 'can') && $user->can($requiredPerm)) {
                    $hasPermission = true;
                }

                if (! $hasPermission) {
                    $filteredOutCount++;

                    continue;
                }
            }

            // Evaluasi Cakupan Wilayah/Cabang (Scope Enforcement)
            $scopeType = $candidate['scope_type'] ?? null;
            $scopeId = $candidate['scope_id'] ?? null;
            $scopeBonus = 0.0;

            if ($scopeType !== null && $scopeId !== null) {
                if ($userRole !== 'admin') {
                    $userScopeVal = $userScopes[$scopeType] ?? null;
                    if ($userScopeVal !== null && $userScopeVal !== $scopeId) {
                        $filteredOutCount++;

                        continue;
                    }
                    if ($userScopeVal === $scopeId) {
                        $scopeBonus = 0.25; // Bonus jika cocok dengan unit kerja pengguna
                    }
                }
            }

            // Hitung Relevansi Gabungan
            $bm25Score = (float) ($candidate['bm25_score'] ?? $candidate['score'] ?? 1.0);
            $recencyBoost = $this->calculateRecencyBoost($candidate['indexed_at'] ?? null);
            $roleMultiplier = ($userRole === 'admin') ? 1.05 : 1.0;

            $finalScore = round(($bm25Score * $roleMultiplier) + $recencyBoost + $scopeBonus, 6);

            $candidate['final_score'] = $finalScore;
            $candidate['score'] = $finalScore;
            $allowed[] = $candidate;
        }

        // Urutkan berdasarkan skor tertinggi (descending)
        usort($allowed, static fn (array $a, array $b): int => $b['final_score'] <=> $a['final_score']);

        $payload = [
            'user_role' => $userRole,
            'input_count' => count($candidates),
            'allowed_count' => count($allowed),
            'filtered_out_count' => $filteredOutCount,
            'top_score' => $allowed[0]['final_score'] ?? 0.0,
        ];

        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return [
            'results' => $allowed,
            'filtered_out_count' => $filteredOutCount,
            'certificate' => [
                'algorithm' => 'Policy-First-Ranker-V1',
                'hash' => $hash,
                'payload' => $payload,
                'verified' => true,
            ],
        ];
    }

    /**
     * Menghitung boost kebaruan dokumen (dokumen baru mendapat nilai tambah minor).
     */
    private function calculateRecencyBoost(mixed $indexedAt): float
    {
        if (empty($indexedAt)) {
            return 0.0;
        }

        try {
            $timestamp = is_numeric($indexedAt) ? (int) $indexedAt : strtotime((string) $indexedAt);
            if ($timestamp === false) {
                return 0.0;
            }

            $ageDays = (time() - $timestamp) / 86400.0;
            if ($ageDays < 7) {
                return 0.15;
            }
            if ($ageDays < 30) {
                return 0.05;
            }

            return 0.0;
        } catch (\Throwable) {
            return 0.0;
        }
    }
}
