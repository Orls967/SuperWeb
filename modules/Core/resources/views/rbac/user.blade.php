@extends('layouts.app')

@section('title', 'RBAC User: ' . $user->name)

@section('content')
<div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <a href="{{ route('admin.rbac.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">← Kembali ke RBAC</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $user->name }}</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ $user->email }} · Legacy role: <span class="font-mono bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded">{{ $user->role ?? '—' }}</span>
        </p>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- RBAC Roles --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">RBAC Roles</h2>
            @if(empty($auth['roles']))
                <p class="text-gray-500 dark:text-gray-400 text-sm">Belum ada RBAC role.</p>
            @else
                <div class="space-y-2 mb-4">
                    @foreach($user->rbacRoles as $role)
                        <div class="flex items-center justify-between p-3 rounded border border-gray-200 dark:border-gray-700">
                            <div>
                                <a href="{{ route('admin.rbac.role', $role) }}" class="font-medium text-blue-600 dark:text-blue-400 hover:underline">{{ $role->label }}</a>
                                <span class="text-xs text-gray-400 ml-1">({{ $role->name }})</span>
                                @if($role->pivot->entity_type)
                                    <span class="block text-xs text-orange-600 dark:text-orange-400">
                                        Scope: {{ $role->pivot->entity_type }}#{{ $role->pivot->entity_id }}
                                    </span>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('admin.rbac.revoke') }}">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $user->id }}">
                                <input type="hidden" name="role_id" value="{{ $role->id }}">
                                <input type="hidden" name="entity_type" value="{{ $role->pivot->entity_type }}">
                                <input type="hidden" name="entity_id" value="{{ $role->pivot->entity_id }}">
                                <button type="submit" class="text-sm text-red-600 dark:text-red-400 hover:underline">Cabut</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Assign new role --}}
            <form method="POST" action="{{ route('admin.rbac.assign') }}" class="mt-4 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                @csrf
                <input type="hidden" name="user_id" value="{{ $user->id }}">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Tambah Role</h3>
                <div class="space-y-3">
                    <select name="role_id" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm">
                        @foreach($allRoles as $role)
                            <option value="{{ $role->id }}">{{ $role->label }} ({{ $role->name }})</option>
                        @endforeach
                    </select>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="entity_type" placeholder="Entity type (opsional)" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm">
                        <input type="number" name="entity_id" placeholder="Entity ID" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm">
                    </div>
                    <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm font-medium transition-colors">
                        Assign Role
                    </button>
                </div>
            </form>
        </div>

        {{-- Effective Permissions --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Effective Permissions ({{ count($auth['permissions']) }})</h2>
            @if(empty($auth['permissions']))
                <p class="text-gray-500 dark:text-gray-400 text-sm">Belum ada permission.</p>
            @else
                <div class="space-y-1">
                    @foreach(collect($auth['permissions'])->sort() as $perm)
                        <div class="flex items-center px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300">
                            <span class="w-2 h-2 rounded-full bg-green-500 mr-2"></span>
                            {{ $perm }}
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
