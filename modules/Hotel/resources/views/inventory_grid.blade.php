@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="border-b border-slate-200 pb-4">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Grid Inventori 30 Hari & Kapasitas CRS</h1>
        <p class="text-sm text-slate-500">Matriks alokasi inventori kamar terpusat dengan pessimistic lock.</p>
    </div>

    <div class="bg-white shadow-sm rounded-lg border border-slate-200 overflow-x-auto p-4">
        <table class="min-w-full divide-y divide-slate-200 text-xs">
            <thead class="bg-slate-50 text-slate-700">
                <tr>
                    <th class="py-3 px-3 text-left font-semibold sticky left-0 bg-slate-50 z-10">Tipe Kamar</th>
                    @foreach($days as $d)
                        <th class="py-3 px-2 text-center font-medium">{{ \Carbon\CarbonImmutable::parse($d)->format('d M') }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @foreach($roomTypes as $rt)
                    <tr>
                        <td class="py-3 px-3 font-semibold text-slate-900 sticky left-0 bg-white z-10">{{ $rt->name }}</td>
                        @foreach($days as $d)
                            <td class="py-3 px-2 text-center tabular-nums text-slate-600 bg-emerald-50/30">
                                <span class="font-bold text-emerald-700">10</span> / 10
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
