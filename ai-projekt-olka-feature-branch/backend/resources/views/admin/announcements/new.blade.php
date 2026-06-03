@extends('admin.layout')

@section('title', 'nowe ogłoszenie')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-[#1754d8]">nowe ogłoszenie</h1>
        <a href="/admin_panel/announcements" class="text-sm text-slate-600 hover:underline">powrót</a>
    </div>

    @include('admin.announcements.form', ['action' => '/admin_panel/announcements/new'])
@endsection
