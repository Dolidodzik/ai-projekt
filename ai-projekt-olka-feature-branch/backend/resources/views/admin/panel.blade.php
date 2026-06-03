<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-white text-slate-900">
    <main class="max-w-3xl mx-auto p-6">
        <h1 class="text-3xl font-semibold text-[#1754d8]">hello admin!</h1>
        <form method="POST" action="{{ route('logout') }}" class="mt-6">
            @csrf
            <button type="submit" class="rounded-md bg-[#1754d8] text-white px-4 py-2 font-medium">Log out</button>
        </form>
    </main>
</body>
</html>
