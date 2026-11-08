@props([
    'name',
    'type' => 'food',
    'netPortion',
    'yieldLoss',
    'grossQty',
    'unit',
    'lineCost'
])

<tr class="hover:bg-slate-800/40 transition">
    <td class="p-3 font-sans">
        <div class="font-medium text-white">{{ $name }}</div>
        <div class="text-[11px] text-slate-400">Tipe: {{ strtoupper($type) }}</div>
    </td>
    <td class="p-3 text-center text-slate-300">{{ number_format($netPortion, 1) }} {{ $unit }}</td>
    <td class="p-3 text-center text-amber-400 font-mono">{{ number_format($yieldLoss * 100, 1) }}%</td>
    <td class="p-3 text-right font-bold text-white font-mono">{{ number_format($grossQty, 2) }} {{ $unit }}</td>
    <td class="p-3 text-right text-emerald-400 font-mono font-bold">IDR {{ number_format($lineCost, 0, ',', '.') }}</td>
</tr>
