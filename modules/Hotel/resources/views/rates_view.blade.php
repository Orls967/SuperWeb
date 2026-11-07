@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="md:flex md:items-center md:justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Manajemen Tarif Best Available Rate (BAR)</h1>
            <p class="text-sm text-slate-500">Penetapan harga dinamis H2 dengan batas floor dan ceiling guardrail.</p>
        </div>
        <div class="mt-4 md:mt-0">
            <form method="POST" action="{{ route('hotel.rates.publish') }}" class="flex gap-2">
                @csrf
                <input type="hidden" name="property_id" value="{{ $selectedPropertyId }}">
                <input type="date" name="from_date" value="{{ date('Y-m-d') }}" class="text-xs rounded-md border-slate-300">
                <input type="date" name="to_date" value="{{ date('Y-m-d', strtotime('+7 days')) }}" class="text-xs rounded-md border-slate-300">
                <button type="submit" class="rounded bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-sky-500">
                    Publish Rate H2
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-md bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <div class="bg-white shadow-sm rounded-lg border border-slate-200 overflow-hidden">
        <div class="px-4 py-4 sm:px-6 border-b border-slate-100 font-semibold text-slate-900">
            Daftar Versi Tarif Aktif Terpublikasi
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-700">
                    <tr>
                        <th class="py-3 px-4 text-left font-semibold">Tanggal Bisnis</th>
                        <th class="py-3 px-4 text-left font-semibold">Tipe Kamar</th>
                        <th class="py-3 px-4 text-right font-semibold">Tarif Dasar</th>
                        <th class="py-3 px-4 text-right font-semibold">Tarif BAR H2</th>
                        <th class="py-3 px-4 text-center font-semibold">Status Versi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($publishedRates as $rate)
                        <tr>
                            <td class="py-3 px-4 font-mono text-slate-900">{{ $rate->business_date->format('Y-m-d') }}</td>
                            <td class="py-3 px-4 text-slate-700">{{ $rate->roomType->name ?? '-' }}</td>
                            <td class="py-3 px-4 text-right tabular-nums text-slate-500">Rp {{ number_format($rate->base_rate_idr, 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right tabular-nums font-bold text-slate-900">Rp {{ number_format($rate->calculated_bar_rate_idr, 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-center">
                                <span class="rounded bg-emerald-100 text-emerald-800 px-2 py-0.5 text-xs font-medium uppercase">{{ $rate->status }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-sm text-slate-500">Belum ada versi tarif terpublikasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-200">
            {{ $publishedRates->links() }}
        </div>
    </div>
</div>
@endsection
