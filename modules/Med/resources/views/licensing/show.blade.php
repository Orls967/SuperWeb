@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-white">Lisensi IP: {{ $ip->title }} ({{ $ip->ip_code }})</h2>
                    <p class="mt-1 text-sm text-gray-400">Tipe: {{ $ip->ip_type }} • Pemilik: {{ $ip->owner_entity_id }}</p>
                </div>
                <a href="{{ route('med.licensing.index') }}" class="text-xs text-gray-400 hover:text-white">&larr; Kembali</a>
            </div>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 bg-gray-700/50 p-6 rounded-lg">
                <h3 class="text-md font-medium text-white mb-4">Terbitkan Lisensi Teritorial Baru</h3>
                <form action="{{ route('med.licensing.grant') }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    @csrf
                    <input type="hidden" name="ip_id" value="{{ $ip->id }}">
                    <div>
                        <label class="block text-xs text-gray-300 mb-1">Licensee Entity ID</label>
                        <input type="text" name="licensee_entity_id" required class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm" placeholder="PTY-STREAM-NETFLIX">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-300 mb-1">Kanal Distribusi</label>
                        <select name="channel" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm">
                            <option value="STREAMING">STREAMING</option>
                            <option value="HOTEL_IN_ROOM">HOTEL_IN_ROOM</option>
                            <option value="STORE_MERCH">STORE_MERCH</option>
                            <option value="VENUE">VENUE</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-300 mb-1">Teritori</label>
                        <input type="text" name="territory" value="GLOBAL" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-300 mb-1">Minimum Guarantee (Rp)</label>
                        <input type="number" name="minimum_guarantee_minor" value="50000000" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm">
                    </div>
                    <div class="md:col-span-4 flex justify-end">
                        <button type="submit" class="py-2 px-6 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded transition">
                            Terbitkan Lisensi Konten
                        </button>
                    </div>
                </form>
            </div>

            <div class="mt-6 space-y-2">
                <h3 class="text-md font-medium text-white mb-2">Lisensi Aktif Terbit ({{ $licenses->count() }})</h3>
                @forelse($licenses as $lic)
                    <div class="p-3 bg-gray-700/50 rounded border border-gray-600 flex justify-between items-center text-xs">
                        <div>
                            <span class="font-mono text-white font-medium">{{ $lic->license_code }}</span>
                            <span class="text-gray-400 ml-2">Licensee: {{ $lic->licensee_entity_id }} • Teritori: {{ $lic->territory }} ({{ $lic->channel }})</span>
                        </div>
                        <span class="text-emerald-400 font-semibold">MG: Rp {{ number_format($lic->minimum_guarantee_minor, 0, ',', '.') }}</span>
                    </div>
                @empty
                    <div class="text-gray-400 text-sm">Belum ada lisensi terbit untuk IP ini.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
