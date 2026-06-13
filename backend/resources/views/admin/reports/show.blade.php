@php
    $statusLabels = [
        'new' => 'Nowe',
        'in_progress' => 'W trakcie',
        'resolved' => 'Rozwiązane',
    ];
@endphp

@extends('admin.layout')

@section('title', $report->title)

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-[#1754d8]">{{ $report->title }}</h1>
        <a href="/admin_panel/reports" class="text-sm text-slate-600 hover:underline">Powrót do listy</a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-6 grid grid-cols-1 gap-4 rounded-md border border-slate-200 bg-slate-50 p-4 md:grid-cols-2">
        <div>
            <div class="text-sm text-slate-500">Użytkownik</div>
            <div class="mt-1 font-medium">{{ $report->user?->name ?? '—' }}</div>
            <div class="text-sm text-slate-500">{{ $report->user?->email }}</div>
        </div>
        <div>
            <div class="text-sm text-slate-500">Utworzono</div>
            <div class="mt-1">{{ $report->created_at?->format('Y-m-d H:i') ?? '—' }}</div>
        </div>
        <div>
            <div class="text-sm text-slate-500">Ostatnia zmiana statusu</div>
            <div class="mt-1">{{ $report->status_updated_at?->format('Y-m-d H:i') ?? '—' }}</div>
        </div>
        <div>
            <div class="text-sm text-slate-500">Aktualny status</div>
            <div class="mt-1 font-medium">{{ $statusLabels[$report->status] ?? $report->status }}</div>
        </div>
    </div>

    <div class="mb-6 rounded-md border border-slate-200 p-4">
        <h2 class="mb-2 text-lg font-medium text-slate-900">Opis</h2>
        <p class="whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{{ $report->description }}</p>
    </div>

    <div class="mb-6 rounded-md border border-slate-200 p-4">
        <h2 class="mb-3 text-lg font-medium text-slate-900">Zdjęcia</h2>
        @if ($report->images->isEmpty())
            <p class="text-sm text-slate-500">Brak załączonych zdjęć.</p>
        @else
            <div class="flex flex-wrap gap-3">
                @foreach ($report->images as $image)
                    @if ($image->url())
                        <a href="{{ $image->url() }}" target="_blank" rel="noreferrer" class="block overflow-hidden rounded-md border border-slate-200">
                            <img src="{{ $image->url() }}" alt="Załącznik" class="h-32 w-32 object-cover">
                        </a>
                    @endif
                @endforeach
            </div>
        @endif
    </div>

    <div class="rounded-md border border-slate-200 p-4">
        <h2 class="mb-3 text-lg font-medium text-slate-900">Zmiana statusu</h2>
        <form method="POST" action="/admin_panel/reports/{{ $report->id }}/status" class="flex flex-wrap items-end gap-3">
            @csrf
            <input type="hidden" name="redirect_to" value="{{ url()->current() }}">
            <div class="space-y-1">
                <label for="status" class="text-sm font-medium">Status</label>
                <select
                    id="status"
                    name="status"
                    class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-[#1754d8]"
                >
                    @foreach ($statusLabels as $value => $label)
                        <option value="{{ $value }}" @selected($report->status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-md bg-[#1754d8] px-4 py-2 text-sm font-medium text-white">Zapisz status</button>
        </form>
    </div>
@endsection
