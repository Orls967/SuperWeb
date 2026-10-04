<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Perencanaan Produksi — MPS / MRP / CRP</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            @if (session('success'))
                <div class="rounded-md bg-green-50 p-4 text-green-800">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-red-800">
                    <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">36.7 Parameter per material (stok pengaman, lead time, MOQ, lot sizing)</h3>
                <form method="POST" action="{{ route('manufacturing.planning.params.store') }}" class="mt-4 grid gap-3 md:grid-cols-4">
                    @csrf
                    <select name="material_id" required class="rounded border-gray-300"><option value="">Material</option>@foreach ($materials as $m)<option value="{{ $m->id }}">{{ $m->code }}</option>@endforeach</select>
                    <input name="safety_stock" type="number" step="0.000001" min="0" value="0" required placeholder="Safety stock" class="rounded border-gray-300">
                    <input name="reorder_point" type="number" step="0.000001" min="0" value="0" required placeholder="Titik pesan ulang" class="rounded border-gray-300">
                    <input name="lead_time_days" type="number" min="0" value="7" required placeholder="Lead time (hari)" class="rounded border-gray-300">
                    <input name="moq" type="number" step="0.000001" min="0.000001" value="1" required placeholder="MOQ" class="rounded border-gray-300">
                    <select name="lot_sizing" class="rounded border-gray-300"><option value="l4l">Lot-for-lot</option><option value="fixed">Fixed qty</option><option value="periodic">Periodik</option><option value="eoq">EOQ</option></select>
                    <input name="period_weeks" type="number" min="1" max="52" value="2" class="rounded border-gray-300" placeholder="Period (minggu)">
                    <button class="rounded bg-indigo-600 px-4 py-2 text-white">Simpan parameter</button>
                </form>
                <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="p-2 text-left">Material</th><th class="p-2 text-left">Safety</th><th class="p-2 text-left">ROP</th><th class="p-2 text-left">Lead</th><th class="p-2 text-left">MOQ</th><th class="p-2 text-left">Lot sizing</th></tr></thead><tbody>
                    @foreach ($params as $param)<tr class="border-t"><td class="p-2">{{ $param->material->code }}</td><td class="p-2">{{ $param->safety_stock }}</td><td class="p-2">{{ $param->reorder_point }}</td><td class="p-2">{{ $param->lead_time_days }} hari</td><td class="p-2">{{ $param->moq }}</td><td class="p-2">{{ $param->lot_sizing }}</td></tr>@endforeach
                </tbody></table></div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">Posisi stok pabrik & penerimaan terjadwal</h3>
                <div class="mt-4 grid gap-6 lg:grid-cols-2">
                    <form method="POST" action="{{ route('manufacturing.planning.balances.store') }}" class="grid gap-3">
                        @csrf
                        <div class="grid gap-3 md:grid-cols-3"><select name="material_id" required class="rounded border-gray-300"><option value="">Material</option>@foreach ($materials as $m)<option value="{{ $m->id }}">{{ $m->code }}</option>@endforeach</select><input name="qty_on_hand" type="number" step="0.000001" min="0" required placeholder="On-hand" class="rounded border-gray-300"><input name="qty_reserved" type="number" step="0.000001" min="0" value="0" required placeholder="Reserved" class="rounded border-gray-300"><button class="rounded bg-indigo-600 px-3 py-2 text-white">Simpan stok</button></div>
                    </form>
                    <form method="POST" action="{{ route('manufacturing.planning.receipts.store') }}" class="grid gap-3">
                        @csrf
                        <div class="grid gap-3 md:grid-cols-5"><select name="material_id" required class="rounded border-gray-300"><option value="">Material</option>@foreach ($materials as $m)<option value="{{ $m->id }}">{{ $m->code }}</option>@endforeach</select><input name="due_date" type="date" required class="rounded border-gray-300"><input name="qty" type="number" step="0.000001" min="0.000001" required placeholder="Qty" class="rounded border-gray-300"><select name="source_type" class="rounded border-gray-300"><option value="po">PO</option><option value="manual">Manual</option><option value="production">Produksi</option></select><button class="rounded bg-indigo-600 px-3 py-2 text-white">Tambah receipt</button></div>
                    </form>
                </div>
                <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="p-2 text-left">Material</th><th class="p-2 text-left">On-hand</th><th class="p-2 text-left">Reserved</th></tr></thead><tbody>
                    @foreach ($balances as $b)<tr class="border-t"><td class="p-2">{{ $b->material->code }}</td><td class="p-2">{{ $b->qty_on_hand }}</td><td class="p-2">{{ $b->qty_reserved }}</td></tr>@endforeach
                </tbody></table></div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">36.1 Forecast & 36.2 MPS (time fence)</h3>
                <div class="mt-4 grid gap-6 lg:grid-cols-2">
                    <form method="POST" action="{{ route('manufacturing.planning.forecast.store') }}" class="grid gap-3">
                        @csrf
                        <div class="grid gap-3 md:grid-cols-2"><input name="name" placeholder="Nama skenario" required class="rounded border-gray-300"><select name="material_id" required class="rounded border-gray-300"><option value="">Material</option>@foreach ($materials as $m)<option value="{{ $m->id }}">{{ $m->code }}</option>@endforeach</select><input name="period_start" type="date" required class="rounded border-gray-300"><input name="qty" type="number" step="0.000001" min="0.000001" required placeholder="Qty" class="rounded border-gray-300"><select name="kind" class="rounded border-gray-300"><option value="forecast">Forecast</option><option value="order">Order</option></select><button class="rounded bg-indigo-600 px-3 py-2 text-white">Buat skenario</button></div>
                    </form>
                    <form method="POST" action="{{ route('manufacturing.planning.mps.store') }}" class="grid gap-3">
                        @csrf
                        <div class="grid gap-3 md:grid-cols-2"><input name="name" placeholder="Nama MPS" required class="rounded border-gray-300"><select name="material_id" required class="rounded border-gray-300"><option value="">Barang jadi</option>@foreach ($materials as $m)<option value="{{ $m->id }}">{{ $m->code }}</option>@endforeach</select><input name="period_start" type="date" required class="rounded border-gray-300"><input name="qty" type="number" step="0.000001" min="0.000001" required placeholder="Qty" class="rounded border-gray-300"><input name="freeze_days" type="number" min="0" value="14" required placeholder="Freeze (hari)" class="rounded border-gray-300"><button class="rounded bg-indigo-600 px-3 py-2 text-white">Simpan MPS</button></div>
                    </form>
                </div>
                <div class="mt-4 grid gap-3 lg:grid-cols-2">
                    @foreach ($scenarios as $sc)<div class="rounded border p-3 text-sm flex items-center justify-between"><span>{{ $sc->name }} v{{ $sc->version }} — {{ $sc->status }} ({{ $sc->lines->count() }} baris)</span>@if ($sc->status !== 'active')<form method="POST" action="{{ route('manufacturing.planning.forecast.activate', $sc) }}">@csrf<button class="text-indigo-700 underline">Aktifkan</button></form>@endif</div>@endforeach
                </div>
                <div class="mt-4 grid gap-3 lg:grid-cols-2">
                    @foreach ($mpsHeaders as $h)<div class="rounded border p-3 text-sm">{{ $h->name }} v{{ $h->version }} — {{ $h->status }}, freeze {{ $h->freeze_days }} hari ({{ $h->lines->count() }} baris, {{ $h->lines->where('frozen', true)->count() }} frozen)</div>@endforeach
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">36.3/36.4/36.8 Jalankan MRP + CRP (idempoten)</h3>
                <form method="POST" action="{{ route('manufacturing.planning.mrp.run') }}" class="mt-4 flex flex-wrap items-end gap-3">
                    @csrf
                    <label class="text-sm">Horizon (hari)<input name="horizon_days" type="number" min="7" value="56" class="ml-2 rounded border-gray-300"></label>
                    <label class="text-sm">Bucket (hari)<input name="bucket_days" type="number" min="1" value="7" class="ml-2 rounded border-gray-300"></label>
                    <label class="text-sm"><input type="checkbox" name="scenario" value="1"> What-if (tanpa order nyata)</label>
                    <button class="rounded bg-indigo-600 px-4 py-2 text-white">Jalankan MRP</button>
                </form>
                <div class="mt-4 space-y-2">@foreach ($runs as $run)<div class="flex items-center justify-between rounded border p-3 text-sm"><span>Run {{ substr($run->id, 0, 8) }} — {{ $run->status }}{{ $run->is_scenario ? ' (scenario)' : '' }} · {{ $run->requirements_count }} requirement · {{ $run->planned_orders_count }} order · {{ $run->capacity_loads_count }} load{{ ($run->summary['bottlenecks'] ?? 0) > 0 ? ' · bottleneck '.($run->summary['bottlenecks']) : '' }}</span><form method="POST" action="{{ route('manufacturing.planning.runs.propose', $run) }}">@csrf<button class="text-indigo-700 underline">Usulkan PR</button></form></div>@endforeach</div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">36.5 Planned → Firm (reservasi bahan)</h3>
                <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="p-2 text-left">Material</th><th class="p-2 text-left">Jenis</th><th class="p-2 text-left">Qty</th><th class="p-2 text-left">Due</th><th class="p-2 text-left">Status</th><th class="p-2 text-left">PR</th><th class="p-2 text-left">Aksi</th></tr></thead><tbody>
                        @foreach ($plannedOrders as $order)<tr class="border-t"><td class="p-2">{{ $order->material->code }}</td><td class="p-2">{{ $order->kind }}</td><td class="p-2">{{ $order->qty }}</td><td class="p-2">{{ $order->due_date->toDateString() }}</td><td class="p-2">{{ $order->status }}</td><td class="p-2">{{ $order->pr_ref ?? '—' }}</td><td class="p-2">@if ($order->status === 'planned')<form method="POST" action="{{ route('manufacturing.planning.orders.firm', $order) }}" class="flex gap-2">@csrf<input type="hidden" name="reservation_kind" value="soft"><button class="text-indigo-700 underline">Firm (soft)</button></form>@endif</td></tr>@endforeach
                    </tbody></table></div>
            </section>
        </div>
    </div>
</x-app-layout>
