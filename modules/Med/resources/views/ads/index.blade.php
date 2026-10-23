@extends('layouts.app')

@section('content')
    <x-enterprise.module-dashboard module="Med" />

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Programmatic Ad Inventory Campaigns</h2>
            <p class="mt-1 text-sm text-gray-400">Pemesanan Inventori Iklan Programatik, Yield CPM & Bagi Hasil Publisher</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 bg-gray-700/50 p-6 rounded-lg">
                <h3 class="text-md font-medium text-white mb-4">Pemesanan Kampanye Iklan Baru</h3>
                <form action="{{ route('med.ads.book') }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-300 mb-1">Advertiser Entity ID</label>
                        <input type="text" name="advertiser_entity_id" required class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm" placeholder="ADV-TELCO-01">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-300 mb-1">Kanal Distribusi</label>
                        <select name="channel_id" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm">
                            @foreach($channels as $channel)
                                <option value="{{ $channel->id }}">{{ $channel->name }} ({{ $channel->channel_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-300 mb-1">Target Impresi</label>
                        <input type="number" name="target_impressions" value="50000" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-300 mb-1">CPM Rate (Rp)</label>
                        <input type="number" name="actual_cpm_minor" value="65000" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm">
                    </div>
                    <div class="md:col-span-4 flex justify-end">
                        <button type="submit" class="py-2 px-6 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded transition">
                            Pesan Kampanye Programatik
                        </button>
                    </div>
                </form>
            </div>

            <div class="mt-6 space-y-2">
                <h3 class="text-md font-medium text-white mb-2">Kampanye Iklan Terjadwal ({{ $campaigns->count() }})</h3>
                @forelse($campaigns as $camp)
                    <div class="p-3 bg-gray-700/50 rounded border border-gray-600 flex justify-between items-center text-xs">
                        <div>
                            <span class="font-mono text-white font-medium">{{ $camp->campaign_code }}</span>
                            <span class="text-gray-400 ml-2">Pengiklan: {{ $camp->advertiser_entity_id }} • Target: {{ number_format($camp->target_impressions) }} impresi</span>
                        </div>
                        <div class="text-right">
                            <span class="text-emerald-400 font-semibold">Total: Rp {{ number_format($camp->total_spend_minor, 0, ',', '.') }}</span>
                            <div class="text-gray-400">Publisher: Rp {{ number_format($camp->publisher_share_minor, 0, ',', '.') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="text-gray-400 text-sm">Belum ada kampanye iklan.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
