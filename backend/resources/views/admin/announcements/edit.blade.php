@extends('admin.layout')

@section('title', 'edycja ogłoszenia')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-[#1754d8]">Edycja ogłoszenia</h1>
        <a href="/admin_panel/announcements" class="text-sm text-slate-600 hover:underline">powrót</a>
    </div>

    @include('admin.announcements.form', ['action' => '/admin_panel/announcements/'.$announcement->id.'/edit', 'announcement' => $announcement])
@endsection
