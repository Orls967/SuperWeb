@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Wakaf Uang & Filantropi Abadi (D162-5)</h1>
            <p class="text-sm text-slate-600 mt-1">Pengelolaan wakaf uang produktif dengan pokok abadi tak berkurang (immutable principal), penyaluran surplus manfaat mauquf 'alaih, dan sertifikat AIW gapless.</p>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden mb-8">
        <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
            <h3 class="font-semibold text-slate-900 text-sm">Daftar Pokok Wakaf Abadi Diterima</h3>
            <span class="text-xs text-slate-500">{{ $principals->total() }} Wakif</span>
        </div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Wakif</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Tujuan / Mauquf 'Alaih</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">Pokok Awal</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">Pokok Berjalan</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">Manfaat Disalurkan</th>
                    <th class="px-4 py-3 text-center font-semibold text-slate-700">Status</th>
                    <th class="px-4 py-3 text-center font-semibold text-slate-700">Tanggal Akad</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($principals as $p)
                <tr class="hover:bg-slate-50/50">
                    <td class="px-4 py-3 font-medium text-slate-900">{{ $p->wakif?->name ?? "User #{$p->wakif_user_id}" }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $p->purpose }}</td>
                    <td class="px-4 py-3 text-right font-mono text-xs text-slate-700">Rp {{ number_format($p->original_principal_idr) }}</td>
                    <td class="px-4 py-3 text-right font-mono text-xs font-bold text-emerald-700">Rp {{ number_format($p->current_principal_idr) }}</td>
                    <td class="px-4 py-3 text-right font-mono text-xs text-blue-700">Rp {{ number_format($p->accumulated_benefits_distributed_idr) }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800">
                            {{ $p->status }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center text-xs text-slate-500">{{ $p->created_at?->format('d/m/Y') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">Belum ada pokok wakaf uang tercatat.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @if(method_exists($principals, 'links'))
        <div class="px-4 py-3 border-t border-slate-200">
            {{ $principals->links() }}
        </div>
        @endif
    </div>
</div>
@endsection

