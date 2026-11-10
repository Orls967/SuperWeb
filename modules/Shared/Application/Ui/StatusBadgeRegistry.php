<?php

declare(strict_types=1);

namespace Modules\Shared\Application\Ui;

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
        'proposed' => ['variant' => 'neutral', 'label' => 'Diusulkan'],
        'in_design' => ['variant' => 'info', 'label' => 'Dalam Desain'],
        'a11y_review' => ['variant' => 'warning', 'label' => 'Review Aksesibilitas'],
        'approved' => ['variant' => 'info', 'label' => 'Disetujui'],
        'released' => ['variant' => 'success', 'label' => 'Dirilis'],
        'deprecated' => ['variant' => 'warning', 'label' => 'Deprecated'],
        'removed' => ['variant' => 'danger', 'label' => 'Dihapus'],

        // Status Umum Aplikasi
        'active' => ['variant' => 'success', 'label' => 'Aktif'],
        'inactive' => ['variant' => 'neutral', 'label' => 'Nonaktif'],
        'pending' => ['variant' => 'warning', 'label' => 'Menunggu'],
        'completed' => ['variant' => 'success', 'label' => 'Selesai'],
        'failed' => ['variant' => 'danger', 'label' => 'Gagal'],
        'cancelled' => ['variant' => 'danger', 'label' => 'Dibatalkan'],
        'draft' => ['variant' => 'neutral', 'label' => 'Draf'],
        'in_review' => ['variant' => 'warning', 'label' => 'Ditinjau'],
        'retired' => ['variant' => 'danger', 'label' => 'Dipensiunkan'],
        'archived' => ['variant' => 'neutral', 'label' => 'Diarsipkan'],
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
        $normalized = strtolower(trim($status));

        return self::$statusMap[$status]
            ?? self::$statusMap[$normalized]
            ?? ['variant' => 'neutral', 'label' => ucwords(str_replace('_', ' ', $status))];
    }

    /**
     * Mendaftarkan atau menimpa pemetaan badge untuk status kustom.
     */
    public static function register(string $status, string $variant, string $label): void
    {
        self::$statusMap[$status] = [
            'variant' => $variant,
            'label' => $label,
        ];
    }
}
