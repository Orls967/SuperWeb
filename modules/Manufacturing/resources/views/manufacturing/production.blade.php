<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Shop Floor — Eksekusi Produksi</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="rounded-md bg-green-50 p-4 text-green-800">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-red-800">
                    <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <section class="rounded-lg bg-white p-6 shadow">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">37.1 Order produksi</h3>
                    <a href="{{ route('manufacturing.index') }}" class="text-sm text-indigo-700 underline">← Master data</a>
                </div>
                @php
                    $me = auth()->user();
                    $canPlan = in_array($me?->role, ['admin', 'planner'], true)
                        || (method_exists($me, 'hasAnyRbacRole') && $me->hasAnyRbacRole(['admin', 'planner']));
                @endphp
                @if ($canPlan)
                <form method="POST" action="{{ route('manufacturing.production.orders.store') }}" class="mt-4 grid gap-3 md:grid-cols-5">
                    @csrf
                    <select name="material_id" required class="rounded border-gray-300"><option value="">Barang jadi</option>@foreach ($orders->pluck('material')->unique('id') as $m)<option value="{{ $m->id }}">{{ $m->code }}</option>@endforeach</select>
                    <input name="qty" type="number" step="0.000001" min="0.000001" required placeholder="Qty target" class="rounded border-gray-300">
                    <select name="kind" class="rounded border-gray-300"><option value="standard">Standard</option><option value="rework">Rework</option><option value="subcontract">Subkontrak</option></select>
                    <input name="due_date" type="date" class="rounded border-gray-300">
                    <button class="rounded bg-indigo-600 px-4 py-2 text-white">Buat order</button>
                </form>
                @endif
                <div class="mt-4 space-y-3">
                    @forelse ($orders as $order)
                        <div class="rounded border p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <span class="font-semibold">{{ $order->number }}</span>
                                    <span class="ml-2 text-sm text-gray-600">{{ $order->material->code }} · {{ $order->qty }} → {{ $order->qty_completed }} · {{ $order->status }} · {{ $order->kind }}</span>
                                </div>
                                <div class="flex flex-wrap gap-2 text-sm">
                                    @foreach (['released' => 'Rilis', 'in_progress' => 'Mulai', 'completed' => 'Selesai', 'closed' => 'Tutup', 'cancelled' => 'Batal'] as $to => $label)
                                        @if ($order->canTransitionTo($to))
                                            <form method="POST" action="{{ route('manufacturing.production.orders.transition', $order) }}">@csrf<input type="hidden" name="to" value="{{ $to }}"><button class="rounded bg-gray-100 px-2 py-1">{{ $label }}</button></form>
                                        @endif
                                    @endforeach
                                    <form method="POST" action="{{ route('manufacturing.production.orders.invariants', $order) }}">@csrf<button class="rounded bg-amber-100 px-2 py-1">Invarian</button></form>
                                </div>
                            </div>

                            <div class="mt-3 grid gap-3 md:grid-cols-3 text-sm">
                                <form method="POST" action="{{ route('manufacturing.production.orders.issue', $order) }}" class="space-y-2">
                                    @csrf
                                    <p class="font-medium">37.2 Issue bahan</p>
                                    <select name="material_id" required class="w-full rounded border-gray-300"><option value="">Bahan</option>@foreach ($balances as $b)<option value="{{ $b->material_id }}">{{ $b->material->code }} ({{ $b->qty_on_hand }})</option>@endforeach</select>
                                    <input name="qty" type="number" step="0.000001" min="0.000001" required placeholder="Qty" class="w-full rounded border-gray-300">
                                    <select name="method" class="w-full rounded border-gray-300"><option value="fifo">FIFO</option><option value="fefo">FEFO</option><option value="manual">Manual</option></select>
                                    <button class="rounded bg-indigo-600 px-3 py-1.5 text-white">Issue</button>
                                </form>

                                <form method="POST" action="{{ route('manufacturing.production.operations.start', $order) }}" class="space-y-2">
                                    @csrf
                                    <p class="font-medium">37.3 Operasi</p>
                                    <input name="sequence" type="number" min="1" value="1" required class="w-full rounded border-gray-300" placeholder="Urutan operasi">
                                    <select name="work_center_id" class="w-full rounded border-gray-300"><option value="">Work center</option>@foreach ($workCenters as $wc)<option value="{{ $wc->id }}">{{ $wc->code }}</option>@endforeach</select>
                                    <button class="rounded bg-emerald-600 px-3 py-1.5 text-white">Mulai operasi</button>
                                </form>

                                <form method="POST" action="{{ route('manufacturing.production.operations.finish', $order) }}" class="space-y-2">
                                    @csrf
                                    <p class="font-medium">Selesaikan operasi</p>
                                    <input name="sequence" type="number" min="1" value="1" required class="w-full rounded border-gray-300">
                                    <div class="grid grid-cols-3 gap-1"><input name="qty_good" type="number" step="0.000001" min="0" required placeholder="Baik" class="rounded border-gray-300"><input name="qty_scrap" type="number" step="0.000001" min="0" placeholder="Scrap" class="rounded border-gray-300"><input name="qty_rework" type="number" step="0.000001" min="0" placeholder="Rework" class="rounded border-gray-300"></div>
                                    <button class="rounded bg-indigo-600 px-3 py-1.5 text-white">Selesai</button>
                                </form>
                            </div>

                            <div class="mt-3 grid gap-3 md:grid-cols-3 text-sm">
                                <form method="POST" action="{{ route('manufacturing.production.orders.receive-fg', $order) }}" class="flex gap-2">
                                    @csrf
                                    <input name="qty" type="number" step="0.000001" min="0.000001" required placeholder="Qty FG" class="w-full rounded border-gray-300">
                                    <input name="lot_number" placeholder="Lot (opsional)" class="w-full rounded border-gray-300">
                                    <button class="whitespace-nowrap rounded bg-indigo-600 px-3 py-1.5 text-white">37.5 Terima FG</button>
                                </form>
                                <form method="POST" action="{{ route('manufacturing.production.orders.scrap', $order) }}" class="flex gap-2">
                                    @csrf
                                    <select name="kind" class="rounded border-gray-300"><option value="scrap">Scrap</option><option value="rework">Rework</option></select>
                                    <input name="qty" type="number" step="0.000001" min="0.000001" required placeholder="Qty" class="w-full rounded border-gray-300">
                                    <input name="reason" required placeholder="Alasan" class="w-full rounded border-gray-300">
                                    <button class="whitespace-nowrap rounded bg-rose-600 px-3 py-1.5 text-white">37.7 Catat</button>
                                </form>
                                <div class="rounded bg-gray-50 p-2">Status: <strong>{{ $order->status }}</strong> · Selesai: {{ $order->qty_completed }}</div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada order produksi.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">37.4 Downtime work center</h3>
                <form method="POST" action="{{ route('manufacturing.production.downtimes.store') }}" class="mt-3 flex flex-wrap gap-2 text-sm">
                    @csrf
                    <select name="work_center_id" required class="rounded border-gray-300"><option value="">Work center</option>@foreach ($workCenters as $wc)<option value="{{ $wc->id }}">{{ $wc->code }}</option>@endforeach</select>
                    <select name="reason_code" required class="rounded border-gray-300"><option value="machine_down">Mesin rusak</option><option value="material_wait">Tunggu bahan</option><option value="setup">Setup</option><option value="break">Istirahat</option><option value="other">Lainnya</option></select>
                    <input name="detail" placeholder="Keterangan" class="rounded border-gray-300">
                    <button class="rounded bg-indigo-600 px-3 py-1.5 text-white">Mulai downtime</button>
                </form>
                <div class="mt-3 space-y-2">
                    @foreach ($downtimes as $log)
                        <div class="flex items-center justify-between rounded border p-2 text-sm">
                            <span>{{ $log->workCenter?->code }} — {{ $log->reason_code }} · {{ $log->started_at->format('d H:i') }} @if ($log->ended_at) · {{ $log->minutes }} mnt @endif</span>
                            @if ($log->ended_at === null)
                                <form method="POST" action="{{ route('manufacturing.production.downtimes.end', $log) }}">@csrf<button class="text-indigo-700 underline">Akhiri</button></form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">Lot aktif & saldo material</h3>
                <div class="mt-3 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="p-2 text-left">Material</th><th class="p-2 text-left">Lot</th><th class="p-2 text-left">Qty</th><th class="p-2 text-left">Produced</th><th class="p-2 text-left">Expiry</th></tr></thead><tbody>
                    @foreach ($lots as $lot)<tr class="border-t"><td class="p-2">{{ $lot->material->code }}</td><td class="p-2">{{ $lot->lot_number }}</td><td class="p-2">{{ $lot->qty }}</td><td class="p-2">{{ $lot->produced_at?->toDateString() }}</td><td class="p-2">{{ $lot->expiry_date?->toDateString() ?? '—' }}</td></tr>@endforeach
                </tbody></table></div>
            </section>
        </div>
    </div>
</x-app-layout>
