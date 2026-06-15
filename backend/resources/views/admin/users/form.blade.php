@php($item = $user ?? null)

@if ($errors->any())
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        {{ $errors->first() }}
    </div>
@endif

<form method="POST" action="{{ $action }}" class="space-y-4">
    @csrf
    <div class="space-y-1">
        <label for="name" class="text-sm font-medium">imię i nazwisko</label>
        <input id="name" name="name" type="text" required value="{{ old('name', $item?->name ?? '') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 outline-none focus:border-[#1754d8]">
    </div>

    <div class="space-y-1">
        <label for="email" class="text-sm font-medium">e-mail</label>
        <input id="email" name="email" type="email" required value="{{ old('email', $item?->email ?? '') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 outline-none focus:border-[#1754d8]">
    </div>

    <div class="space-y-1">
        <label for="password" class="text-sm font-medium">
            hasło
            @if ($item)
                <span class="font-normal text-slate-500">(pozostaw puste, aby nie zmieniać)</span>
            @endif
        </label>
        <input id="password" name="password" type="password" @required(!$item) minlength="8" class="w-full rounded-md border border-slate-300 px-3 py-2 outline-none focus:border-[#1754d8]">
    </div>

    <div class="flex items-center gap-2">
        <input
            id="is_admin"
            name="is_admin"
            type="checkbox"
            value="1"
            @checked(old('is_admin', $item?->is_admin ?? false))
            class="rounded border-slate-300 text-[#1754d8] focus:ring-[#1754d8]"
        >
        <label for="is_admin" class="text-sm font-medium">administrator</label>
    </div>

    <button type="submit" class="rounded-md bg-[#1754d8] px-4 py-2 font-medium text-white">zapisz</button>
</form>
