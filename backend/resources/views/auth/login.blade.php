<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <title>Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-white text-slate-900 flex items-center justify-center p-4">
    <form method="POST" action="/login" class="w-full max-w-sm border border-slate-200 rounded-md p-6 space-y-4">
        @csrf
        <h1 class="text-xl font-semibold text-[#1754d8]">Admin Login</h1>
        @if ($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif
        <div class="space-y-1">
            <label for="email" class="text-sm font-medium">Email</label>
            <input id="email" name="email" type="email" required value="{{ old('email') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 outline-none focus:border-[#1754d8]" />
        </div>
        <div class="space-y-1">
            <label for="password" class="text-sm font-medium">Password</label>
            <input id="password" name="password" type="password" required class="w-full rounded-md border border-slate-300 px-3 py-2 outline-none focus:border-[#1754d8]" />
        </div>
        <button type="submit" class="w-full rounded-md bg-[#1754d8] text-white py-2 font-medium">Log in</button>
    </form>
</body>
</html>
