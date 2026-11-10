<?php

declare(strict_types=1);

namespace Modules\Shared\Application\Ui;

use InvalidArgumentException;

/**
 * Pembangun Breadcrumb otomatis berdasarkan hierarki rute dan segmen konteks.
 */
class BreadcrumbBuilder
{
    /**
     * @var array<int, array{label: string, url: string|null, is_current: bool, icon: string|null, metadata: array<string, mixed>}>
     */
    protected array $items = [];

    /**
     * @var array<string, string>
     */
    protected array $segmentLabels = [
        'design-system' => 'Design System',
        'releases' => 'Rilis & Versi',
        'research' => 'Riset UX',
        'create' => 'Tambah Baru',
        'edit' => 'Ubah',
        'components' => 'Katalog Komponen',
        'tokens' => 'Design Tokens',
        'accessibility' => 'Aksesibilitas',
        'settings' => 'Pengaturan',
        'admin' => 'Administrasi',
    ];

    /**
     * Inisialisasi breadcrumb builder dengan item awal opsional.
     *
     * @param  array<int, array{label: string, url?: string|null, icon?: string|null}>  $initial
     */
    public function __construct(array $initial = [])
    {
        foreach ($initial as $item) {
            $this->add((string) $item['label'], $item['url'] ?? null, $item['icon'] ?? null);
        }
    }

    /**
     * Tambahkan segmen breadcrumb baru.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function add(string $label, ?string $url = null, ?string $icon = null, array $metadata = []): self
    {
        $cleanLabel = trim($label);
        if ($cleanLabel === '') {
            throw new InvalidArgumentException('Label breadcrumb tidak boleh berupa string kosong.');
        }

        // Segmen sebelumnya tidak lagi menjadi segmen aktif/terkini
        foreach ($this->items as &$item) {
            $item['is_current'] = false;
        }
        unset($item);

        $this->items[] = [
            'label' => $cleanLabel,
            'url' => $url !== null ? trim($url) : null,
            'is_current' => true,
            'icon' => $icon,
            'metadata' => $metadata,
        ];

        return $this;
    }

    /**
     * Tambahkan segmen beranda default di posisi awal.
     */
    public function prependHome(string $label = 'Beranda', string $url = '/', ?string $icon = 'home'): self
    {
        array_unshift($this->items, [
            'label' => $label,
            'url' => $url,
            'is_current' => empty($this->items),
            'icon' => $icon,
            'metadata' => ['is_root' => true],
        ]);

        return $this;
    }

    /**
     * Daftarkan translasi/label kustom untuk segmen URL tertentu.
     */
    public function registerSegmentLabel(string $segment, string $label): self
    {
        $this->segmentLabels[strtolower(trim($segment))] = trim($label);

        return $this;
    }

    /**
     * Bangun breadcrumb otomatis dari path URL (misal: /admin/design-system/releases/create).
     */
    public function fromUrlPath(string $path, bool $includeHome = true): self
    {
        $this->items = [];

        if ($includeHome) {
            $this->prependHome();
        }

        $segments = array_filter(explode('/', trim($path, '/')));
        $accumulated = '';

        foreach ($segments as $segment) {
            $accumulated .= '/'.$segment;
            $normalizedKey = strtolower($segment);
            $label = $this->segmentLabels[$normalizedKey] ?? $this->formatSegmentToLabel($segment);

            $this->add($label, $accumulated);
        }

        return $this;
    }

    /**
     * Ubah slug segment URL menjadi teks kapital terformat.
     */
    protected function formatSegmentToLabel(string $segment): string
    {
        $clean = str_replace(['-', '_'], ' ', $segment);

        return ucwords($clean);
    }

    /**
     * Ambil seluruh item breadcrumb yang telah tersusun.
     *
     * @return array<int, array{label: string, url: string|null, is_current: bool, icon: string|null, metadata: array<string, mixed>}>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Ekspor menjadi struktur Schema.org BreadcrumbList untuk SEO dan mesin pencari.
     *
     * @return array<string, mixed>
     */
    public function toSchemaOrgJsonLd(string $baseUrl = 'https://superweb.local'): array
    {
        $itemList = [];
        $position = 1;

        foreach ($this->items as $item) {
            $entry = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $item['label'],
            ];

            if ($item['url'] !== null) {
                $fullUrl = str_starts_with($item['url'], 'http')
                    ? $item['url']
                    : rtrim($baseUrl, '/').'/'.ltrim($item['url'], '/');
                $entry['item'] = $fullUrl;
            }

            $itemList[] = $entry;
            $position++;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $itemList,
        ];
    }

    /**
     * Hitung jumlah segmen dalam breadcrumb.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Apakah breadcrumb memiliki item?
     */
    public function isNotEmpty(): bool
    {
        return ! empty($this->items);
    }
}
