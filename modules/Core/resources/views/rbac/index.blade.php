@extends('layouts.app')

@section('title', 'RBAC — Roles & Permissions')

@section('content')
<div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
    <x-ds.breadcrumb :items="[
        ['label' => 'Beranda', 'url' => route('dashboard')],
        ['label' => 'Administrasi', 'url' => '#'],
        ['label' => 'Roles & Permissions', 'url' => '#', 'active' => true],
    ]" />

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Roles & Permissions (RBAC)</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Matriks otorisasi data-driven — kelola role dan permission seluruh platform SuperWeb.</p>
        </div>
        <div class="flex items-center gap-2">
            <x-ds.badge-status status="released" />
            <span class="text-xs font-semibold px-2 py-0.5 rounded bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                Data-Driven RBAC
            </span>
        </div>
    </div>

    @if(session('success'))
        <x-ds.toast type="success" :message="session('success')" />
    @endif

    {{-- Roles Summary Menggunakan x-ds.card --}}
    <x-ds.card title="Daftar Peran Pengguna (Roles)">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @foreach($roles as $role)
                <a href="{{ route('admin.rbac.role', $role) }}"
                   class="block p-4 border border-slate-200 dark:border-slate-800 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:border-blue-500/40 transition">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-slate-900 dark:text-white text-sm">{{ $role->label }}</span>
                        <span class="text-xs font-mono px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                            {{ $role->name }}
                        </span>
                    </div>
                    <div class="mt-2 flex space-x-3 text-xs text-slate-500 dark:text-slate-400">
                        <span>{{ $role->permissions_count }} permission</span>
                        <span>&bull;</span>
                        <span>{{ $role->users_count }} user</span>
                    </div>
                    @if($role->description)
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400 line-clamp-2">{{ $role->description }}</p>
                    @endif
                </a>
            @endforeach
        </div>
    </x-ds.card>

    {{-- Permission Matrix --}}
    <x-ds.card title="Matriks Hak Akses Antarmuka (Permission Matrix)">
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs text-left">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50">
                        <th class="py-2.5 px-4 font-semibold text-slate-600 dark:text-slate-300 sticky left-0 bg-slate-50 dark:bg-slate-900/50">Permission</th>
                        @foreach($roles as $role)
                            <th class="text-center py-2.5 px-2 font-semibold text-slate-600 dark:text-slate-300 whitespace-nowrap">{{ $role->name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($permissionsByModule as $module => $perms)
                        <tr class="bg-slate-100/70 dark:bg-slate-850">
                            <td colspan="{{ $roles->count() + 1 }}" class="py-2 px-4 font-bold text-slate-800 dark:text-slate-200 uppercase text-[11px] tracking-wider">
                                MODUL {{ strtoupper($module) }}
                            </td>
                        </tr>
                        @foreach($perms as $perm)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition">
                                <td class="py-2 px-4 text-slate-800 dark:text-slate-200 sticky left-0 bg-white dark:bg-slate-900 whitespace-nowrap">
                                    {{ $perm->label }}
                                    <span class="text-slate-400 font-mono ml-1 text-[11px]">({{ $perm->name }})</span>
                                </td>
                                @foreach($roles as $role)
                                    <td class="text-center py-2 px-2">
                                        @if(in_array($perm->name, $matrix[$role->name] ?? []))
                                            <span class="text-emerald-600 dark:text-emerald-400 font-bold">&check;</span>
                                        @else
                                            <span class="text-slate-300 dark:text-slate-700">&ndash;</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ds.card>
</div>
@endsection
