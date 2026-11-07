@props(['ingredient', 'qty', 'unit', 'unitCost', 'subtotal'])

<tr class="text-xs hover:bg-slate-50 transition border-b border-slate-100">
    <td class="py-2.5 px-3 font-medium text-slate-800">{{ $ingredient }}</td>
    <td class="py-2.5 px-3 text-center font-mono">{{ $qty }} {{ $unit }}</td>
    <td class="py-2.5 px-3 text-right font-mono text-slate-600">IDR {{ number_format((float) $unitCost, 0, ',', '.') }}</td>
    <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900">IDR {{ number_format((float) $subtotal, 0, ',', '.') }}</td>
</tr>
