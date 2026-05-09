@extends('admin.layout')

@section('title', $announcement->title)

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-[#1754d8]">{{ $announcement->title }}</h1>
        <div class="flex items-center gap-3">
            <a href="/admin_panel/announcements/{{ $announcement->id }}/edit" class="rounded-md bg-[#1754d8] px-4 py-2 text-sm font-medium text-white">edytuj</a>
            <form method="POST" action="/admin_panel/announcements/{{ $announcement->id }}/delete" onsubmit="return confirm('czy na pewno usunąć?')">
                @csrf
                <button type="submit" class="text-sm text-red-600 hover:underline">usuń</button>
            </form>
            <a href="/admin_panel/announcements" class="text-sm text-slate-600 hover:underline">powrót</a>
        </div>
    </div>

    <div class="mb-6 rounded-md border border-slate-200 bg-slate-50 p-4">
        <div class="text-sm text-slate-500">{{ $announcement->published_at?->format('Y-m-d H:i') }}</div>
        <div class="prose mt-3 max-w-none">{!! $announcement->content !!}</div>
    </div>
@endsection
