@php
    $statusLabels = [
        'new' => 'Nowe',
        'in_progress' => 'W trakcie',
        'resolved' => 'Rozwiązane',
    ];
@endphp

@extends('admin.layout')

@section('title', 'Zgłoszenia')

@section('content')
    <h1 class="mb-6 text-2xl font-semibold text-[#1754d8]">Zgłoszenia</h1>

    @if (session('status'))
        <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">
            {{ session('status') }}
        </div>
    @endif

    <form method="GET" action="/admin_panel/reports" class="mb-6 grid grid-cols-1 gap-4 rounded-md border border-slate-200 bg-slate-50 p-4 md:grid-cols-3">
        <div class="space-y-1 md:col-span-1">
            <label for="q" class="text-sm font-medium">Szukaj</label>
            <input
                id="q"
                name="q"
                type="search"
                value="{{ $filters['q'] }}"
                placeholder="Tytuł lub opis..."
                class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-[#1754d8]"
            >
        </div>

        <div class="space-y-1">
            <label for="status" class="text-sm font-medium">Status</label>
            <select
                id="status"
                name="status"
                class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-[#1754d8]"
            >
                <option value="">Wszystkie</option>
                @foreach ($statusLabels as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="space-y-1">
            <label for="sort" class="text-sm font-medium">Sortowanie</label>
            <select
                id="sort"
                name="sort"
                class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-[#1754d8]"
            >
                <option value="status_updated_at_desc" @selected($filters['sort'] === 'status_updated_at_desc')>Data zmiany statusu - malejąco</option>
                <option value="status_updated_at_asc" @selected($filters['sort'] === 'status_updated_at_asc')>Data zmiany statusu - rosnąco</option>
                <option value="created_at_desc" @selected($filters['sort'] === 'created_at_desc')>Data utworzenia - malejąco</option>
                <option value="created_at_asc" @selected($filters['sort'] === 'created_at_asc')>Data utworzenia - rosnąco</option>
            </select>
        </div>

        <div class="flex items-end gap-2 md:col-span-3">
            <button type="submit" class="rounded-md bg-[#1754d8] px-4 py-2 text-sm font-medium text-white">Filtruj</button>
            <a href="/admin_panel/reports" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Wyczyść</a>
        </div>
    </form>

    <div class="overflow-hidden rounded-md border border-slate-200">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 font-medium">Tytuł</th>
                    <th class="px-4 py-3 font-medium">Użytkownik</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">Utworzono</th>
                    <th class="px-4 py-3 font-medium">Zmiana statusu</th>
                    <th class="px-4 py-3 font-medium">Akcje</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reports as $report)
                    <tr class="border-t border-slate-200 align-top">
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900">{{ $report->title }}</div>
                            <div class="mt-1 line-clamp-2 text-xs text-slate-500">{{ $report->description }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div>{{ $report->user?->name ?? '—' }}</div>
                            <div class="text-xs text-slate-500">{{ $report->user?->email }}</div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ $statusLabels[$report->status] ?? $report->status }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $report->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $report->status_updated_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <a href="/admin_panel/reports/{{ $report->id }}" class="text-[#1754d8] hover:underline">Szczegóły</a>
                        </td>
                    </tr>
                @empty
                    <tr class="border-t border-slate-200">
                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">Brak zgłoszeń.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
