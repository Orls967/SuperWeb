@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-xl font-semibold text-white">5G Network Slices Provisioning</h2>
                    <p class="mt-1 text-sm text-gray-400">Dedikasi Bandwidth URLLC / eMBB untuk Enterprise Tenants</p>
                </div>
                <a href="{{ route('tlx.network.index') }}" class="text-sm text-indigo-400 hover:underline">&larr; Kembali ke Overview</a>
            </div>

            @if(session('success'))
                <div class="mb-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-gray-700/50 p-6 rounded-lg">
                    <h3 class="text-lg font-medium text-white mb-4">Daftar Network Slices Aktif</h3>
                    <div class="space-y-3">
                        @forelse($links as $link)
                            <div class="p-4 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                                <div>
                                    <div class="text-white font-mono font-medium">{{ $link->link_code }}</div>
                                    <div class="text-xs text-gray-400 mt-1">Source: {{ $link->source_site_id }} &rarr; Target: {{ $link->target_site_id }}</div>
                                    <div class="text-xs text-indigo-400 mt-1">Bandwidth: {{ $link->bandwidth_capacity_mbps }} Mbps • Latensi: {{ $link->latency_ms }} ms</div>
                                </div>
                                <div>
                                    <span class="px-2.5 py-1 text-xs rounded bg-emerald-900 text-emerald-200 font-semibold">{{ $link->status }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada slice yang dialokasikan.</div>
                        @endforelse
                    </div>
                </div>

                <div class="bg-gray-700/50 p-6 rounded-lg">
                    <h3 class="text-lg font-medium text-white mb-4">Alokasi 5G Slice Baru</h3>
                    <form action="{{ route('tlx.network.slices.provision') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-300">Pilih Backbone Link</label>
                            <select name="link_id" class="mt-1 w-full bg-gray-800 border border-gray-600 rounded px-3 py-2 text-sm text-white">
                                @foreach($links as $l)
                                    <option value="{{ $l->id }}">{{ $l->link_code }} (Kapasitas: {{ $l->bandwidth_capacity_gbps }} Gbps)</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-300">Nama Slice</label>
                            <input type="text" name="slice_name" required placeholder="URLLC-SmartFactory-01" class="mt-1 w-full bg-gray-800 border border-gray-600 rounded px-3 py-2 text-sm text-white">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-300">Enterprise Entity ID</label>
                            <input type="text" name="enterprise_entity_id" required value="TNT-ENT-001" class="mt-1 w-full bg-gray-800 border border-gray-600 rounded px-3 py-2 text-sm text-white">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-300">Alokasi Bandwidth (Gbps)</label>
                            <input type="number" step="0.1" name="slice_bandwidth_gbps" required min="0.1" value="2.0" class="mt-1 w-full bg-gray-800 border border-gray-600 rounded px-3 py-2 text-sm text-white">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-300">Biaya Bulanan (Minor IDR)</label>
                            <input type="number" name="monthly_fee_minor" required min="0" value="25000000" class="mt-1 w-full bg-gray-800 border border-gray-600 rounded px-3 py-2 text-sm text-white">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-sm font-semibold">
                            Provision 5G Slice
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
