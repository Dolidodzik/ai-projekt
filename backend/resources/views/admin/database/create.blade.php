@extends('admin.layout')

@section('title', 'Nowy rekord: '.$table)

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.database.table', $table) }}" class="text-sm text-slate-600 hover:underline">Powrót do tabeli {{ $table }}</a>
        <h1 class="mt-1 text-2xl font-semibold text-[#1754d8]">Nowy rekord: {{ $table }}</h1>
    </div>

    @include('admin.database.form', ['action' => route('admin.database.store', $table)])
@endsection
