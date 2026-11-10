<?php

declare(strict_types=1);

namespace Modules\Shared\Application\Ui;

use Modules\Core\Domain\StateMachines\ComponentReleaseStateMachine;

/**
 * StatusBadgeRegistry
 *
 * Registri pemetaan semantik konstanta status State Machine ke varian warna lencana
 * dan label mikrokopi Bahasa Indonesia yang konsisten lintas 30 lini bisnis.
 */
class StatusBadgeRegistry
{
    /**
     * @var array<string, array{variant: string, label: string}>
     */
    private static array $statusMap = [
        // ComponentRelease State Machine (Fase 240)
        ComponentReleaseStateMachine::STATUS_PROPOSED => ['variant' => 'neutral', 'label' => 'Diusulkan'],
        ComponentReleaseStateMachine::STATUS_IN_DESIGN => ['variant' => 'info', 'label' => 'Dalam Desain'],
        ComponentReleaseStateMachine::STATUS_A11Y_REVIEW => ['variant' => 'warning', 'label' => 'Review Aksesibilitas'],
        ComponentReleaseStateMachine::STATUS_APPROVED => ['variant' => 'info', 'label' => 'Disetujui'],
        ComponentReleaseStateMachine::STATUS_RELEASED => ['variant' => 'success', 'label' => 'Dirilis'],
        ComponentReleaseStateMachine::STATUS_DEPRECATED => ['variant' => 'warning', 'label' => 'Deprecated'],
        ComponentReleaseStateMachine::STATUS_REMOVED => ['variant' => 'danger', 'label' => 'Dihapus'],

        // Status Umum Aplikasi
        'active' => ['variant' => 'success', 'label' => 'Aktif'],
        'inactive' => ['variant' => 'neutral', 'label' => 'Nonaktif'],
        'pending' => ['variant' => 'warning', 'label' => 'Menunggu'],
        'completed' => ['variant' => 'success', 'label' => 'Selesai'],
        'failed' => ['variant' => 'danger', 'label' => 'Gagal'],
        'cancelled' => ['variant' => 'danger', 'label' => 'Dibatalkan'],
        'draft' => ['variant' => 'neutral', 'label' => 'Draf'],
        'open' => ['variant' => 'info', 'label' => 'Terbuka'],
        'closed' => ['variant' => 'neutral', 'label' => 'Ditutup'],

        // Riset UX
        'OPEN' => ['variant' => 'danger', 'label' => 'Terbuka'],
        'BACKLOG' => ['variant' => 'warning', 'label' => 'Antrean'],
        'REMEDIATED' => ['variant' => 'success', 'label' => 'Terselesaikan'],
    ];

    /**
     * Mengambil konfigurasi badge (varian dan label) untuk status tertentu.
     *
     * @return array{variant: string, label: string}
     */
    public static function get(string $status): array
    {
        if (isset(self::$statusMap[$status])) {
            return self::$statusMap[$status];
        }

        $fallbackLabel = ucwords(str_replace(['_', '-'], ' ', $status));

        return ['variant' => 'neutral', 'label' => $fallbackLabel];
    }

    /**
     * Mendaftarkan pemetaan status kustom dari modul lain.
     */
    public static function register(string $status, string $variant, string $label): void
    {
        self::$statusMap[$status] = [
            'variant' => $variant,
            'label' => $label,
        ];
    }
}
