@extends('layouts.app')

@section('content')
<div class="py-12"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6 text-gray-300">
<div class="p-4 sm:p-6 bg-gray-800 shadow sm:rounded-lg">
    <h2 class="text-xl font-bold text-white">Portal Kontraktor Angkut</h2>
    @include('mining::partials.flash')
    <p class="my-3 text-sm">{{ $summary['cases'] }} kasus · BBM aktual {{ number_format($summary['actual_ml'] / 1000) }} L vs baseline {{ number_format($summary['baseline_ml'] / 1000) }} L · hold berjalan Rp {{ number_format($summary['held_idr']) }} · dipotong Rp {{ number_format($summary['deducted_idr']) }} · dirilis Rp {{ number_format($summary['released_idr']) }}</p>

    <h3 class="text-lg font-semibold text-white mb-2">Hold tagihan</h3>
    <div class="overflow-x-auto mb-6"><table class="w-full text-left text-sm">
        <thead class="text-xs uppercase text-gray-400"><tr><th class="p-2">Referensi</th><th class="p-2">Hold (Rp)</th><th class="p-2">Dirilis</th><th class="p-2">Dipotong</th><th class="p-2">Status</th></tr></thead>
        <tbody class="divide-y divide-gray-700">
        @forelse($myHolds as $h)
            <tr><td class="p-2 font-mono">{{ $h->hold_reference }}</td><td class="p-2">{{ number_format($h->hold_amount_idr) }}</td><td class="p-2">{{ number_format($h->released_amount_idr) }}</td><td class="p-2">{{ number_format($h->deducted_amount_idr) }}</td><td class="p-2">{{ $h->status }}</td></tr>
        @empty
            <tr><td colspan="5" class="p-4 text-center text-gray-400">Tidak ada hold.</td></tr>
        @endforelse
        </tbody>
    </table></div>

    <h3 class="text-lg font-semibold text-white mb-2">Kasus anomali BBM</h3>
    <div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead class="text-xs uppercase text-gray-400"><tr><th class="p-2">Kasus</th><th class="p-2">Tanggal</th><th class="p-2">Alat</th><th class="p-2">Lebih</th><th class="p-2">Status</th><th class="p-2">Sanggah</th></tr></thead>
        <tbody class="divide-y divide-gray-700">
        @forelse($myCases as $c)
            <tr><td class="p-2 font-mono">{{ $c->case_number }}</td><td class="p-2">{{ $c->shift_date->format('Y-m-d') }}</td><td class="p-2">{{ $c->equipment?->equipment_code }}</td>
                <td class="p-2">{{ number_format($c->excess_bps / 100, 2) }}%</td><td class="p-2">{{ $c->status->value }}</td>
                <td class="p-2">
                    @if($c->status->value === 'payment_held')
                        <form action="{{ route('mining.contractor.dispute') }}" method="POST" class="flex gap-1">@csrf
                            <input type="hidden" name="case_id" value="{{ $c->id }}">
                            <input name="reason" required minlength="5" class="rounded bg-gray-900 border-gray-600 text-xs w-40" placeholder="alasan">
                            <button class="px-2 bg-amber-600 text-white rounded text-xs">Sanggah</button>
                        </form>
                    @endif
                </td></tr>
        @empty
            <tr><td colspan="6" class="p-4 text-center text-gray-400">Tidak ada kasus.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $myCases->links() }}
</div></div></div>
@endsection
