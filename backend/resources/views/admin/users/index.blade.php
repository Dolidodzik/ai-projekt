@extends('admin.layout')

@section('title', 'Użytkownicy')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-[#1754d8]">Użytkownicy</h1>
        <a href="{{ route('admin.users.create') }}" class="rounded-md bg-[#1754d8] px-4 py-2 font-medium text-white">nowy</a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="GET" action="{{ route('admin.users.index') }}" class="mb-6 grid grid-cols-1 gap-4 rounded-md border border-slate-200 bg-slate-50 p-4 md:grid-cols-4">
        <div class="space-y-1 md:col-span-2">
            <label for="q" class="text-sm font-medium">Szukaj</label>
            <input
                id="q"
                name="q"
                type="search"
                value="{{ $filters['q'] }}"
                placeholder="Imię lub e-mail..."
                class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-[#1754d8]"
            >
        </div>

        <div class="space-y-1">
            <label for="role" class="text-sm font-medium">Rola</label>
            <select id="role" name="role" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-[#1754d8]">
                <option value="">Wszystkie</option>
                <option value="admin" @selected($filters['role'] === 'admin')>Administratorzy</option>
                <option value="user" @selected($filters['role'] === 'user')>Użytkownicy</option>
            </select>
        </div>

        <div class="space-y-1">
            <label for="sort" class="text-sm font-medium">Sortowanie</label>
            <select id="sort" name="sort" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-[#1754d8]">
                <option value="created_at_desc" @selected($filters['sort'] === 'created_at_desc')>Data rejestracji (najnowsze)</option>
                <option value="created_at_asc" @selected($filters['sort'] === 'created_at_asc')>Data rejestracji (najstarsze)</option>
                <option value="name_asc" @selected($filters['sort'] === 'name_asc')>Imię A-Z</option>
                <option value="name_desc" @selected($filters['sort'] === 'name_desc')>Imię Z-A</option>
                <option value="email_asc" @selected($filters['sort'] === 'email_asc')>E-mail A-Z</option>
                <option value="email_desc" @selected($filters['sort'] === 'email_desc')>E-mail Z-A</option>
            </select>
        </div>

        <div class="md:col-span-4">
            <button type="submit" class="rounded-md bg-[#1754d8] px-4 py-2 text-sm font-medium text-white">Filtruj</button>
            <a href="{{ route('admin.users.index') }}" class="ml-2 text-sm text-slate-600 hover:underline">Wyczyść</a>
        </div>
    </form>

    <div class="overflow-hidden rounded-md border border-slate-200">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 font-medium">imię</th>
                    <th class="px-4 py-3 font-medium">e-mail</th>
                    <th class="px-4 py-3 font-medium">rola</th>
                    <th class="px-4 py-3 font-medium">data</th>
                    <th class="px-4 py-3 font-medium">akcje</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr class="border-t border-slate-200">
                        <td class="px-4 py-3">{{ $user->name }}</td>
                        <td class="px-4 py-3">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            @if ($user->is_admin)
                                <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">admin</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">użytkownik</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $user->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('admin.users.edit', $user) }}" class="text-[#1754d8] hover:underline">edycja</a>
                                <a href="{{ route('admin.users.show', $user) }}" class="text-[#1754d8] hover:underline">detale</a>
                                @if (auth()->id() !== $user->id)
                                    <form method="POST" action="{{ route('admin.users.delete', $user) }}" onsubmit="return confirm('czy na pewno usunąć?')">
                                        @csrf
                                        <button type="submit" class="text-red-600 hover:underline">usuń</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="border-t border-slate-200">
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">brak</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($users->hasPages())
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600">
            <div>
                Strona {{ $users->currentPage() }} z {{ $users->lastPage() }}
                ({{ $users->total() }} użytkowników)
            </div>
            <div class="flex items-center gap-2">
                @if ($users->onFirstPage())
                    <span class="rounded-md border border-slate-200 px-3 py-1 text-slate-400">Poprzednia</span>
                @else
                    <a href="{{ $users->previousPageUrl() }}" class="rounded-md border border-slate-300 px-3 py-1 hover:bg-slate-50">Poprzednia</a>
                @endif
                @if ($users->hasMorePages())
                    <a href="{{ $users->nextPageUrl() }}" class="rounded-md border border-slate-300 px-3 py-1 hover:bg-slate-50">Następna</a>
                @else
                    <span class="rounded-md border border-slate-200 px-3 py-1 text-slate-400">Następna</span>
                @endif
            </div>
        </div>
    @endif
@endsection
