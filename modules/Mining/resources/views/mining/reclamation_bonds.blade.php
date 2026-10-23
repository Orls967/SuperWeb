@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>🌱</span> Mine Reclamation Provisions &amp; Guarantee Bonds
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Fase 94: Post-mining rehabilitation escrow guarantee bonding and satellite NDVI canopy verification.
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('mining.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-sm font-semibold rounded-lg text-white transition">
                        Back to Mining Hub
                    </a>
                </div>
            </div>

            @if(session('success'))
                <div class="mb-4 p-4 bg-emerald-900/60 border border-emerald-500 rounded-lg text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Post &amp; Evaluate Reclamation Bond</h3>
                    <form action="{{ route('mining.operations.reclamation.bond') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Target Mining Concession Site</label>
                            <select name="site_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                @foreach($sites as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->commodity }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Reclamation Plan Code</label>
                            <input type="text" name="plan_code" value="REC-{{ date('Y') }}-{{ strtoupper(substr(uniqid(), -4)) }}" maxlength="32" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Target Rehabilitation Area (Hectares)</label>
                            <input type="number" step="0.1" name="target_hectares" value="25.0" min="0.1" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Escrow Provision Accrued (IDR)</label>
                            <input type="number" name="provision_accrued_idr" value="750000000" min="1000000" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Satellite Verified Canopy NDVI (0.00 - 1.00)</label>
                            <input type="number" step="0.001" name="verified_growth_ndvi" value="0.680" min="0" max="1" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                            <p class="text-[11px] text-gray-400 mt-1">Release threshold: NDVI &ge; 0.650 triggers 80% statutory bond return.</p>
                        </div>

                        <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 font-semibold rounded text-white text-sm transition">
                            Evaluate Bond Guarantee
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-2 bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Reclamation Provisions Portfolio</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-300">
                            <thead class="bg-gray-800 text-gray-400 uppercase font-semibold">
                                <tr>
                                    <th class="p-3">Plan Code</th>
                                    <th class="p-3">Site</th>
                                    <th class="p-3">Area (Ha)</th>
                                    <th class="p-3">Accrued (IDR)</th>
                                    <th class="p-3">NDVI</th>
                                    <th class="p-3">Released (IDR)</th>
                                    <th class="p-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800 font-mono">
                                @forelse($provisions as $p)
                                    <tr class="hover:bg-gray-800/50">
                                        <td class="p-3 font-semibold text-white">{{ $p->plan_code }}</td>
                                        <td class="p-3">{{ $p->site->name ?? substr($p->site_id, 0, 8) }}</td>
                                        <td class="p-3 text-cyan-400">{{ number_format($p->target_hectares, 1) }}</td>
                                        <td class="p-3">IDR {{ number_format($p->provision_accrued_idr) }}</td>
                                        <td class="p-3 font-bold {{ $p->verified_growth_ndvi >= 0.65 ? 'text-emerald-400' : 'text-amber-400' }}">
                                            {{ number_format($p->verified_growth_ndvi, 3) }}
                                        </td>
                                        <td class="p-3 text-emerald-400 font-bold">IDR {{ number_format($p->provision_released_idr) }}</td>
                                        <td class="p-3">
                                            @if($p->status === 'RELEASED')
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-950 text-emerald-400 border border-emerald-800">
                                                    RELEASED
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-950 text-amber-400 border border-amber-800">
                                                    {{ $p->status }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="p-4 text-center text-gray-500">No reclamation provisions registered yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $provisions->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
