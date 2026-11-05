@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">BMT & Koperasi Syariah (G7-D10)</h1>
            <p class="text-sm text-slate-600 mt-1">Digitalisasi Baitul Maal wat Tamwil: registrasi lembaga, simpanan wadiah yad dhamanah, mudharabah, dan tata kelola anti-riba.</p>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 overflow-x-auto shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Kode BMT</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Nama Lembaga</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Kota</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Penasihat DPS</th>
                    <th class="px-4 py-3 text-center font-semibold text-slate-700">Rekening Simpanan</th>
                    <th class="px-4 py-3 text-center font-semibold text-slate-700">Kelompok Mikro</th>
                    <th class="px-4 py-3 text-center font-semibold text-slate-700">Status</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($cooperatives as $coop)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-mono font-medium text-emerald-700">{{ $coop->cooperative_code }}</td>
                        <td class="px-4 py-3 text-slate-900 font-medium">{{ $coop->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $coop->city }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $coop->dps_advisor }}</td>
                        <td class="px-4 py-3 text-center font-mono text-slate-700">{{ $coop->savings_count ?? 0 }}</td>
                        <td class="px-4 py-3 text-center font-mono text-slate-700">{{ $coop->group_financings_count ?? 0 }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($coop->is_active)
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">Aktif</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">Non-Aktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('syariah.bmt.show', $coop->id) }}" class="text-emerald-600 hover:text-emerald-800 font-medium">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-slate-500">Belum ada BMT/koperasi syariah terdaftar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

