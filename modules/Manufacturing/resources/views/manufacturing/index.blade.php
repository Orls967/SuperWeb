<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Master Data Produksi</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            <div class="flex items-center justify-between rounded-lg bg-indigo-50 px-4 py-3">
                <div class="flex items-center gap-3"><p class="text-sm text-indigo-800">Fase 36–37 — perencanaan MPS/MRP/CRP &amp; eksekusi produksi (shop floor).</p><a href="{{ route('manufacturing.production.index') }}" class="rounded bg-indigo-800 px-3 py-1.5 text-sm text-white">Shop floor</a>
                    <a href="{{ route('manufacturing.costing.index') }}" class="rounded bg-emerald-700 px-3 py-1.5 text-sm text-white">Costing</a>
                    <a href="{{ route('manufacturing.quality.index') }}" class="rounded bg-rose-700 px-3 py-1.5 text-sm text-white">QMS</a></div>
                <a href="{{ route('manufacturing.planning.index') }}" class="rounded bg-indigo-600 px-4 py-2 text-sm text-white">Buka perencanaan</a>
            </div>
            @if (session('success'))
                <div class="rounded-md bg-green-50 p-4 text-green-800">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-red-800">
                    <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">Plant</h3>
                <form method="POST" action="{{ route('manufacturing.plants.store') }}" class="mt-4 grid gap-3 md:grid-cols-4">
                    @csrf
                    <input name="code" placeholder="Kode" required maxlength="40" class="rounded border-gray-300">
                    <input name="name" placeholder="Nama pabrik" required class="rounded border-gray-300">
                    <select name="type" class="rounded border-gray-300"><option value="factory">Factory</option><option value="central_kitchen">Central kitchen</option><option value="workshop">Workshop</option></select>
                    <input name="timezone" value="Asia/Jakarta" required class="rounded border-gray-300">
                    <input name="nominal_capacity_per_day" type="number" min="0" value="0" required class="rounded border-gray-300">
                    <input name="capacity_uom" value="unit" required class="rounded border-gray-300">
                    <button class="rounded bg-indigo-600 px-4 py-2 text-white">Tambah plant</button>
                </form>
                <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="p-2 text-left">Kode</th><th class="p-2 text-left">Nama</th><th class="p-2 text-left">Jenis</th><th class="p-2 text-left">Area</th><th class="p-2 text-left">Work center</th></tr></thead><tbody>
                    @foreach ($plants as $plant)<tr class="border-t"><td class="p-2">{{ $plant->code }}</td><td class="p-2">{{ $plant->name }}</td><td class="p-2">{{ $plant->type }}</td><td class="p-2">{{ $plant->areas_count }}</td><td class="p-2">{{ $plant->work_centers_count }}</td></tr>@endforeach
                </tbody></table></div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">Material</h3>
                <form method="POST" action="{{ route('manufacturing.materials.store') }}" class="mt-4 grid gap-3 md:grid-cols-4">
                    @csrf
                    <input name="code" placeholder="Kode material" required class="rounded border-gray-300">
                    <input name="name" placeholder="Nama material" required class="rounded border-gray-300">
                    <select name="kind" class="rounded border-gray-300">@foreach (['raw'=>'Bahan baku','wip'=>'WIP','finished'=>'Barang jadi','packaging'=>'Kemasan','by_product'=>'By-product','co_product'=>'Co-product'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                    <input name="base_uom" placeholder="Satuan dasar" value="pcs" required class="rounded border-gray-300">
                    <label><input type="checkbox" name="lot_tracked" value="1" checked> Lot-tracked</label>
                    <label><input type="checkbox" name="expiry_tracked" value="1"> Expiry-tracked</label>
                    <label><input type="checkbox" name="serial_tracked" value="1"> Serial-tracked</label>
                    <button class="rounded bg-indigo-600 px-4 py-2 text-white">Tambah material</button>
                </form>
                <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="p-2 text-left">Kode</th><th class="p-2 text-left">Nama</th><th class="p-2 text-left">Jenis</th><th class="p-2 text-left">Satuan</th></tr></thead><tbody>
                    @foreach ($materials as $material)<tr class="border-t"><td class="p-2">{{ $material->code }}</td><td class="p-2">{{ $material->name }}</td><td class="p-2">{{ $material->kind }}</td><td class="p-2">{{ $material->base_uom }}</td></tr>@endforeach
                </tbody></table></div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">BOM multi-level</h3>
                <p class="mt-1 text-sm text-gray-600">Siklus, kuantitas nol, dan UoM tanpa konversi akan ditolak.</p>
                <form method="POST" action="{{ route('manufacturing.boms.store') }}" class="mt-4 space-y-3">
                    @csrf
                    <div class="grid gap-3 md:grid-cols-3"><select name="output_material_id" required class="rounded border-gray-300"><option value="">Pilih output</option>@foreach ($materials as $material)<option value="{{ $material->id }}">{{ $material->code }} — {{ $material->name }}</option>@endforeach</select><input name="name" placeholder="Nama BOM" required class="rounded border-gray-300"><input name="effective_from" type="date" value="{{ now()->toDateString() }}" required class="rounded border-gray-300"></div>
                    <input name="output_qty" type="number" step="0.000001" min="0.000001" value="1" required placeholder="Qty output" class="rounded border-gray-300">
                    <input name="output_uom" value="pcs" required placeholder="UoM output" class="rounded border-gray-300">
                    <div class="grid gap-3 md:grid-cols-4"><select name="lines[0][input_material_id]" required class="rounded border-gray-300"><option value="">Pilih bahan</option>@foreach ($materials as $material)<option value="{{ $material->id }}">{{ $material->code }} — {{ $material->name }}</option>@endforeach</select><input name="lines[0][qty]" type="number" step="0.000001" min="0.000001" required placeholder="Qty bahan" class="rounded border-gray-300"><input name="lines[0][uom]" value="pcs" required placeholder="UoM" class="rounded border-gray-300"><input name="lines[0][scrap_percent]" type="number" step="0.01" min="0" max="100" value="0" placeholder="Scrap %" class="rounded border-gray-300"></div>
                    <input name="change_reason" placeholder="Alasan perubahan (opsional)" class="w-full rounded border-gray-300">
                    <button class="rounded bg-indigo-600 px-4 py-2 text-white">Buat versi BOM</button>
                </form>
                <div class="mt-4 space-y-2">@foreach ($boms as $bom)<div class="rounded border p-3"><strong>{{ $bom->outputMaterial->code }} — {{ $bom->name }} (v{{ $bom->version }})</strong><ul class="list-disc pl-5 text-sm">@foreach ($bom->lines as $line)<li>{{ $line->inputMaterial->code }}: {{ $line->qty }} {{ $line->uom }} (scrap {{ $line->scrap_percent }}%)</li>@endforeach</ul></div>@endforeach</div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">Routing</h3>
                <form method="POST" action="{{ route('manufacturing.routings.store') }}" class="mt-4 grid gap-3 md:grid-cols-4">
                    @csrf
                    <select name="output_material_id" required class="rounded border-gray-300"><option value="">Pilih material output</option>@foreach ($materials as $material)<option value="{{ $material->id }}">{{ $material->code }}</option>@endforeach</select>
                    <input name="name" placeholder="Nama routing" required class="rounded border-gray-300"><input name="effective_from" type="date" value="{{ now()->toDateString() }}" required class="rounded border-gray-300">
                    <input name="operations[0][name]" placeholder="Operasi pertama" required class="rounded border-gray-300"><input name="operations[0][setup_minutes]" type="number" min="0" value="0" placeholder="Setup menit" class="rounded border-gray-300"><input name="operations[0][run_minutes_per_unit]" type="number" min="0" value="0" placeholder="Run menit/unit" class="rounded border-gray-300">
                    <button class="rounded bg-indigo-600 px-4 py-2 text-white">Buat routing</button>
                </form>
                <div class="mt-4 space-y-2">@foreach ($routings as $routing)<div class="rounded border p-3">{{ $routing->outputMaterial->code }} — {{ $routing->name }} (v{{ $routing->version }}): {{ $routing->operations->count() }} operasi</div>@endforeach</div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">Formula / resep</h3>
                <form method="POST" action="{{ route('manufacturing.formulas.store') }}" class="mt-4 grid gap-3 md:grid-cols-4">
                    @csrf
                    <select name="output_material_id" required class="rounded border-gray-300"><option value="">Pilih material output</option>@foreach ($materials as $material)<option value="{{ $material->id }}">{{ $material->code }}</option>@endforeach</select>
                    <input name="name" placeholder="Nama formula" required class="rounded border-gray-300"><input name="standard_yield_percent" type="number" min="0.0001" step="0.0001" value="100" required placeholder="Yield %" class="rounded border-gray-300"><input name="yield_tolerance_percent" type="number" min="0" step="0.0001" value="5" required placeholder="Toleransi %" class="rounded border-gray-300"><input name="effective_from" type="date" value="{{ now()->toDateString() }}" required class="rounded border-gray-300"><button class="rounded bg-indigo-600 px-4 py-2 text-white">Simpan draft formula</button>
                </form>
                <div class="mt-4 space-y-2">@foreach ($formulas as $formula)<div class="flex items-center justify-between rounded border p-3"><span>{{ $formula->outputMaterial->code }} — {{ $formula->name }} v{{ $formula->version }} ({{ $formula->status }})</span><span class="flex gap-2">@if ($formula->status === 'draft')<form method="POST" action="{{ route('manufacturing.formulas.submit', $formula) }}">@csrf<button class="text-indigo-700 underline">Ajukan</button></form>@elseif ($formula->status === 'pending_approval')<form method="POST" action="{{ route('manufacturing.formulas.approve', $formula) }}">@csrf<button class="text-green-700 underline">Setujui</button></form>@endif</span></div>@endforeach</div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">Tenaga kerja produksi</h3>
                <form method="POST" action="{{ route('manufacturing.workers.store') }}" class="mt-4 grid gap-3 md:grid-cols-3">
                    @csrf
                    <input name="employee_code" placeholder="Kode pekerja" required class="rounded border-gray-300"><input name="name" placeholder="Nama" required class="rounded border-gray-300"><input name="skills[0]" placeholder="Skill (opsional)" class="rounded border-gray-300"><button class="rounded bg-indigo-600 px-4 py-2 text-white">Tambah pekerja</button>
                </form>
                <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="p-2 text-left">Kode</th><th class="p-2 text-left">Nama</th><th class="p-2 text-left">Status</th><th class="p-2 text-left">Skill</th></tr></thead><tbody>@foreach ($workers as $worker)<tr class="border-t"><td class="p-2">{{ $worker->employee_code }}</td><td class="p-2">{{ $worker->name }}</td><td class="p-2">{{ $worker->status }}</td><td class="p-2">{{ implode(', ', $worker->skills ?? []) }}</td></tr>@endforeach</tbody></table></div>
            </section>
        </div>
    </div>
</x-app-layout>
