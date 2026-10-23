@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Telecom & 5G Network Management</h2>
            <p class="mt-1 text-sm text-gray-400">Monitoring Infrastruktur Seluler, 5G Network Slices & IoT SIM Subscriptions</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Base Stations & Sites -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Telecom Sites ({{ $sites->count() }})</h3>
                    <div class="space-y-2">
                        @forelse($sites as $site)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600">
                                <div class="text-white font-medium">{{ $site->site_code }} - {{ $site->name }}</div>
                                <div class="text-xs text-gray-400">Region: {{ $site->region }} • Tipe: {{ $site->tower_type }}</div>
                                <div class="text-xs text-emerald-400 mt-1">Status: {{ $site->status }}</div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada tower site aktif.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Network Links & Slices -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-md font-medium text-gray-200">5G Slices & Links ({{ $links->count() }})</h3>
                        <a href="{{ route('tlx.network.slices') }}" class="text-xs text-indigo-400 hover:underline">Kelola &rarr;</a>
                    </div>
                    <div class="space-y-2">
                        @forelse($links as $link)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600">
                                <div class="text-white font-mono text-sm">{{ $link->link_code }}</div>
                                <div class="text-xs text-gray-400">Bandwidth: {{ $link->bandwidth_capacity_mbps }} Mbps • Tipe: {{ $link->link_type }}</div>
                                <div class="text-xs text-cyan-400 mt-1">Status: {{ $link->status }}</div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada link/slice aktif.</div>
                        @endforelse
                    </div>
                </div>

                <!-- IoT SIM & eSIM Provisioning -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Aktivasi Bulk eSIM IoT</h3>
                    <form action="{{ route('tlx.network.esim.activate') }}" method="POST" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-300">Tenant Enterprise ID</label>
                            <input type="text" name="client_tenant_id" required value="TNT-SMARTCITY-01" class="mt-1 w-full bg-gray-800 border border-gray-600 rounded px-3 py-1.5 text-sm text-white">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-300">Profil eSIM</label>
                            <select name="profile_type" class="mt-1 w-full bg-gray-800 border border-gray-600 rounded px-3 py-1.5 text-sm text-white">
                                <option value="IOT_TELEMETRY">Industrial IoT Telemetry</option>
                                <option value="FLEET_TRACKING">Fleet GPS Tracker</option>
                                <option value="SMART_METER">Smart Grid Meter</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-300">Jumlah Unit</label>
                            <input type="number" name="count" required min="1" max="100" value="10" class="mt-1 w-full bg-gray-800 border border-gray-600 rounded px-3 py-1.5 text-sm text-white">
                        </div>
                        <button type="submit" class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-xs font-semibold">
                            Aktivasi Profil eSIM
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
