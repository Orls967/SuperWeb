@extends('layouts.app')

@section('title', 'Fleet Excess Mileage & Wear-and-Tear Billing')
@section('subtitle', 'Audit Odometer Unit Sewa, Denda Kelebihan Kilometer & Estimasi Biaya Pemulihan Aset')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
        <h3 class="text-base font-bold text-slate-100 mb-4">Daftar Unit Sewa & Audit Odometer</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="text-xs uppercase bg-slate-900/50 text-slate-400 border-b border-slate-700">
                    <tr>
                        <th class="p-3">Unit ID</th>
                        <th class="p-3">Kontrak</th>
                        <th class="p-3">Odo Awal</th>
                        <th class="p-3">Odo Saat Ini</th>
                        <th class="p-3">Batas Maks/Thn</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Audit & Tagih</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($units as $u)
                        <tr class="hover:bg-slate-800/30">
                            <td class="p-3 font-mono font-bold text-violet-400">UNIT-{{ $u->id }}</td>
                            <td class="p-3 text-xs">{{ optional($u->contract)->contract_number ?? '-' }}</td>
                            <td class="p-3 font-mono">{{ number_format($u->baseline_odometer_km) }} km</td>
                            <td class="p-3 font-mono text-slate-100">{{ number_format($u->current_odometer_km) }} km</td>
                            <td class="p-3 font-mono text-slate-400">{{ number_format($u->max_km_per_year) }} km</td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 text-xs font-bold rounded bg-slate-700 text-slate-300">
                                    {{ $u->status }}
                                </span>
                            </td>
                            <td class="p-3">
                                <form action="{{ route('fleet.lifecycle.bill-excess') }}" method="POST" class="inline-flex gap-1 items-center">
                                    @csrf
                                    <input type="hidden" name="unit_id" value="{{ $u->id }}">
                                    <input type="hidden" name="actual_odometer" value="{{ $u->current_odometer_km + 5000 }}">
                                    <input type="hidden" name="allowed_km_cap" value="{{ $u->max_km_per_year }}">
                                    <button type="submit" class="px-2.5 py-1 bg-rose-600 hover:bg-rose-500 text-white rounded-lg text-xs font-semibold">
                                        Tagih Kelebihan
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-500">Belum ada unit terdaftar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $units->links() }}
        </div>
    </div>
</div>
@endsection
