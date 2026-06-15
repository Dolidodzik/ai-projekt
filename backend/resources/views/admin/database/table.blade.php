@extends('admin.layout')

@section('title', $table)

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('admin.database.index') }}" class="text-sm text-slate-600 hover:underline">Powrót do listy tabel</a>
            <h1 class="mt-1 text-2xl font-semibold text-[#1754d8]">{{ $table }}</h1>
        </div>
        <a href="{{ route('admin.database.create', $table) }}" class="rounded-md bg-[#1754d8] px-4 py-2 font-medium text-white">nowy rekord</a>
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

    <form method="GET" action="{{ route('admin.database.table', $table) }}" class="mb-6 grid grid-cols-1 gap-4 rounded-md border border-slate-200 bg-slate-50 p-4 md:grid-cols-4">
        <div class="space-y-1 md:col-span-2">
            <label for="q" class="text-sm font-medium">Szukaj</label>
            <input id="q" name="q" type="search" value="{{ $filters['q'] }}" placeholder="Szukaj w kolumnach tekstowych..." class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
        </div>
        <div class="space-y-1">
            <label for="sort" class="text-sm font-medium">Sortowanie</label>
            <select id="sort" name="sort" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                @foreach ($columns as $column)
                    <option value="{{ $column->column_name }}" @selected($filters['sort'] === $column->column_name)>{{ $column->column_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="space-y-1">
            <label for="direction" class="text-sm font-medium">Kierunek</label>
            <select id="direction" name="direction" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                <option value="desc" @selected($filters['direction'] === 'desc')>malejąco</option>
                <option value="asc" @selected($filters['direction'] === 'asc')>rosnąco</option>
            </select>
        </div>
        <div class="md:col-span-4">
            <button type="submit" class="rounded-md bg-[#1754d8] px-4 py-2 text-sm font-medium text-white">Filtruj</button>
            <a href="{{ route('admin.database.table', $table) }}" class="ml-2 text-sm text-slate-600 hover:underline">Wyczyść</a>
        </div>
    </form>

    <div class="overflow-x-auto rounded-md border border-slate-200">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="bg-slate-50">
                <tr>
                    @foreach ($displayColumns as $columnName)
                        <th class="px-4 py-3 font-medium">{{ $columnName }}</th>
                    @endforeach
                    <th class="px-4 py-3 font-medium">akcje</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php($record = (string) $row->{$primaryKey})
                    <tr class="border-t border-slate-200">
                        @foreach ($displayColumns as $columnName)
                            <td class="max-w-[220px] truncate px-4 py-3" title="{{ $row->{$columnName} }}">
                                {{ is_bool($row->{$columnName}) ? ($row->{$columnName} ? 'true' : 'false') : $row->{$columnName} }}
                            </td>
                        @endforeach
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('admin.database.edit', ['table' => $table, 'record' => $record]) }}" class="text-[#1754d8] hover:underline">edycja</a>
                                <a href="{{ route('admin.database.show', ['table' => $table, 'record' => $record]) }}" class="text-[#1754d8] hover:underline">detale</a>
                                <form method="POST" action="{{ route('admin.database.delete', ['table' => $table, 'record' => $record]) }}" onsubmit="return confirm('Czy na pewno usunąć rekord?')">
                                    @csrf
                                    <button type="submit" class="text-red-600 hover:underline">usuń</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="border-t border-slate-200">
                        <td colspan="{{ count($displayColumns) + 1 }}" class="px-4 py-6 text-center text-slate-500">brak rekordów</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($rows->hasPages())
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600">
            <div>Strona {{ $rows->currentPage() }} z {{ $rows->lastPage() }} ({{ $rows->total() }} rekordów)</div>
            <div class="flex items-center gap-2">
                @if ($rows->onFirstPage())
                    <span class="rounded-md border border-slate-200 px-3 py-1 text-slate-400">Poprzednia</span>
                @else
                    <a href="{{ $rows->previousPageUrl() }}" class="rounded-md border border-slate-300 px-3 py-1 hover:bg-slate-50">Poprzednia</a>
                @endif
                @if ($rows->hasMorePages())
                    <a href="{{ $rows->nextPageUrl() }}" class="rounded-md border border-slate-300 px-3 py-1 hover:bg-slate-50">Następna</a>
                @else
                    <span class="rounded-md border border-slate-200 px-3 py-1 text-slate-400">Następna</span>
                @endif
            </div>
        </div>
    @endif
@endsection
