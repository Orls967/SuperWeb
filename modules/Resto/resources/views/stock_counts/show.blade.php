<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('resto.stock-counts.index') }}" class="p-2 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-slate-900 transition-all">
                    &larr;
                </a>
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                        Stock Opname #OPN-{{ str_pad((string)$count->id, 5, '0', STR_PAD_LEFT) }}
                    </h2>
                    <p class="text-sm text-slate-500 mt-0.5">{{ $count->outlet?->name }} &bull; Tanggal {{ $count->date->format('d F Y') }}</p>
                </div>
            </div>

            <span class="px-3.5 py-1.5 rounded-full text-xs font-bold
                {{ $count->status->value === 'approved' ? 'bg-emerald-100 text-emerald-800' : '' }}
                {{ $count->status->value === 'pending_approval' ? 'bg-amber-100 text-amber-800 animate-pulse' : '' }}
                {{ $count->status->value === 'draft' ? 'bg-slate-100 text-slate-800' : '' }}
                {{ $count->status->value === 'rejected' ? 'bg-rose-100 text-rose-800' : '' }}">
                {{ $count->status->label() }}
            </span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm flex items-center justify-between">
                    <span>{{ session('success') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="font-bold text-emerald-500">&times;</button>
                </div>
            @endif

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Status Dokumen</span>
                    <h3 class="text-lg font-bold text-slate-800 mt-1">{{ $count->status->label() }}</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Tanggal: {{ $count->date->format('d/m/Y') }}</p>
                </div>

                <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Petugas Hitung & Approval</span>
                    <h3 class="text-sm font-bold text-slate-800 mt-1">Petugas: {{ $count->counter?->name }}</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Approved: {{ $count->approver?->name ?: 'Menunggu Approval Manager' }}</p>
                </div>

                <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Item Dihitung</span>
                    <h3 class="text-xl font-black text-slate-800 mt-1">{{ $count->lines->count() }} Bahan</h3>
                    <p class="text-xs text-slate-500 mt-0.5">{{ $count->lines->where('variance_qty', '!=', 0)->count() }} item mengalami selisih</p>
                </div>

                <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Nilai Selisih</span>
                    @php
                        $totalVarianceVal = $count->lines->sum('variance_value');
                    @endphp
                    <h3 class="text-xl font-black mt-1 {{ $totalVarianceVal < 0 ? 'text-rose-600' : ($totalVarianceVal > 0 ? 'text-emerald-600' : 'text-slate-800') }}">
                        Rp {{ number_format($totalVarianceVal, 0, ',', '.') }}
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Selisih minus = kerugian susut/waste</p>
                </div>
            </div>

            <!-- Notes -->
            @if($count->notes)
                <div class="p-4 bg-amber-50/70 rounded-2xl border border-amber-200 text-xs text-amber-900">
                    <span class="font-bold">Catatan Opname:</span> {{ $count->notes }}
                </div>
            @endif

            <!-- Lines Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-base text-slate-800">Rincian Fisik vs Saldo Sistem</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Selisih kuantitas dan kalkulasi nilai persediaan</p>
                    </div>

                    @if($count->status->value === 'pending_approval' || $count->status->value === 'draft')
                        <form method="POST" action="{{ route('resto.stock-counts.approve', $count) }}" onsubmit="return confirm('Apakah Anda yakin ingin menyetujui Stock Opname ini? Saldo sistem dan jurnal penyesuaian akan diposting secara permanen.')">
                            @csrf
                            <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                <span>Setujui & Posting Jurnal Penyesuaian</span>
                            </button>
                        </form>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/60 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                <th class="p-4">SKU & Bahan Baku</th>
                                <th class="p-4 text-right">Saldo Sistem</th>
                                <th class="p-4 text-right">Hasil Fisik</th>
                                <th class="p-4 text-right">Selisih Fisik</th>
                                <th class="p-4 text-right">HPP Satuan</th>
                                <th class="p-4 text-right">Nilai Selisih</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($count->lines as $line)
                                @php
                                    $diff = (float)$line->variance_qty;
                                @endphp
                                <tr class="hover:bg-slate-50/60 transition-colors">
                                    <td class="p-4">
                                        <div class="font-bold text-slate-800">{{ $line->ingredient?->name }}</div>
                                        <div class="text-[11px] text-slate-400 font-mono">{{ $line->ingredient?->sku }} &bull; {{ $line->ingredient?->base_unit }}</div>
                                    </td>
                                    <td class="p-4 text-right font-medium text-slate-600">
                                        {{ number_format((float)$line->system_qty, 2) }} {{ $line->ingredient?->base_unit }}
                                    </td>
                                    <td class="p-4 text-right font-bold text-slate-800">
                                        {{ number_format((float)$line->counted_qty, 2) }} {{ $line->ingredient?->base_unit }}
                                    </td>
                                    <td class="p-4 text-right font-bold {{ $diff < 0 ? 'text-rose-600' : ($diff > 0 ? 'text-emerald-600' : 'text-slate-400') }}">
                                        {{ $diff > 0 ? '+' : '' }}{{ number_format($diff, 2) }} {{ $line->ingredient?->base_unit }}
                                    </td>
                                    <td class="p-4 text-right font-mono text-xs text-slate-600">
                                        Rp {{ number_format((float)$line->unit_cost, 2, ',', '.') }}
                                    </td>
                                    <td class="p-4 text-right font-black {{ $line->variance_value < 0 ? 'text-rose-600' : ($line->variance_value > 0 ? 'text-emerald-600' : 'text-slate-400') }}">
                                        Rp {{ number_format($line->variance_value, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-slate-400 text-sm italic">
                                        Tidak ada baris bahan baku pada sesi opname ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="border-t-2 border-slate-200 bg-slate-50 font-bold text-sm">
                            <tr>
                                <td colspan="5" class="p-4 text-right text-slate-700 uppercase tracking-wider text-xs">Total Dampak Nilai ke Buku:</td>
                                <td class="p-4 text-right font-black text-base {{ $totalVarianceVal < 0 ? 'text-rose-600' : ($totalVarianceVal > 0 ? 'text-emerald-600' : 'text-slate-800') }}">
                                    Rp {{ number_format($totalVarianceVal, 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
