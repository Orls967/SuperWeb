@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('syariah.social.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Kembali ke Dampak Sosial</a>
                <span class="text-slate-300">/</span>
                <span class="font-mono text-sm text-emerald-700">Graduasi</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 mt-1">Pelacak Graduasi Mustahik Menjadi Muzakki Mandiri</h1>
            <p class="text-sm text-slate-600 mt-1">Transformasi bertahap: Mustahik Prasejahtera &rarr; Mikro Pemula &rarr; Mikro Bertumbuh &rarr; Muzakki Mandiri Pembayar Zakat.</p>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-6 mb-8">
        <h2 class="text-lg font-bold text-slate-900 mb-4">Daftar Pelaku Usaha Mustahik Tergraduasi</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Kode Asesmen</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Party Subjek</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Tahap Sekarang</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Target Tahap</th>
                        <th class="px-4 py-2 text-right font-semibold text-slate-700">Pertumbuhan Omzet</th>
                        <th class="px-4 py-2 text-center font-semibold text-slate-700">Status Kelulusan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($graduations ?? [] as $grd)
                        <tr>
                            <td class="px-4 py-2 font-mono text-emerald-700">{{ $grd->graduation_code }}</td>
                            <td class="px-4 py-2 font-mono text-slate-700">{{ $grd->party_ref }}</td>
                            <td class="px-4 py-2 text-slate-700">{{ $grd->current_stage }}</td>
                            <td class="px-4 py-2 font-medium text-emerald-700">{{ $grd->target_stage }}</td>
                            <td class="px-4 py-2 text-right font-mono font-bold text-emerald-600">+{{ number_format($grd->growth_rate_bps / 100, 1) }}%</td>
                            <td class="px-4 py-2 text-center">
                                @if($grd->status === 'GRADUATED')
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800">Lulus Muzakki</span>
                                @elseif($grd->status === 'REJECTED')
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-rose-100 text-rose-800">Belum Memenuhi</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800">Menunggu Supervisi</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-4 text-center text-slate-500">Belum ada asesmen graduasi tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
