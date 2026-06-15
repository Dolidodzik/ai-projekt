@extends('admin.layout')

@section('title', $user->name)

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-[#1754d8]">{{ $user->name }}</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.users.edit', $user) }}" class="rounded-md bg-[#1754d8] px-4 py-2 text-sm font-medium text-white">edytuj</a>
            @if (auth()->id() !== $user->id)
                <form method="POST" action="{{ route('admin.users.delete', $user) }}" onsubmit="return confirm('czy na pewno usunąć?')">
                    @csrf
                    <button type="submit" class="text-sm text-red-600 hover:underline">usuń</button>
                </form>
            @endif
            <a href="{{ route('admin.users.index') }}" class="text-sm text-slate-600 hover:underline">powrót</a>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm text-slate-500">E-mail</dt>
                <dd class="mt-1 font-medium">{{ $user->email }}</dd>
            </div>
            <div>
                <dt class="text-sm text-slate-500">Rola</dt>
                <dd class="mt-1 font-medium">{{ $user->is_admin ? 'Administrator' : 'Użytkownik' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-slate-500">Data rejestracji</dt>
                <dd class="mt-1 font-medium">{{ $user->created_at?->format('Y-m-d H:i') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-slate-500">ID</dt>
                <dd class="mt-1 font-medium">{{ $user->id }}</dd>
            </div>
        </dl>
    </div>
@endsection
