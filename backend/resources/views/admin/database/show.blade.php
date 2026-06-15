@extends('admin.layout')

@section('title', $table.' #'.$row->{$primaryKey})

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('admin.database.table', $table) }}" class="text-sm text-slate-600 hover:underline">Powrót do tabeli {{ $table }}</a>
            <h1 class="mt-1 text-2xl font-semibold text-[#1754d8]">{{ $table }} #{{ $row->{$primaryKey} }}</h1>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.database.edit', ['table' => $table, 'record' => (string) $row->{$primaryKey}]) }}" class="rounded-md bg-[#1754d8] px-4 py-2 text-sm font-medium text-white">edytuj</a>
            <form method="POST" action="{{ route('admin.database.delete', ['table' => $table, 'record' => (string) $row->{$primaryKey}]) }}" onsubmit="return confirm('Czy na pewno usunąć rekord?')">
                @csrf
                <button type="submit" class="text-sm text-red-600 hover:underline">usuń</button>
            </form>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">
            {{ session('status') }}
        </div>
    @endif

    <div class="overflow-hidden rounded-md border border-slate-200">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 font-medium">kolumna</th>
                    <th class="px-4 py-3 font-medium">typ</th>
                    <th class="px-4 py-3 font-medium">wartość</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($columns as $column)
                    <tr class="border-t border-slate-200">
                        <td class="px-4 py-3 font-medium">{{ $column->column_name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $column->data_type }}</td>
                        <td class="px-4 py-3 break-all">
                            @php($value = $row->{$column->column_name})
                            @if ($column->column_name === 'password_hash')
                                ********
                            @elseif (is_bool($value))
                                {{ $value ? 'true' : 'false' }}
                            @else
                                {{ $value }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
