<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <title>@yield('title', 'admin')</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-white text-slate-900">
    <div class="min-h-screen grid grid-cols-1 md:grid-cols-[220px_1fr]">
        <aside class="flex min-h-screen flex-col border-r border-slate-200 p-4">
            <div class="mb-8 px-3 text-3xl font-bold">Autobusy</div>
            <nav class="space-y-2">
                <a href="/admin_panel/" class="block rounded-md px-3 py-2 hover:bg-slate-100">Statystyki</a>
                <a href="/admin_panel/reports" class="block rounded-md px-3 py-2 hover:bg-slate-100">Zgłoszenia</a>
                <a href="/admin_panel/announcements" class="block rounded-md px-3 py-2 hover:bg-slate-100">Ogłoszenia</a>
            </nav>
            <div class="mt-auto space-y-2 border-t border-slate-200 pt-4">
                <div class="text-sm font-medium">{{ auth()->user()->name }}</div>
                <div class="text-xs text-slate-500">{{ auth()->user()->email }}</div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full rounded-md bg-[#1754d8] px-3 py-2 text-sm font-medium text-white">wyloguj</button>
                </form>
            </div>
        </aside>
        <div class="flex min-h-screen flex-col">
            <main class="flex-1 p-6">
                @yield('content')
            </main>
            <footer class="border-t border-slate-200 p-4 text-center text-sm text-slate-600">rzeszowskie autobusy</footer>
        </div>
    </div>
</body>
</html>
