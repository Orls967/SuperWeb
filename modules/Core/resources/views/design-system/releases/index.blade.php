@extends('layouts.app')

@section('title', 'Tata Kelola Rilis Komponen — Design System')

@section('content')
    <div class="space-y-6">
        <x-ds.breadcrumb :items="[
            ['label' => 'Beranda', 'url' => route('dashboard')],
            ['label' => 'Design System', 'url' => route('design-system.index')],
            ['label' => 'Tata Kelola Rilis', 'url' => '#', 'active' => true],
        ]" />

        @if(session('status'))
            <x-ds.toast type="success" :message="session('status')" />
        @endif

        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Tata Kelola Rilis & Siklus Hidup Komponen</h1>
                <p class="text-sm text-slate-600 dark:text-slate-400">Pengelolaan State Machine 7 status rilis komponen Design System SuperWeb.</p>
            </div>
            <a href="{{ route('admin.design-system.releases.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 transition">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                Ajukan Proposal Baru
            </a>
        </div>

        {{-- Statistik Siklus Hidup --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <x-ds.stat label="Total" :value="$stats['total']" hint="Komponen Terdaftar" />
            <x-ds.stat label="Draft" :value="$stats['draft']" hint="Fase Usulan" />
            <x-ds.stat label="In Review" :value="$stats['in_review']" hint="Uji Aksesibilitas" />
            <x-ds.stat label="Approved" :value="$stats['approved']" hint="Siap Rilis" />
            <x-ds.stat label="Released" :value="$stats['released']" change-type="positive" hint="Produksi Aktif" />
            <x-ds.stat label="Deprecated" :value="$stats['deprecated']" change-type="negative" hint="Ditinggalkan" />
        </div>

        {{-- Tabel Komponen & Transisi Status --}}
        <x-ds.card title="Daftar Komponen & Transisi Siklus Hidup">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600 dark:text-slate-400">
                    <thead class="border-b border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200 font-semibold uppercase tracking-wider bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th class="px-4 py-3">Nama Komponen</th>
                            <th class="px-4 py-3">Kategori</th>
                            <th class="px-4 py-3">Versi</th>
                            <th class="px-4 py-3">Status Saat Ini</th>
                            <th class="px-4 py-3">Aksi Transisi Cepat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($components as $comp)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition">
                                <td class="px-4 py-3 font-semibold text-slate-900 dark:text-slate-100">
                                    <a href="{{ route('design-system.show', $comp->name) }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ $comp->display_name }}
                                    </a>
                                    <span class="block font-mono text-[11px] text-slate-500">&lt;x-ds.{{ $comp->name }}&gt;</span>
                                </td>
                                <td class="px-4 py-3 font-medium">{{ ucfirst($comp->category) }}</td>
                                <td class="px-4 py-3 font-mono">{{ $comp->current_version }}</td>
                                <td class="px-4 py-3">
                                    <x-ds.badge-status :status="$comp->status" />
                                </td>
                                <td class="px-4 py-3">
                                    {{-- Transisi Form Sesuai Status --}}
                                    <div class="flex items-center gap-1.5">
                                        @if($comp->status === 'draft')
                                            <form method="POST" action="{{ route('admin.design-system.releases.transition', $comp->name) }}">
                                                @csrf
                                                <input type="hidden" name="to_status" value="in_review">
                                                <input type="hidden" name="reason" value="Kirim usulan untuk peninjauan a11y & visual">
                                                <x-ds.button type="submit" size="xs" variant="secondary">Review &rarr;</x-ds.button>
                                            </form>
                                        @elseif($comp->status === 'in_review')
                                            <form method="POST" action="{{ route('admin.design-system.releases.transition', $comp->name) }}">
                                                @csrf
                                                <input type="hidden" name="to_status" value="approved">
                                                <input type="hidden" name="reason" value="Lolos audit WCAG 2.1 AA dan pengujian token tema">
                                                <x-ds.button type="submit" size="xs" variant="primary">Setujui &rarr;</x-ds.button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.design-system.releases.transition', $comp->name) }}">
                                                @csrf
                                                <input type="hidden" name="to_status" value="draft">
                                                <input type="hidden" name="reason" value="Kembalikan ke draft untuk perbaikan temuan">
                                                <x-ds.button type="submit" size="xs" variant="ghost">Revisi</x-ds.button>
                                            </form>
                                        @elseif($comp->status === 'approved')
                                            <form method="POST" action="{{ route('admin.design-system.releases.transition', $comp->name) }}">
                                                @csrf
                                                <input type="hidden" name="to_status" value="released">
                                                <input type="hidden" name="reason" value="Rilis resmi kanonikal untuk seluruh modul aplikasi">
                                                <x-ds.button type="submit" size="xs" variant="primary">Rilis ke Prod</x-ds.button>
                                            </form>
                                        @elseif($comp->status === 'released')
                                            <form method="POST" action="{{ route('admin.design-system.releases.transition', $comp->name) }}">
                                                @csrf
                                                <input type="hidden" name="to_status" value="deprecated">
                                                <input type="hidden" name="reason" value="Komponen digantikan pola baru yang lebih aksesibel">
                                                <x-ds.button type="submit" size="xs" variant="danger">Deprecate</x-ds.button>
                                            </form>
                                        @elseif($comp->status === 'deprecated')
                                            <form method="POST" action="{{ route('admin.design-system.releases.transition', $comp->name) }}">
                                                @csrf
                                                <input type="hidden" name="to_status" value="retired">
                                                <input type="hidden" name="reason" value="Masa tenggang berakhir, komponen dipensiunkan">
                                                <x-ds.button type="submit" size="xs" variant="danger">Pensiunkan</x-ds.button>
                                            </form>
                                        @else
                                            <span class="text-[11px] text-slate-400 italic">Arsip permanen</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="pt-4">
                {{ $components->links() }}
            </div>
        </x-ds.card>

        {{-- Log Riwayat Rilis Terkini --}}
        <x-ds.card title="Aktivitas Transisi Siklus Hidup Terkini">
            <x-ds.timeline :items="$recentHistories->map(fn($h) => [
                'title' => ($h->component?->display_name ?? 'Komponen') . ': ' . strtoupper($h->from_status) . ' &rarr; ' . strtoupper($h->to_status),
                'description' => $h->reason ?? 'Pembaruan status state machine',
                'timestamp' => (string)$h->created_at,
                'status' => $h->to_status,
            ])->toArray()" />
        </x-ds.card>
    </div>
@endsection
