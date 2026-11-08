@props([
    'items' => [],
    'totalCogs' => 0
])

<div class="overflow-x-auto rounded-xl border border-slate-700 bg-slate-900/60">
    <table class="w-full text-left text-sm text-slate-300">
        <thead class="bg-slate-800 text-xs uppercase font-semibold text-slate-400 border-b border-slate-700">
            <tr>
                <th class="p-3">Nama Bahan Baku</th>
                <th class="p-3 text-center">Tipe Resep</th>
                <th class="p-3 text-right">Kuantitas Kotor</th>
                <th class="p-3 text-center">Satuan</th>
                <th class="p-3 text-right">Subtotal Biaya (COGS)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-800 font-mono text-xs">
            @forelse($items as $item)
                <tr class="hover:bg-slate-800/40 transition">
                    <td class="p-3 font-sans font-medium text-white">{{ $item['name'] ?? '-' }}</td>
                    <td class="p-3 text-center font-sans">
                        <span class="px-2 py-0.5 text-[10px] rounded {{ ($item['type'] ?? 'food') === 'food' ? 'bg-amber-500/10 text-amber-300 border border-amber-500/20' : 'bg-blue-500/10 text-blue-300 border border-blue-500/20' }}">
                            {{ strtoupper($item['type'] ?? 'food') }}
                        </span>
                    </td>
                    <td class="p-3 text-right font-bold text-slate-200">{{ number_format($item['gross_qty'] ?? 0, 2) }}</td>
                    <td class="p-3 text-center text-slate-400">{{ $item['unit'] ?? 'unit' }}</td>
                    <td class="p-3 text-right text-emerald-400 font-bold">IDR {{ number_format($item['line_cost_idr'] ?? 0, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="p-4 text-center text-slate-400 font-sans">Belum ada rincian BOM resep yang dimasukkan.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot class="border-t border-slate-700 bg-slate-800/80 font-bold text-xs">
            <tr>
                <td colspan="4" class="p-3 text-right font-sans text-slate-300">Total Akumulasi Biaya Bahan Pokok (COGS):</td>
                <td class="p-3 text-right text-emerald-400 font-mono">IDR {{ number_format($totalCogs, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>
</div>
