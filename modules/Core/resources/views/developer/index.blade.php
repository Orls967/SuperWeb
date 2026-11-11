<x-ds.shell title="Pusat Pengembang API v2">
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <x-ds.breadcrumb :crumbs="[
                    ['label' => 'Portal', 'href' => '#'],
                    ['label' => 'Developer Hub', 'href' => route('developer.index')],
                ]" />
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">Developer Hub & Sandbox API v2</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Dokumentasi interaktif, contoh curl teruji, dan sandbox simulasi B2B API v2.</p>
            </div>
        </div>

        @include('core::api._states')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <x-ds.card title="Spesifikasi Gateway API v2">
                <dl class="divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                    <div class="py-2 flex justify-between">
                        <dt class="text-slate-500">Versi API</dt>
                        <dd class="font-semibold">v2 (JSON:API / RFC 7807)</dd>
                    </div>
                    <div class="py-2 flex justify-between">
                        <dt class="text-slate-500">Rate Limiting</dt>
                        <dd class="font-semibold">Integer Token Bucket (B1)</dd>
                    </div>
                    <div class="py-2 flex justify-between">
                        <dt class="text-slate-500">Idempotensi</dt>
                        <dd class="font-semibold">Idempotency-Key (TTL 24 jam)</dd>
                    </div>
                    <div class="py-2 flex justify-between">
                        <dt class="text-slate-500">Format Halaman</dt>
                        <dd class="font-semibold">Cursor Pagination (Keyset)</dd>
                    </div>
                </dl>
            </x-ds.card>

            <x-ds.card title="Contoh Panggilan cURL">
                <div class="space-y-3 font-mono text-xs">
                    <div class="bg-slate-900 text-slate-100 p-3 rounded-lg overflow-x-auto">
                        # Mengambil Pesanan Pembelian<br>
                        curl -X GET '{{ config('app.url') }}/api/v2/purchase-orders?limit=10' \<br>
                        &nbsp;&nbsp;-H 'Authorization: Bearer sw_live_xxx'
                    </div>
                    <div class="bg-slate-900 text-slate-100 p-3 rounded-lg overflow-x-auto">
                        # Mutasi Idempoten<br>
                        curl -X POST '{{ config('app.url') }}/api/v2/purchase-orders' \<br>
                        &nbsp;&nbsp;-H 'Authorization: Bearer sw_live_xxx' \<br>
                        &nbsp;&nbsp;-H 'Idempotency-Key: 1111-2222-3333-4444'
                    </div>
                </div>
            </x-ds.card>
        </div>
    </div>
</x-ds.shell>

