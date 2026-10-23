@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Renewable Energy Certificates (REC)</h2>
            <p class="mt-1 text-sm text-gray-400">Penerbitan Kredensial Hijau & Sertifikat Energi Terbarukan Terverifikasi</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 bg-gray-700/50 p-6 rounded-lg">
                <h3 class="text-md font-medium text-white mb-4">Penerbitan Sertifikat REC Baru</h3>
                <form action="{{ route('egy.rec.mint') }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-300 mb-1">Asset Pembangkit (EBT)</label>
                        <input type="text" name="generation_asset_id" required class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm" placeholder="SOLAR-FARM-01">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-300 mb-1">MWh Dihasilkan</label>
                        <input type="number" name="mwh_generated" required class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm" placeholder="50">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-300 mb-1">Vintage Year</label>
                        <input type="number" name="vintage_year" value="2026" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full py-2 px-4 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded transition">
                            Terbitkan REC
                        </button>
                    </div>
                </form>
            </div>

            <div class="mt-6 space-y-3">
                <h3 class="text-md font-medium text-white">Daftar Sertifikat REC Terdaftar ({{ $recs->count() }})</h3>
                @forelse($recs as $rec)
                    <div class="p-3 bg-gray-700/50 rounded border border-gray-600 flex justify-between items-center text-xs">
                        <div>
                            <div class="text-white font-medium">{{ $rec->certificate_code }}</div>
                            <div class="text-gray-400">Pembangkit: {{ $rec->generation_asset_id }} • Registry Ref: {{ $rec->registry_reference }}</div>
                        </div>
                        <div class="text-right">
                            <span class="text-emerald-400 font-bold text-sm">{{ $rec->mwh_generated }} MWh</span>
                            <div class="text-gray-400">Vintage: {{ $rec->vintage_year }}</div>
                        </div>
                    </div>
                @empty
                    <div class="text-gray-400 text-sm">Belum ada REC diterbitkan.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
