@extends('layouts.app')

@section('title', 'RBAC — Roles & Permissions')

@section('content')
<div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Roles & Permissions</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Matriks otorisasi data-driven — kelola role dan permission seluruh platform.</p>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Roles Summary --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Roles</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @foreach($roles as $role)
                <a href="{{ route('admin.rbac.role', $role) }}"
                   class="block p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-gray-900 dark:text-white">{{ $role->label }}</span>
                        <span class="text-xs px-2 py-1 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300">
                            {{ $role->name }}
                        </span>
                    </div>
                    <div class="mt-2 flex space-x-4 text-sm text-gray-500 dark:text-gray-400">
                        <span>{{ $role->permissions_count }} permission</span>
                        <span>{{ $role->users_count }} user</span>
                    </div>
                    @if($role->description)
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ $role->description }}</p>
                    @endif
                </a>
            @endforeach
        </div>
    </div>

    {{-- Permission Matrix --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Matriks Permission</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead>
                    <tr class="border-b dark:border-gray-700">
                        <th class="text-left py-2 px-3 font-medium text-gray-600 dark:text-gray-400 sticky left-0 bg-white dark:bg-gray-800">Permission</th>
                        @foreach($roles as $role)
                            <th class="text-center py-2 px-2 font-medium text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ $role->name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($permissionsByModule as $module => $perms)
                        <tr class="bg-gray-50 dark:bg-gray-900">
                            <td colspan="{{ $roles->count() + 1 }}" class="py-1.5 px-3 font-semibold text-gray-700 dark:text-gray-300 uppercase text-[10px] tracking-wider">
                                {{ strtoupper($module) }}
                            </td>
                        </tr>
                        @foreach($perms as $perm)
                            <tr class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-750">
                                <td class="py-1.5 px-3 text-gray-700 dark:text-gray-300 sticky left-0 bg-white dark:bg-gray-800 whitespace-nowrap">
                                    {{ $perm->label }}
                                    <span class="text-gray-400 dark:text-gray-600 ml-1">({{ $perm->name }})</span>
                                </td>
                                @foreach($roles as $role)
                                    <td class="text-center py-1.5 px-2">
                                        @if(in_array($perm->name, $matrix[$role->name] ?? []))
                                            <span class="text-green-600 dark:text-green-400">✓</span>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600">–</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
