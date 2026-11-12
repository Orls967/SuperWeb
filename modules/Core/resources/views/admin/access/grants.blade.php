<x-ds.shell title="Tata Kelola Hak Akses (Access Grants)">
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <x-ds.breadcrumb :crumbs="[
                    ['label' => 'Admin', 'href' => '#'],
                    ['label' => 'Akses & Identitas', 'href' => route('admin.access.grants')],
                    ['label' => 'Access Grants', 'href' => route('admin.access.grants')],
                ]" />
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">Siklus Hidup Hak Akses (Access Grants)</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pengawasan hak akses sementara 8 status, persetujuan manajerial, dan pencabutan sub-detik.</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.access.grants') }}">
                <x-ds.badge :variant="empty($status) ? 'primary' : 'neutral'">Semua Status</x-ds.badge>
            </a>
            <a href="{{ route('admin.access.grants', ['status' => 'requested']) }}">
                <x-ds.badge :variant="$status === 'requested' ? 'primary' : 'neutral'">Requested</x-ds.badge>
            </a>
            <a href="{{ route('admin.access.grants', ['status' => 'active']) }}">
                <x-ds.badge :variant="$status === 'active' ? 'primary' : 'neutral'">Active</x-ds.badge>
            </a>
            <a href="{{ route('admin.access.grants', ['status' => 'suspended']) }}">
                <x-ds.badge :variant="$status === 'suspended' ? 'primary' : 'neutral'">Suspended</x-ds.badge>
            </a>
            <a href="{{ route('admin.access.grants', ['status' => 'revoked']) }}">
                <x-ds.badge :variant="$status === 'revoked' ? 'primary' : 'neutral'">Revoked</x-ds.badge>
            </a>
            <a href="{{ route('admin.access.grants', ['status' => 'expired']) }}">
                <x-ds.badge :variant="$status === 'expired' ? 'primary' : 'neutral'">Expired</x-ds.badge>
            </a>
        </div>

        <x-ds.card title="Daftar Permohonan & Hak Akses Aktif">
            @if ($grants->isEmpty())
                <x-ds.empty
                    title="Tidak Ada Hak Akses"
                    description="Belum ada permohonan atau izin hak akses yang tercatat untuk filter ini."
                />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Kode Izin</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Pengguna</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Tenant</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Resource & Aksi</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Status</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Versi Cache</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Masa Berlaku</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($grants as $g)
                                <tr>
                                    <td class="px-4 py-2 font-mono font-medium">{{ $g->grant_code }}</td>
                                    <td class="px-4 py-2">{{ $g->user?->name ?? 'User #' . $g->user_id }}</td>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $g->tenant?->name ?? $g->tenant_id }}</td>
                                    <td class="px-4 py-2">
                                        <span class="font-mono text-xs">{{ $g->resource }}</span> :
                                        <span class="font-semibold text-xs">{{ $g->action }}</span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <x-ds.badge :variant="match($g->status) {
                                            'active' => 'success',
                                            'requested', 'policy_checked', 'approved', 'provisioned' => 'warning',
                                            'suspended' => 'neutral',
                                            'revoked', 'expired' => 'danger',
                                            default => 'neutral'
                                        }">
                                            {{ strtoupper($g->status) }}
                                        </x-ds.badge>
                                    </td>
                                    <td class="px-4 py-2 font-mono text-xs">v{{ $g->grant_version }}</td>
                                    <td class="px-4 py-2 text-xs text-slate-500">
                                        {{ $g->valid_to?->format('d M Y H:i') ?? 'Tanpa Batas' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $grants->links() }}
                </div>
            @endif
        </x-ds.card>
    </div>
</x-ds.shell>

