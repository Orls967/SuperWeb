<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Escrow {{ $escrow->escrow_code }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-3 sm:px-6 lg:px-8 space-y-4">
            @if (session('success'))<div class="rounded-md bg-green-50 p-4 text-green-800 text-sm">{{ session('success') }}</div>@endif
            @if ($errors->any())<div class="rounded-md bg-red-50 p-4 text-red-800 text-sm"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <dl class="rounded-lg bg-white p-4 shadow grid grid-cols-1 gap-2 sm:grid-cols-2 text-sm">
                <div><dt class="text-xs text-gray-500">L/C</dt><dd class="font-mono break-all">{{ $escrow->letter_of_credit_id }}</dd></div>
                <div><dt class="text-xs text-gray-500">Status</dt><dd class="font-semibold">{{ $escrow->status }}</dd></div>
                <div><dt class="text-xs text-gray-500">USDT</dt><dd>{{ number_format($escrow->usdt_micro / 1000000, 6) }}</dd></div>
                <div><dt class="text-xs text-gray-500">Kurs setoran</dt><dd>Rp {{ number_format($escrow->deposit_fx_rate_idr) }}</dd></div>
                @if ($escrow->status === 'RELEASED')
                    <div><dt class="text-xs text-gray-500">Dokumen rilis</dt><dd>{{ $escrow->release_document_type }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Hash dokumen</dt><dd class="font-mono break-all">{{ $escrow->release_document_hash }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Kurs rilis</dt><dd>Rp {{ number_format($escrow->release_fx_rate_idr) }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Revaluasi kurs</dt><dd>Rp {{ number_format($escrow->fx_revaluation_idr) }}</dd></div>
                @endif
            </dl>

            @if ($canOperate && $escrow->status === 'DEPOSITED')
            <form method="POST" action="{{ route('trade_finance.clearing.release', $escrow) }}" class="rounded-lg bg-white p-4 shadow grid grid-cols-1 gap-3">
                @csrf
                <h3 class="font-semibold text-gray-800">Rilis ke eksportir (verifikasi custody)</h3>
                <select name="document_type" class="rounded border-gray-300 text-sm"><option value="BL">Bill of Lading</option><option value="POD">Proof of Delivery</option></select>
                <input name="document_hash" required minlength="64" maxlength="64" placeholder="SHA-256 dokumen (64 hex)" class="rounded border-gray-300 text-sm font-mono">
                <input name="idempotency_key" required maxlength="64" placeholder="Idempotency-Key" value="rel-{{ $escrow->id }}" class="rounded border-gray-300 text-sm">
                <button class="rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Verifikasi &amp; rilis</button>
            </form>
            @endif

            <a class="text-sm text-teal-700 underline" href="{{ route('trade_finance.clearing.index') }}">← Kembali ke daftar</a>
        </div>
    </div>
</x-app-layout>
