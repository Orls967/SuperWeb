@extends('layouts.app')

@section('title', $asset->asset_number)
@section('subtitle', $asset->name)

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-white">{{ $asset->name }}</h1>
                <span class="text-xs font-mono text-indigo-300 bg-indigo-500/10 px-2 py-1 rounded">{{ $asset->asset_number }}</span>
                <span class="text-xs font-mono text-slate-400 bg-slate-700/50 px-2 py-1 rounded">QR: {{ $asset->asset_tag }}</span>
            </div>
            <p class="text-sm text-slate-400 mt-2">{{ $asset->category?->name }} · {{ $asset->location?->name ?? 'Lokasi belum ditetapkan' }} · kondisi {{ $asset->condition }}</p>
        </div>
        <a href="{{ route('asset.index') }}" class="text-sm text-indigo-400 hover:text-indigo-300">← Register</a>
    </div>

    @if(session('success')) <div class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-lg text-emerald-300 text-sm">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="mb-4 p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-red-300 text-sm">{{ session('error') }}</div> @endif

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4"><p class="text-xs text-slate-400">Biaya Perolehan + Landed</p><p class="text-lg text-white font-semibold mt-1">{{ number_format($asset->acquisition_cost_idr + $asset->landed_cost_idr) }} IDR</p></div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4"><p class="text-xs text-slate-400">Akumulasi Penyusutan</p><p class="text-lg text-amber-300 font-semibold mt-1">{{ number_format($asset->accumulated_depreciation_idr) }} IDR</p></div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4"><p class="text-xs text-slate-400">Book Value</p><p class="text-lg text-emerald-300 font-semibold mt-1">{{ number_format($asset->book_value_idr) }} IDR</p></div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4"><p class="text-xs text-slate-400">Status / Rantai</p><p class="text-lg text-white font-semibold mt-1">{{ $asset->status->label() }} · {{ $asset->events->count() }} event</p></div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
        {{-- Mutasi lokasi --}}
        <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <h2 class="font-semibold text-white mb-3">📍 Mutasi Lokasi (approval)</h2>
            <form method="POST" action="{{ route('asset.move', $asset) }}" class="flex gap-2">
                @csrf
                <select name="location_id" required class="flex-1 px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" @selected($asset->location_id === $loc->id)>{{ $loc->name }} ({{ $loc->level }})</option>
                    @endforeach
                </select>
                <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm">Ajukan</button>
            </form>
            <p class="text-xs text-slate-500 mt-2">Mutasi lokasi memerlukan persetujuan admin (four-eyes).</p>
        </section>

        {{-- Stok opname --}}
        <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <h2 class="font-semibold text-white mb-3">🔎 Stok Opname / Scan Tag</h2>
            <form method="POST" action="{{ route('asset.scan') }}" class="grid grid-cols-2 gap-2">
                @csrf
                <input type="hidden" name="tag" value="{{ $asset->asset_tag }}">
                <input name="cycle_id" type="number" min="1" placeholder="ID siklus opname" required class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <select name="result" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    <option value="found">Ditemukan</option><option value="missing">Hilang</option><option value="unexpected">Tidak terdaftar di lokasi</option>
                </select>
                <input name="note" placeholder="Catatan" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <button class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-lg text-sm">Catat Hasil</button>
            </form>
            <p class="text-xs text-slate-500 mt-2">Selisih stok opname ditandai menunggu approval, tidak langsung mengubah ledger.</p>
        </section>

        {{-- Check-out --}}
        <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <h2 class="font-semibold text-white mb-3">📤 Penugasan / Check-out</h2>
            <form method="POST" action="{{ route('asset.checkout', $asset) }}" class="space-y-2">
                @csrf
                <select name="assigned_to_user_id" required class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    @foreach(\App\Models\User::orderBy('name')->get() as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
                <input name="purpose" placeholder="Tujuan peminjaman" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="condition_out" placeholder="Kondisi saat keluar" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm">Check-out</button>
            </form>
        </section>

        {{-- Asuransi --}}
        <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <h2 class="font-semibold text-white mb-3">🛡️ Polis Asuransi</h2>
            <form method="POST" action="{{ route('asset.insurance.store', $asset) }}" class="grid grid-cols-2 gap-2">
                @csrf
                <input name="policy_number" placeholder="Nomor polis" required class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="provider" placeholder="Perusahaan asuransi" required class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="coverage_amount_idr" type="number" min="1" placeholder="Nilai pertanggungan" required class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="annual_premium_idr" type="number" min="0" placeholder="Premi tahunan" required class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="start_date" type="date" required class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="end_date" type="date" required class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <button class="col-span-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm">Daftarkan Polis</button>
            </form>
        </section>
    </div>

    {{-- Open assignments --}}
    @if($openAssignments->isNotEmpty())
        <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 mb-6">
            <h2 class="font-semibold text-white mb-3">Aset sedang dipinjam</h2>
            @foreach($openAssignments as $assignment)
                <form method="POST" action="{{ route('asset.checkin', [$asset, $assignment]) }}" class="flex flex-wrap items-center gap-3 mb-2">
                    @csrf
                    <span class="text-sm text-slate-300">{{ $assignment->assignedTo?->name }} · keluar {{ $assignment->checked_out_at->format('d M Y H:i') }} · {{ $assignment->purpose }}</span>
                    <input name="condition_in" placeholder="Kondisi kembali" class="px-2 py-1 bg-slate-900 border border-slate-600 rounded text-white text-xs">
                    <button class="px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded text-xs">Check-in</button>
                </form>
            @endforeach
        </section>
    @endif

    {{-- Event chain --}}
    <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-white">🔗 Riwayat Aset (SHA-256 Hash Chain)</h2>
            <form method="POST" action="{{ route('asset.verify-chain') }}">@csrf<button class="text-xs text-indigo-300 hover:text-indigo-200">Verify seluruh chain</button></form>
        </div>
        <div class="space-y-2">
            @forelse($asset->events->sortByDesc('sequence') as $event)
                <div class="border-l-2 border-indigo-500 pl-4 py-1">
                    <div class="flex flex-wrap gap-2 items-center">
                        <span class="text-xs font-mono text-indigo-300">#{{ $event->sequence }}</span>
                        <span class="text-xs uppercase text-slate-300">{{ $event->event_type->label() }}</span>
                        <span class="text-xs text-slate-500">{{ $event->occurred_at->format('d M Y H:i:s') }}</span>
                    </div>
                    <p class="text-[10px] font-mono text-slate-600 truncate">prev {{ substr($event->prev_hash, 0, 20) }}… → {{ substr($event->hash, 0, 20) }}…</p>
                    <p class="text-xs text-slate-400">{{ json_encode($event->payload, JSON_UNESCAPED_UNICODE) }}</p>
                </div>
            @empty
                <p class="text-sm text-slate-500">Belum ada event.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
