@props([
    'partnerCode' => 'GARUDA_AIR',
    'partnerName' => 'Garuda Indonesia',
    'transactionCount' => 142,
    'totalPointsTransferred' => 1420000,
    'grossPayableIdr' => 142000000,
    'transferFeeCollectedIdr' => 4970000,
    'netPayableToPartnerIdr' => 137030000,
    'status' => 'PENDING_SETTLEMENT',
])

<tr class="border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition text-xs">
    <td class="py-3.5 px-4 font-semibold text-slate-900 dark:text-white flex items-center space-x-2">
        <span class="w-2 h-2 rounded-full @if($status === 'SETTLED') bg-emerald-500 @else bg-amber-500 @endif"></span>
        <span>{{ $partnerName }}</span>
        <code class="text-[10px] text-slate-400">({{ $partnerCode }})</code>
    </td>
    <td class="py-3.5 px-4 text-center text-slate-600 dark:text-slate-300">
        {{ number_format($transactionCount) }} Transaksi
    </td>
    <td class="py-3.5 px-4 text-right font-mono font-bold text-slate-700 dark:text-slate-300">
        {{ number_format($totalPointsTransferred) }} PTS
    </td>
    <td class="py-3.5 px-4 text-right font-mono text-slate-500 dark:text-slate-400">
        Rp {{ number_format($grossPayableIdr, 0, ',', '.') }}
    </td>
    <td class="py-3.5 px-4 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
        Rp {{ number_format($transferFeeCollectedIdr, 0, ',', '.') }}
    </td>
    <td class="py-3.5 px-4 text-right font-mono font-black text-indigo-600 dark:text-indigo-400">
        Rp {{ number_format($netPayableToPartnerIdr, 0, ',', '.') }}
    </td>
    <td class="py-3.5 px-4 text-center">
        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase
            @if($status === 'SETTLED') bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300
            @else bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300 @endif">
            {{ str_replace('_', ' ', $status) }}
        </span>
    </td>
</tr>

