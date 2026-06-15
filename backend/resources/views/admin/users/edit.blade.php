@extends('admin.layout')

@section('title', 'Edycja użytkownika')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-[#1754d8]">Edycja: {{ $user->name }}</h1>
        <a href="{{ route('admin.users.index') }}" class="text-sm text-slate-600 hover:underline">powrót</a>
    </div>

    @include('admin.users.form', ['action' => route('admin.users.update', $user), 'user' => $user])
@endsection
