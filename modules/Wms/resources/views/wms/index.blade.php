<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Gudang &amp; Pusat Distribusi (WMS)</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))<div class="rounded-md bg-green-50 p-4 text-green-800">{{ session('success') }}</div>@endif
            @if ($errors->any())<div class="rounded-md bg-red-50 p-4 text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">41.1 Gudang multi-tier</h3>
                <form method="POST" action="{{ route('wms.warehouses.store') }}" class="mt-3 grid gap-2 md:grid-cols-5 text-sm">
                    @csrf
                    <input name="code" placeholder="Kode gudang" required class="rounded border-gray-300">
                    <input name="name" placeholder="Nama" required class="rounded border-gray-300">
                    <select name="kind" class="rounded border-gray-300"><option value="dc">DC</option><option value="raw">Bahan baku</option><option value="fg">Barang jadi</option><option value="quarantine">Karantina</option><option value="transit">Transit</option><option value="consignment">Konsinyasi</option><option value="reefer">Reefer</option></select>
                    <input name="city" placeholder="Kota" class="rounded border-gray-300">
                    <button class="rounded bg-indigo-600 px-3 py-2 text-white">Tambah gudang</button>
                </form>
                <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($warehouses as $warehouse)<div class="rounded border p-3 text-sm"><strong>{{ $warehouse->code }}</strong> — {{ $warehouse->name }}<div class="text-gray-500">{{ $warehouse->kind }} · {{ $warehouse->city }} · {{ $warehouse->zones_count }} zona</div></div>@endforeach
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">41.2 Stok per bin/lot/status</h3>
                <div class="mt-3 grid gap-4 lg:grid-cols-2 text-sm">
                    <form method="POST" action="{{ route('wms.putaway') }}" class="flex flex-wrap gap-2">
                        @csrf
                        <input name="bin_id" type="number" placeholder="Bin ID" required class="w-24 rounded border-gray-300">
                        <input name="product_id" type="number" placeholder="Product ID" required class="w-28 rounded border-gray-300">
                        <input name="qty" type="number" step="0.000001" min="0.000001" placeholder="Qty" required class="w-24 rounded border-gray-300">
                        <input name="lot_number" placeholder="Lot (opsional)" class="w-32 rounded border-gray-300">
                        <select name="status" class="rounded border-gray-300"><option value="available">Available</option><option value="quarantine">Quarantine</option><option value="blocked">Blocked</option></select>
                        <button class="rounded bg-indigo-600 px-3 py-2 text-white">Putaway</button>
                    </form>
                    <form method="POST" action="{{ route('wms.pick') }}" class="flex flex-wrap gap-2">
                        @csrf
                        <input name="bin_id" type="number" placeholder="Bin ID" required class="w-24 rounded border-gray-300">
                        <input name="product_id" type="number" placeholder="Product ID" required class="w-28 rounded border-gray-300">
                        <input name="qty" type="number" step="0.000001" min="0.000001" placeholder="Qty" required class="w-24 rounded border-gray-300">
                        <select name="method" class="rounded border-gray-300"><option value="fifo">FIFO</option><option value="fefo">FEFO</option></select>
                        <button class="rounded bg-emerald-600 px-3 py-2 text-white">Pick</button>
                    </form>
                </div>
                <div class="mt-3 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="p-2 text-left">Bin</th><th class="p-2 text-left">Product</th><th class="p-2 text-left">Lot/Serial</th><th class="p-2 text-left">Status</th><th class="p-2 text-right">Qty</th></tr></thead><tbody>
                    @foreach ($stocks as $stock)<tr class="border-t"><td class="p-2">{{ $stock->bin_id }}</td><td class="p-2">{{ $stock->product_id }}</td><td class="p-2">{{ $stock->lot_number ?? $stock->serial_number ?? '—' }}</td><td class="p-2">{{ $stock->status }}</td><td class="p-2 text-right">{{ $stock->qty }}</td></tr>@endforeach
                </tbody></table></div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <div class="flex flex-wrap items-center justify-between gap-3"><h3 class="text-lg font-semibold">41.3 Tugas &amp; wave picking</h3>
                    <form method="POST" action="{{ route('wms.waves.store') }}" class="flex gap-2 text-sm">@csrf<select name="strategy" class="rounded border-gray-300"><option value="fifo">FIFO</option><option value="fefo">FEFO</option><option value="zone">Zone</option><option value="batch">Batch</option></select><button class="rounded bg-indigo-600 px-3 py-2 text-white">Wave baru</button></form>
                </div>
                <div class="mt-3 space-y-2">@foreach ($tasks as $task)<div class="rounded border p-3 text-sm">{{ $task->kind }} · product {{ $task->product_id }} · qty {{ $task->qty }} · bin {{ $task->bin_id }} · {{ $task->status }}</div>@endforeach</div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">41.4 Transfer antar-gudang / cross-dock</h3>
                <form method="POST" action="{{ route('wms.transfers.store') }}" class="mt-3 grid gap-2 md:grid-cols-6 text-sm">
                    @csrf
                    <select name="from_warehouse_id" required class="rounded border-gray-300"><option value="">Dari gudang</option>@foreach ($warehouses as $w)<option value="{{ $w->id }}">{{ $w->code }}</option>@endforeach</select>
                    <select name="to_warehouse_id" required class="rounded border-gray-300"><option value="">Ke gudang</option>@foreach ($warehouses as $w)<option value="{{ $w->id }}">{{ $w->code }}</option>@endforeach</select>
                    <input name="product_id" type="number" required placeholder="Product ID" class="rounded border-gray-300">
                    <input name="qty" type="number" step="0.000001" min="0.000001" required placeholder="Qty" class="rounded border-gray-300">
                    <input name="lot_number" placeholder="Lot" class="rounded border-gray-300">
                    <label class="flex items-center gap-1"><input type="checkbox" name="cross_dock" value="1"> Cross-dock</label>
                    <button class="rounded bg-indigo-600 px-3 py-2 text-white">Buat transfer</button>
                </form>
                <div class="mt-3 space-y-2">@foreach ($transfers as $transfer)<div class="flex flex-wrap items-center justify-between gap-2 rounded border p-3 text-sm"><span>{{ $transfer->number }} · {{ $transfer->fromWarehouse?->code }} → {{ $transfer->toWarehouse?->code }} · {{ $transfer->status }}{{ $transfer->cross_dock ? ' · cross-dock' : '' }}</span><span class="flex gap-2">@if ($transfer->status === 'draft')<form method="POST" action="{{ route('wms.transfers.ship', $transfer) }}">@csrf<button class="text-indigo-700 underline">Kirim</button></form>@elseif ($transfer->status === 'in_transit')<form method="POST" action="{{ route('wms.transfers.receive', $transfer) }}" class="flex gap-1">@csrf<input name="bin_id" type="number" placeholder="Bin tujuan" required class="w-24 rounded border-gray-300"><button class="text-emerald-700 underline">Terima</button></form>@endif</span></div>@endforeach</div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">41.5 Cycle count &amp; 41.6 replenishment / ABC slotting</h3>
                <form method="POST" action="{{ route('wms.cycle-counts.store') }}" class="mt-3 flex flex-wrap gap-2 text-sm">
                    @csrf
                    <select name="warehouse_id" required class="rounded border-gray-300"><option value="">Gudang</option>@foreach ($warehouses as $w)<option value="{{ $w->id }}">{{ $w->code }}</option>@endforeach</select>
                    <input name="bin_id" type="number" required placeholder="Bin ID" class="w-24 rounded border-gray-300"><input name="product_id" type="number" required placeholder="Product ID" class="w-28 rounded border-gray-300"><input name="counted_qty" type="number" step="0.000001" min="0" required placeholder="Hasil hitung" class="w-32 rounded border-gray-300"><button class="rounded bg-indigo-600 px-3 py-2 text-white">Buat count</button>
                </form>
                <div class="mt-3 flex items-center justify-between text-sm"><span>{{ $counts->count() }} cycle count terbaru</span><form method="POST" action="{{ route('wms.replenishments.compute') }}">@csrf<button class="rounded bg-amber-600 px-3 py-2 text-white">Hitung replenishment</button></form></div>
                <div class="mt-3 grid gap-3 md:grid-cols-2"><div class="rounded border p-3 text-sm"><strong>Pick face di bawah minimum</strong>@foreach ($replenishments as $r)<div class="mt-1">Bin {{ $r->bin_id }} / product {{ $r->product_id }}: {{ $r->current_qty }} / min {{ $r->min_qty }} → saran {{ $r->suggested_qty }}</div>@endforeach</div><div class="rounded border p-3 text-sm"><strong>Slotting ABC</strong>@foreach ($slottings as $s)<div class="mt-1">Product {{ $s->product_id }} — kelas {{ $s->abc_class }} · nilai tahunan {{ number_format($s->annual_value_idr) }}</div>@endforeach</div></div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">41.7 Dock appointment &amp; packing list</h3>
                <form method="POST" action="{{ route('wms.dock.store') }}" class="mt-3 flex flex-wrap gap-2 text-sm">
                    @csrf
                    <select name="warehouse_id" required class="rounded border-gray-300"><option value="">Gudang</option>@foreach ($warehouses as $w)<option value="{{ $w->id }}">{{ $w->code }}</option>@endforeach</select>
                    <select name="direction" class="rounded border-gray-300"><option value="in">Inbound</option><option value="out">Outbound</option></select>
                    <input name="reference" placeholder="PO/transfer ref" required class="rounded border-gray-300"><input name="window_start" type="datetime-local" required class="rounded border-gray-300"><input name="window_end" type="datetime-local" required class="rounded border-gray-300"><input name="carrier" placeholder="Carrier" class="rounded border-gray-300"><button class="rounded bg-indigo-600 px-3 py-2 text-white">Jadwalkan dok</button>
                </form>
                <div class="mt-3 space-y-1 text-sm">@foreach ($appointments as $a)<div>{{ $a->warehouse?->code }} · {{ $a->direction }} · {{ $a->reference }} · {{ $a->window_start->format('d M H:i') }}–{{ $a->window_end->format('H:i') }} · {{ $a->status }}</div>@endforeach</div>
            </section>
        </div>
    </div>
</x-app-layout>
