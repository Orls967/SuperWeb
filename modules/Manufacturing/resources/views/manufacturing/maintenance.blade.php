<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pemeliharaan, OEE &amp; K3</h2>
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

            <div class="flex items-center justify-between rounded-lg bg-indigo-50 px-4 py-3">
                <p class="text-sm text-indigo-800">40.1–40.7 — OEE, WO pemeliharaan, suku cadang, sensor simulasi, K3, energi.</p>
                <div class="flex gap-3 text-sm"><a href="{{ route('manufacturing.index') }}" class="text-indigo-700 underline">Master</a><a href="{{ route('manufacturing.quality.index') }}" class="text-indigo-700 underline">QMS</a></div>
            </div>

            <div class="grid gap-4 md:grid-cols-4">
                @foreach ($backlog as $priority => $row)
                    <div class="rounded-lg bg-white p-4 shadow text-sm">
                        <p class="text-gray-500">Backlog {{ $priority }}</p>
                        <p class="text-xl font-semibold">{{ $row['count'] }} WO</p>
                        <p class="{{ $row['overdue'] > 0 ? 'text-rose-600' : 'text-gray-400' }}">{{ $row['overdue'] }} lewat jadwal</p>
                    </div>
                @endforeach
            </div>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">40.2 Work order pemeliharaan</h3>
                <form method="POST" action="{{ route('manufacturing.maintenance.orders.store') }}" class="mt-3 grid gap-3 md:grid-cols-4 text-sm">
                    @csrf
                    <select name="work_center_id" required class="rounded border-gray-300"><option value="">Work center</option>@foreach ($workCenters as $wc)<option value="{{ $wc->id }}">{{ $wc->code }} — {{ $wc->name }}</option>@endforeach</select>
                    <input name="description" placeholder="Deskripsi kerusakan/jadwal" required class="rounded border-gray-300">
                    <select name="kind" class="rounded border-gray-300"><option value="corrective">Korektif</option><option value="preventive">Preventif</option><option value="predictive">Prediktif</option></select>
                    <select name="priority" class="rounded border-gray-300"><option value="low">Low</option><option value="normal" selected>Normal</option><option value="high">High</option><option value="critical">Critical</option></select>
                    <input name="due_date" type="date" class="rounded border-gray-300"><input name="labor_cost_idr" type="number" min="0" placeholder="Biaya tenaga" class="rounded border-gray-300">
                    <button class="rounded bg-indigo-600 px-4 py-2 text-white">Buat WO</button>
                </form>
                <div class="mt-3 space-y-2">
                    @forelse ($orders as $order)
                        <div class="flex flex-wrap items-center justify-between gap-2 rounded border p-3 text-sm">
                            <span><strong>{{ $order->number }}</strong> — {{ $order->workCenter?->code }} · {{ $order->kind }} · {{ $order->priority }} · {{ $order->status }} · {{ \Illuminate\Support\Str::limit($order->description, 60) }}</span>
                            <span class="flex gap-2">
                                @foreach (['in_progress' => 'Mulai', 'completed' => 'Selesai', 'cancelled' => 'Batal'] as $to => $label)
                                    @if ($order->canTransitionTo($to))
                                        <form method="POST" action="{{ route('manufacturing.maintenance.orders.transition', $order) }}">@csrf<input type="hidden" name="to" value="{{ $to }}"><button class="text-indigo-700 underline">{{ $label }}</button></form>
                                    @endif
                                @endforeach
                            </span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada WO pemeliharaan.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">40.1/40.4 OEE &amp; sensor simulasi</h3>
                <div class="mt-3 grid gap-4 lg:grid-cols-2">
                    <form method="POST" action="{{ route('manufacturing.maintenance.oee.compute') }}" class="flex flex-wrap items-end gap-2 text-sm">
                        @csrf
                        <select name="work_center_id" required class="rounded border-gray-300"><option value="">Work center</option>@foreach ($workCenters as $wc)<option value="{{ $wc->id }}">{{ $wc->code }}</option>@endforeach</select>
                        <input name="period_date" type="date" value="{{ now()->toDateString() }}" required class="rounded border-gray-300">
                        <input name="planned_minutes" type="number" min="1" value="480" required class="w-24 rounded border-gray-300" title="Menit terencana">
                        <button class="rounded bg-indigo-600 px-3 py-2 text-white">Hitung OEE</button>
                    </form>
                    <form method="POST" action="{{ route('manufacturing.maintenance.sensors.store') }}" class="flex flex-wrap items-end gap-2 text-sm">
                        @csrf
                        <select name="work_center_id" required class="rounded border-gray-300"><option value="">Work center</option>@foreach ($workCenters as $wc)<option value="{{ $wc->id }}">{{ $wc->code }}</option>@endforeach</select>
                        <input name="sensor_code" placeholder="Sensor" required class="w-24 rounded border-gray-300">
                        <select name="metric" class="rounded border-gray-300"><option value="temperature">Suhu</option><option value="vibration">Getaran</option><option value="current">Arus</option></select>
                        <input name="value" type="number" step="0.001" placeholder="Nilai" required class="w-24 rounded border-gray-300">
                        <input name="threshold_high" type="number" step="0.001" placeholder="Ambang atas" class="w-28 rounded border-gray-300">
                        <button class="rounded bg-amber-600 px-3 py-2 text-white">Catat bacaan</button>
                    </form>
                </div>
                <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr>
                    <th class="p-2 text-left">Tanggal</th><th class="p-2 text-left">Work center</th>
                    <th class="p-2 text-right">A%</th><th class="p-2 text-right">P%</th><th class="p-2 text-right">Q%</th>
                    <th class="p-2 text-right">OEE%</th><th class="p-2 text-right">MTBF (j)</th><th class="p-2 text-right">MTTR (m)</th>
                </tr></thead><tbody>
                    @forelse ($oees as $oee)
                        <tr class="border-t">
                            <td class="p-2">{{ $oee->period_date->format('Y-m-d') }}</td><td class="p-2">{{ $oee->workCenter?->code }}</td>
                            <td class="p-2 text-right">{{ number_format($oee->availability_percent, 1) }}</td>
                            <td class="p-2 text-right">{{ number_format($oee->performance_percent, 1) }}</td>
                            <td class="p-2 text-right">{{ number_format($oee->quality_percent, 1) }}</td>
                            <td class="p-2 text-right font-semibold {{ $oee->oee_percent >= 65 ? 'text-emerald-600' : ($oee->oee_percent >= 40 ? 'text-amber-600' : 'text-rose-600') }}">{{ number_format($oee->oee_percent, 1) }}</td>
                            <td class="p-2 text-right">{{ number_format($oee->mtbf_hours, 1) }}</td>
                            <td class="p-2 text-right">{{ number_format($oee->mttr_minutes, 1) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-2 text-gray-500">Belum ada perhitungan OEE.</td></tr>
                    @endforelse
                </tbody></table></div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">40.5 Pareto downtime (7 hari) &amp; 40.3 stok minimum</h3>
                <div class="mt-3 grid gap-4 lg:grid-cols-2">
                    <table class="min-w-full text-sm"><thead><tr><th class="p-2 text-left">Alasan</th><th class="p-2 text-right">Menit</th><th class="p-2 text-right">%</th><th class="p-2 text-right">Kumulatif</th></tr></thead><tbody>
                        @forelse ($pareto as $row)
                            <tr class="border-t"><td class="p-2">{{ $row['reason'] }}</td><td class="p-2 text-right">{{ $row['minutes'] }}</td><td class="p-2 text-right">{{ $row['share_percent'] }}%</td><td class="p-2 text-right">{{ $row['cumulative_percent'] }}%</td></tr>
                        @empty
                            <tr><td colspan="4" class="p-2 text-gray-500">Belum ada downtime.</td></tr>
                        @endforelse
                    </tbody></table>
                    <div class="space-y-3 text-sm">
                        <form method="POST" action="{{ route('manufacturing.maintenance.parts.store') }}" class="flex flex-wrap gap-2">
                            @csrf
                            <select name="work_center_id" required class="rounded border-gray-300"><option value="">Work center</option>@foreach ($workCenters as $wc)<option value="{{ $wc->id }}">{{ $wc->code }}</option>@endforeach</select>
                            <input name="part_code" placeholder="Kode part" required class="rounded border-gray-300"><input name="name" placeholder="Nama" required class="rounded border-gray-300">
                            <input name="min_stock" type="number" step="0.01" min="0" placeholder="Stok min" class="w-24 rounded border-gray-300"><input name="unit_cost_idr" type="number" min="0" placeholder="Harga" class="w-28 rounded border-gray-300">
                            <button class="rounded bg-indigo-600 px-3 py-2 text-white">Tambah part</button>
                        </form>
                        <div class="rounded border p-3">Part terdaftar: {{ $parts->count() }} · di bawah minimum: {{ count($lowStock) }}
                            @foreach ($lowStock as $row)<div class="text-rose-600">⚠ {{ $row['part']->part_code }} — {{ $row['part']->name }} (pemakaian {{ $row['consumed'] }} / min {{ $row['part']->min_stock }})</div>@endforeach
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">40.6 K3 — insiden, near-miss &amp; izin kerja berisiko</h3>
                <div class="mt-3 grid gap-4 lg:grid-cols-2">
                    <form method="POST" action="{{ route('manufacturing.maintenance.incidents.store') }}" class="space-y-2 text-sm">
                        @csrf
                        <div class="flex flex-wrap gap-2"><input name="title" placeholder="Judul insiden" required class="flex-1 rounded border-gray-300">
                            <select name="kind" class="rounded border-gray-300"><option value="near_miss">Near-miss</option><option value="incident">Insiden</option><option value="injury">Cedera</option><option value="environmental">Lingkungan</option></select>
                            <select name="severity" class="rounded border-gray-300"><option value="low">Low</option><option value="moderate">Moderate</option><option value="high">High</option><option value="critical">Critical</option></select></div>
                        <div class="flex flex-wrap gap-2"><select name="work_center_id" class="rounded border-gray-300"><option value="">Work center</option>@foreach ($workCenters as $wc)<option value="{{ $wc->id }}">{{ $wc->code }}</option>@endforeach</select>
                            <input name="due_date" type="date" class="rounded border-gray-300"><input name="description" placeholder="Keterangan" class="flex-1 rounded border-gray-300">
                            <button class="rounded bg-rose-600 px-3 py-2 text-white">Lapor</button></div>
                    </form>
                    <form method="POST" action="{{ route('manufacturing.maintenance.permits.store') }}" class="space-y-2 text-sm">
                        @csrf
                        <div class="flex flex-wrap gap-2"><select name="work_center_id" required class="rounded border-gray-300"><option value="">Work center</option>@foreach ($workCenters as $wc)<option value="{{ $wc->id }}">{{ $wc->code }}</option>@endforeach</select>
                            <select name="permit_type" class="rounded border-gray-300"><option value="hot_work">Hot work</option><option value="confined_space">Confined space</option></select>
                            <input name="valid_from" type="date" value="{{ now()->toDateString() }}" class="rounded border-gray-300"><input name="valid_until" type="date" required class="rounded border-gray-300">
                            <button class="rounded bg-indigo-600 px-3 py-2 text-white">Ajukan izin</button></div>
                        <div class="flex flex-wrap gap-2"><input name="hazards" placeholder="Bahaya" class="flex-1 rounded border-gray-300"><input name="controls" placeholder="Pengendalian" class="flex-1 rounded border-gray-300"></div>
                    </form>
                </div>
                <div class="mt-4 grid gap-4 lg:grid-cols-2 text-sm">
                    <table class="min-w-full"><thead><tr><th class="p-2 text-left">No.</th><th class="p-2 text-left">Jenis</th><th class="p-2 text-left">Severity</th><th class="p-2 text-left">Status</th><th class="p-2 text-left">Aksi</th></tr></thead><tbody>
                        @foreach ($incidents as $incident)
                            <tr class="border-t"><td class="p-2">{{ $incident->number }}</td><td class="p-2">{{ $incident->kind }}</td><td class="p-2">{{ $incident->severity }}</td><td class="p-2">{{ $incident->status }}</td><td class="p-2">@if ($incident->status !== 'closed')<form method="POST" action="{{ route('manufacturing.maintenance.incidents.close', $incident) }}">@csrf<button class="text-emerald-700 underline">Tutup</button></form>@endif</td></tr>
                        @endforeach
                    </tbody></table>
                    <table class="min-w-full"><thead><tr><th class="p-2 text-left">No.</th><th class="p-2 text-left">Jenis</th><th class="p-2 text-left">Berlaku s/d</th><th class="p-2 text-left">Status</th><th class="p-2 text-left">Aksi</th></tr></thead><tbody>
                        @foreach ($permits as $permit)
                            <tr class="border-t"><td class="p-2">{{ $permit->number }}</td><td class="p-2">{{ $permit->permit_type }}</td><td class="p-2">{{ $permit->valid_until->format('Y-m-d H:i') }}</td><td class="p-2">{{ $permit->status }}</td><td class="p-2">@if ($permit->status === 'pending')<form method="POST" action="{{ route('manufacturing.maintenance.permits.approve', $permit) }}">@csrf<button class="text-emerald-700 underline">Setujui</button></form>@endif</td></tr>
                        @endforeach
                    </tbody></table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
