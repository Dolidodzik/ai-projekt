@extends('admin.layout')

@section('title', 'Edycja: '.$table)

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.database.table', $table) }}" class="text-sm text-slate-600 hover:underline">Powrót do tabeli {{ $table }}</a>
        <h1 class="mt-1 text-2xl font-semibold text-[#1754d8]">Edycja rekordu: {{ $table }}</h1>
    </div>

    @include('admin.database.form', [
        'action' => route('admin.database.update', ['table' => $table, 'record' => $record]),
        'row' => $row,
    ])
@endsection
