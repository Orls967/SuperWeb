@extends('layouts.app')

@section('title', 'Role: ' . $role->label)

@section('content')
<div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <a href="{{ route('admin.rbac.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">← Kembali ke RBAC</a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $role->label }}</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                <span class="font-mono bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded">{{ $role->name }}</span>
                @if($role->description) — {{ $role->description }} @endif
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Permissions --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Permissions ({{ $role->permissions->count() }})</h2>
            @foreach($allPermissions as $module => $perms)
                <div class="mb-4">
                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">{{ strtoupper($module) }}</h3>
                    <div class="space-y-1">
                        @foreach($perms as $perm)
                            <form method="POST" action="{{ route('admin.rbac.toggle-permission', $role) }}" class="inline">
                                @csrf
                                <input type="hidden" name="permission_id" value="{{ $perm->id }}">
                                <button type="submit"
                                    class="flex items-center w-full text-left px-3 py-1.5 rounded text-sm hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors {{ $role->permissions->contains('id', $perm->id) ? 'bg-green-50 dark:bg-green-900/20' : '' }}">
                                    <span class="w-5 h-5 mr-2 flex items-center justify-center rounded border
                                        {{ $role->permissions->contains('id', $perm->id)
                                            ? 'bg-green-500 border-green-500 text-white'
                                            : 'border-gray-300 dark:border-gray-600' }}">
                                        @if($role->permissions->contains('id', $perm->id))
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/></svg>
                                        @endif
                                    </span>
                                    <span class="text-gray-700 dark:text-gray-300">{{ $perm->label }}</span>
                                    <span class="ml-auto text-xs text-gray-400">{{ $perm->name }}</span>
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Users with this role --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Users ({{ $role->users->count() }})</h2>
            @if($role->users->isEmpty())
                <p class="text-gray-500 dark:text-gray-400 text-sm">Belum ada user dengan role ini.</p>
            @else
                <div class="space-y-2">
                    @foreach($role->users as $user)
                        <div class="flex items-center justify-between p-3 rounded border border-gray-200 dark:border-gray-700">
                            <div>
                                <a href="{{ route('admin.rbac.user', $user) }}" class="font-medium text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400">
                                    {{ $user->name }}
                                </a>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                                @if($user->pivot->entity_type)
                                    <span class="text-xs text-orange-600 dark:text-orange-400">
                                        Scope: {{ $user->pivot->entity_type }}#{{ $user->pivot->entity_id }}
                                    </span>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('admin.rbac.revoke') }}">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $user->id }}">
                                <input type="hidden" name="role_id" value="{{ $role->id }}">
                                <input type="hidden" name="entity_type" value="{{ $user->pivot->entity_type }}">
                                <input type="hidden" name="entity_id" value="{{ $user->pivot->entity_id }}">
                                <button type="submit" class="text-sm text-red-600 dark:text-red-400 hover:underline" onclick="return confirm('Cabut role ini?')">
                                    Cabut
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
