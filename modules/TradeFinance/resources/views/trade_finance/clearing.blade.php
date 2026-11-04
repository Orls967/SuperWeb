<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Clearing Escrow USDT per L/C — Fase 83</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 space-y-4">
            @if (session('success'))<div class="rounded-md bg-green-50 p-4 text-green-800 text-sm">{{ session('success') }}</div>@endif
            @if ($errors->any())<div class="rounded-md bg-red-50 p-4 text-red-800 text-sm"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <div class="rounded-lg bg-teal-50 px-4 py-3 text-xs sm:text-sm text-teal-800">
                <strong>Fase 83:</strong> escrow USDT (micro-unit) per L/C. Rilis hanya setelah hash BL/POD terverifikasi di rantai custody Logistics. Kurs IDR/USDT dari PriceFeed (simulasi, S3).
            </div>

            <div class="grid grid-cols-2 gap-3 md:grid-cols-4 text-center">
                <div class="rounded-lg bg-white p-3 shadow"><div class="text-xs text-gray-500">Total</div><div class="text-lg font-bold">{{ number_format($summary['total']) }}</div></div>
                <div class="rounded-lg bg-white p-3 shadow"><div class="text-xs text-gray-500">Terdanai</div><div class="text-lg font-bold text-amber-600">{{ number_format($summary['deposited']) }}</div></div>
                <div class="rounded-lg bg-white p-3 shadow"><div class="text-xs text-gray-500">Dirilis</div><div class="text-lg font-bold text-emerald-600">{{ number_format($summary['released']) }}</div></div>
                <div class="rounded-lg bg-white p-3 shadow"><div class="text-xs text-gray-500">USDT tertahan</div><div class="text-lg font-bold">{{ number_format($summary['escrow_usdt_micro'] / 1000000, 6) }}</div></div>
            </div>

            @if ($canOperate)
            <form method="POST" action="{{ route('trade_finance.clearing.deposit') }}" class="rounded-lg bg-white p-4 shadow grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @csrf
                <h3 class="font-semibold text-gray-800 sm:col-span-2 lg:col-span-3">Danai escrow baru</h3>
                <input name="letter_of_credit_id" required placeholder="UUID L/C" class="rounded border-gray-300 text-sm" value="{{ old('letter_of_credit_id') }}">
                <input name="importer_user_id" required type="number" min="1" placeholder="ID importir" class="rounded border-gray-300 text-sm" value="{{ old('importer_user_id') }}">
                <input name="exporter_user_id" required type="number" min="1" placeholder="ID eksportir" class="rounded border-gray-300 text-sm" value="{{ old('exporter_user_id') }}">
                <input name="usdt_micro" required type="number" min="1" placeholder="USDT (micro-unit)" class="rounded border-gray-300 text-sm" value="{{ old('usdt_micro') }}">
                <input name="idempotency_key" required maxlength="64" placeholder="Idempotency-Key" class="rounded border-gray-300 text-sm" value="{{ old('idempotency_key') }}">
                <button class="rounded bg-teal-600 px-4 py-2 text-sm font-semibold text-white">Setor USDT</button>
            </form>
            @endif

            <div class="rounded-lg bg-white shadow overflow-x-auto">
                <table class="min-w-full text-xs sm:text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600"><tr><th class="px-3 py-2">Kode</th><th class="px-3 py-2">USDT</th><th class="px-3 py-2">Kurs setor</th><th class="px-3 py-2">Status</th><th class="px-3 py-2"></th></tr></thead>
                    <tbody>
                    @forelse ($escrows as $e)
                        <tr class="border-t"><td class="px-3 py-2 font-mono">{{ $e->escrow_code }}</td><td class="px-3 py-2">{{ number_format($e->usdt_micro / 1000000, 6) }}</td><td class="px-3 py-2">Rp {{ number_format($e->deposit_fx_rate_idr) }}</td><td class="px-3 py-2">{{ $e->status }}</td><td class="px-3 py-2"><a class="text-teal-700 underline" href="{{ route('trade_finance.clearing.show', $e) }}">Detail</a></td></tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">Belum ada escrow.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
