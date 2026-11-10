<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Search;

use App\Models\User;
use Modules\Core\Domain\Models\CoreUserSearchPin;

/**
 * Service pengelolaan jalan pintas favorit pengguna pada Command Palette.
 */
class SearchPinService
{
    /**
     * Mengambil daftar pintasan tersemat untuk pengguna tertentu.
     *
     * @return array<int, array{title: string, url: string, category: string, badge: string|null, badge_color: string|null}>
     */
    public function getUserPins(User $user): array
    {
        $pins = CoreUserSearchPin::query()
            ->where('user_id', $user->id)
            ->orderBy('order', 'asc')
            ->get();

        if ($pins->isEmpty()) {
            return $this->getDefaultPins($user);
        }

        return $pins->map(fn ($p) => [
            'id' => $p->id,
            'title' => $p->title,
            'url' => $p->url,
            'category' => $p->category,
            'badge' => $p->badge,
            'badge_color' => $p->badge_color,
        ])->toArray();
    }

    /**
     * Menyematkan hasil pencarian ke daftar pin pengguna.
     *
     * @param  array{title: string, url: string, category?: string, badge?: string, badge_color?: string}  $item
     */
    public function pinItem(User $user, array $item): CoreUserSearchPin
    {
        $count = CoreUserSearchPin::where('user_id', $user->id)->count();

        return CoreUserSearchPin::create([
            'user_id' => $user->id,
            'title' => $item['title'],
            'url' => $item['url'],
            'category' => $item['category'] ?? 'Shortcut',
            'badge' => $item['badge'] ?? null,
            'badge_color' => $item['badge_color'] ?? null,
            'order' => $count + 1,
        ]);
    }

    /**
     * Menghapus pin pengguna.
     */
    public function unpinItem(User $user, int $pinId): bool
    {
        return (bool) CoreUserSearchPin::where('user_id', $user->id)->where('id', $pinId)->delete();
    }

    /**
     * Pintasan bawaan jika pengguna belum pernah menyematkan pin khusus.
     *
     * @return array<int, array{title: string, url: string, category: string, badge: string, badge_color: string}>
     */
    private function getDefaultPins(User $user): array
    {
        return [
            [
                'title' => 'Kotak Masuk Tugas Saya',
                'url' => route('core.task-inbox.index'),
                'category' => 'Navigasi Inti',
                'badge' => 'INBOX',
                'badge_color' => 'bg-blue-500/20 text-blue-300',
            ],
            [
                'title' => 'Dasbor Utama Konsolidasi',
                'url' => route('dashboard'),
                'category' => 'Navigasi Inti',
                'badge' => 'CORE',
                'badge_color' => 'bg-indigo-500/20 text-indigo-300',
            ],
        ];
    }
}
