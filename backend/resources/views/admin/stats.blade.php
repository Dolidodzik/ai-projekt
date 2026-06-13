@extends('admin.layout')

@section('title', 'statystyki')

@section('content')
    <h1 class="mb-6 text-2xl font-semibold text-[#1754d8]">Statystyki</h1>

    <section class="mb-8">
        <h2 class="mb-3 text-lg font-medium text-slate-900">wyszukiwania tras</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-md border border-slate-200 p-4">
                <div class="text-sm text-slate-500">łącznie</div>
                <div class="mt-1 text-2xl font-semibold text-[#1754d8]">{{ number_format($searchStats['total'], 0, ',', ' ') }}</div>
            </div>
            <div class="rounded-md border border-slate-200 p-4">
                <div class="text-sm text-slate-500">dzisiaj</div>
                <div class="mt-1 text-2xl font-semibold text-[#1754d8]">{{ number_format($searchStats['today'], 0, ',', ' ') }}</div>
            </div>
            <div class="rounded-md border border-slate-200 p-4">
                <div class="text-sm text-slate-500">ten tydzień</div>
                <div class="mt-1 text-2xl font-semibold text-[#1754d8]">{{ number_format($searchStats['this_week'], 0, ',', ' ') }}</div>
            </div>
            <div class="rounded-md border border-slate-200 p-4">
                <div class="text-sm text-slate-500">ten miesiąc</div>
                <div class="mt-1 text-2xl font-semibold text-[#1754d8]">{{ number_format($searchStats['this_month'], 0, ',', ' ') }}</div>
            </div>
        </div>
    </section>

    <section class="mb-8 rounded-md border border-slate-200 bg-slate-50 p-4">
        <h2 class="mb-3 text-lg font-medium text-slate-900">aktywność użytkowników</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-md border border-slate-200 bg-white p-4">
                <div class="text-sm text-slate-500">użytkownicy z wyszukiwaniami</div>
                <div class="mt-1 text-2xl font-semibold text-[#1754d8]">{{ number_format($userStats['unique_users'], 0, ',', ' ') }}</div>
            </div>
            <div class="rounded-md border border-slate-200 bg-white p-4">
                <div class="text-sm text-slate-500">średni czas przejazdu</div>
                <div class="mt-1 text-2xl font-semibold text-[#1754d8]">{{ $userStats['avg_duration'] }} min</div>
            </div>
        </div>
    </section>

    <section class="mb-8">
        <h2 class="mb-3 text-lg font-medium text-slate-900">bilety</h2>
        <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-md border border-slate-200 p-4">
                <div class="text-sm text-slate-500">sprzedane łącznie</div>
                <div class="mt-1 text-2xl font-semibold text-[#1754d8]">{{ number_format($ticketStats['total'], 0, ',', ' ') }}</div>
            </div>
            <div class="rounded-md border border-slate-200 p-4">
                <div class="text-sm text-slate-500">aktywne bilety</div>
                <div class="mt-1 text-2xl font-semibold text-[#1754d8]">{{ number_format($ticketStats['active'], 0, ',', ' ') }}</div>
            </div>
            <div class="rounded-md border border-slate-200 p-4">
                <div class="text-sm text-slate-500">przychód łącznie</div>
                <div class="mt-1 text-2xl font-semibold text-[#1754d8]">{{ number_format($ticketStats['revenue_total'], 2, ',', ' ') }} zł</div>
            </div>
            <div class="rounded-md border border-slate-200 p-4">
                <div class="text-sm text-slate-500">sprzedaż w tym miesiącu</div>
                <div class="mt-1 text-2xl font-semibold text-[#1754d8]">{{ number_format($ticketStats['sold_this_month'], 0, ',', ' ') }}</div>
                <div class="mt-1 text-sm text-slate-500">{{ number_format($ticketStats['revenue_this_month'], 2, ',', ' ') }} zł</div>
            </div>
        </div>

        <div class="overflow-hidden rounded-md border border-slate-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 font-medium">typ biletu</th>
                        <th class="px-4 py-3 font-medium text-right">sprzedane</th>
                        <th class="px-4 py-3 font-medium text-right">przychód</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ticketsByType as $row)
                        <tr class="border-t border-slate-200">
                            <td class="px-4 py-3">{{ $row->name }}</td>
                            <td class="px-4 py-3 text-right">{{ $row->sold }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format((float) $row->revenue, 2, ',', ' ') }} zł</td>
                        </tr>
                    @empty
                        <tr class="border-t border-slate-200">
                            <td colspan="3" class="px-4 py-6 text-center text-slate-500">brak</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section>
            <h2 class="mb-3 text-lg font-medium text-slate-900">najpopularniejsze linie</h2>
            <div class="overflow-hidden rounded-md border border-slate-200">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 font-medium">linia</th>
                            <th class="px-4 py-3 font-medium">nazwa</th>
                            <th class="px-4 py-3 font-medium text-right">wyszukiwań</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($topRoutes as $route)
                            <tr class="border-t border-slate-200">
                                <td class="px-4 py-3 font-medium">{{ $route->route_short_name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $route->route_long_name ?: '—' }}</td>
                                <td class="px-4 py-3 text-right">{{ $route->searches }}</td>
                            </tr>
                        @empty
                            <tr class="border-t border-slate-200">
                                <td colspan="3" class="px-4 py-6 text-center text-slate-500">brak</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section>
            <h2 class="mb-3 text-lg font-medium text-slate-900">najpopularniejsze trasy</h2>
            <div class="overflow-hidden rounded-md border border-slate-200">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 font-medium">od</th>
                            <th class="px-4 py-3 font-medium">do</th>
                            <th class="px-4 py-3 font-medium text-right">wyszukiwań</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($topPairs as $pair)
                            <tr class="border-t border-slate-200">
                                <td class="px-4 py-3">{{ $pair->from_stop }}</td>
                                <td class="px-4 py-3">{{ $pair->to_stop }}</td>
                                <td class="px-4 py-3 text-right">{{ $pair->searches }}</td>
                            </tr>
                        @empty
                            <tr class="border-t border-slate-200">
                                <td colspan="3" class="px-4 py-6 text-center text-slate-500">brak</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
