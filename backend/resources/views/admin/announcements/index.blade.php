@extends('admin.layout')

@section('title', 'ogłoszenia')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-[#1754d8]">Ogłoszenia</h1>
        <a href="/admin_panel/announcements/new" class="rounded-md bg-[#1754d8] px-4 py-2 font-medium text-white">nowe</a>
    </div>

    <div class="overflow-hidden rounded-md border border-slate-200">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 font-medium">tytuł</th>
                    <th class="px-4 py-3 font-medium">data</th>
                    <th class="px-4 py-3 font-medium">akcje</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($announcements as $announcement)
                    <tr class="border-t border-slate-200">
                        <td class="px-4 py-3">{{ $announcement->title }}</td>
                        <td class="px-4 py-3">{{ $announcement->published_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <a href="/admin_panel/announcements/{{ $announcement->id }}/edit" class="text-[#1754d8] hover:underline">edycja</a>
                                <a href="/admin_panel/announcements/{{ $announcement->id }}" class="text-[#1754d8] hover:underline">detale</a>
                                <form method="POST" action="/admin_panel/announcements/{{ $announcement->id }}/delete" onsubmit="return confirm('czy na pewno usunąć?')">
                                    @csrf
                                    <button type="submit" class="text-red-600 hover:underline">usuń</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="border-t border-slate-200">
                        <td colspan="3" class="px-4 py-6 text-center text-slate-500">brak</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
