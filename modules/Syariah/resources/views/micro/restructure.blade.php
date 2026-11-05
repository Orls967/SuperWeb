@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('syariah.micro.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Kembali ke Mikro</a>
                <span class="text-slate-300">/</span>
                <span class="font-mono text-sm text-emerald-700">Restrukturisasi</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 mt-1">Restrukturisasi Pembiayaan & Moral Hazard Guard (G7-D9)</h1>
            <p class="text-sm text-slate-600 mt-1">Pemisahan kerugian non-kelalaian (ditanggung modal shohibul maal) vs kelalaian/ta'addi (ditanggung pengelola mudharib).</p>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-6 mb-8">
        <h2 class="text-lg font-bold text-slate-900 mb-4">Daftar Restrukturisasi Pembiayaan Mikro</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Kode Restruktur</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Kode Fasilitas</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Klasifikasi Kerugian</th>
                        <th class="px-4 py-2 text-center font-semibold text-slate-700">Moral Hazard</th>
                        <th class="px-4 py-2 text-right font-semibold text-slate-700">Plafon Direstruktur</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Disetujui Oleh</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($restructurings ?? [] as $rst)
                        <tr>
                            <td class="px-4 py-2 font-mono text-emerald-700">{{ $rst->restruct_code }}</td>
                            <td class="px-4 py-2 font-mono text-slate-700">{{ $rst->facility_code }}</td>
                            <td class="px-4 py-2 text-slate-900 font-medium">{{ $rst->loss_type }}</td>
                            <td class="px-4 py-2 text-center">
                                @if($rst->moral_hazard_detected)
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-rose-100 text-rose-800">Ya (Kelalaian)</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800">Tidak (Risiko Murni)</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right font-mono font-medium text-slate-900">Rp {{ number_format($rst->restructured_principal_idr / 100, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ $rst->approved_by }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-4 text-center text-slate-500">Belum ada catatan restrukturisasi pembiayaan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
