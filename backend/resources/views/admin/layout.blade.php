<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'admin')</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-white text-slate-900">
    <div class="min-h-screen grid grid-cols-1 md:grid-cols-[220px_1fr]">
        <aside class="border-r border-slate-200 p-4">
            <nav class="space-y-2">
                <a href="/admin_panel/" class="block rounded-md px-3 py-2 hover:bg-slate-100">statystyki</a>
                <a href="/admin_panel/reports" class="block rounded-md px-3 py-2 hover:bg-slate-100">zgłoszenia</a>
                <a href="/admin_panel/announcements" class="block rounded-md px-3 py-2 hover:bg-slate-100">ogłoszenia</a>
            </nav>
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
