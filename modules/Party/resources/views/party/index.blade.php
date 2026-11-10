@extends('layouts.app')

@section('title', 'Party Directory')

@section('content')
    <x-enterprise.module-dashboard module="Party" />

<div class="max-w-7xl mx-auto px-4 py-8 space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Party Directory</h1>
            <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Satu sumber kebenaran untuk seluruh entitas dan mitra bisnis</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('party.legal-entities') }}">
                <x-ds.button variant="secondary" size="sm">🏢 Legal Entities</x-ds.button>
            </a>
            <a href="{{ route('party.create') }}" id="btn-create-party">
                <x-ds.button variant="primary" size="sm">+ Tambah Party</x-ds.button>
            </a>
        </div>
    </div>

    {{-- Flash Toast --}}
    @if(session('success'))
        <x-ds.toast type="success" :message="session('success')" />
    @endif

    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap gap-3 p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm">
        <div class="flex-1 min-w-48">
            <x-ds.input
                id="search-party"
                name="q"
                :value="request('q')"
                placeholder="Cari nama / NIB..."
                aria-label="Cari nama atau NIB party"
            />
        </div>
        <div class="w-48">
            <x-ds.select
                id="filter-status"
                name="status"
                :value="request('status')"
                :options="[
                    '' => 'Semua Status',
                    'pending' => 'Pending',
                    'verified' => 'Verified',
                    'suspended' => 'Suspended',
                    'blacklisted' => 'Blacklisted',
                ]"
                aria-label="Filter status party"
            />
        </div>
        <div class="w-48">
            <x-ds.select
                id="filter-role"
                name="role"
                :value="request('role')"
                :options="[
                    '' => 'Semua Role',
                    'supplier' => 'Supplier',
                    'carrier' => 'Carrier',
                    'tenant' => 'Tenant',
                    'customer' => 'Customer',
                    'distributor' => 'Distributor',
                    'agent' => 'Agent',
                    'partner' => 'Partner',
                    'franchisee' => 'Franchisee',
                ]"
                aria-label="Filter role party"
            />
        </div>
        <div class="flex items-center gap-2">
            <x-ds.button type="submit" variant="primary" size="md">Filter</x-ds.button>
            <a href="{{ route('party.index') }}" class="px-3 py-2 text-xs font-medium text-slate-500 hover:text-slate-900 dark:hover:text-white transition">Reset</a>
        </div>
    </form>

    {{-- Table Container --}}
    <x-ds.card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-500 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Tipe</th>
                        <th class="px-4 py-3">Role(s)</th>
                        <th class="px-4 py-3">Status KYB</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Credit Score</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse($parties as $party)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900 dark:text-white">{{ $party->name }}</div>
                            @if($party->nib)
                                <div class="text-xs text-slate-500 font-mono">NIB: {{ $party->nib }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300 capitalize">{{ $party->type->value }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @foreach($party->roles->where('is_active', true)->take(3) as $role)
                                    <span class="px-2 py-0.5 bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 rounded text-xs font-medium border border-blue-200 dark:border-blue-800">
                                        {{ ucfirst($role->role->value) }}
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <x-ds.badge-status :status="$party->kyb_status->value" />
                        </td>
                        <td class="px-4 py-3">
                            <x-ds.badge-status :status="$party->status->value" />
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if($party->creditProfile)
                                <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $party->creditProfile->internal_score }}</span>
                                <span class="text-slate-500 text-xs">/100</span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('party.show', $party) }}" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                                Detail &rarr;
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="p-8">
                            <x-ds.empty
                                title="Tidak Ada Party Ditemukan"
                                description="Belum ada data entitas atau mitra bisnis yang sesuai dengan kriteria pencarian."
                                action-label="Tambah Party Baru"
                                :action-url="route('party.create')"
                            />
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($parties->hasPages())
            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                {{ $parties->links() }}
            </div>
        @endif
    </x-ds.card>
</div>
@endsection
