@extends('admin.layout')

@section('title', 'Baza danych')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-[#1754d8]">Baza danych</h1>
        <p class="mt-2 text-sm text-slate-600">Wybierz tabelę, aby przeglądać i edytować rekordy z walidacją typów kolumn.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($tables as $table)
            <a href="{{ route('admin.database.table', $table['name']) }}" class="rounded-md border border-slate-200 p-4 hover:border-[#1754d8] hover:bg-slate-50">
                <div class="font-medium text-slate-900">{{ $table['name'] }}</div>
                <div class="mt-1 text-sm text-slate-500">{{ number_format($table['count'], 0, ',', ' ') }} rekordów</div>
            </a>
        @endforeach
    </div>
@endsection
