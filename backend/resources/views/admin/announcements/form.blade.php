@php($item = $announcement ?? null)

@if ($errors->any())
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        {{ $errors->first() }}
    </div>
@endif

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-4">
    @csrf
    <div class="space-y-1">
        <label for="title" class="text-sm font-medium">tytuł</label>
        <input id="title" name="title" type="text" required value="{{ old('title', $item?->title ?? '') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 outline-none focus:border-[#1754d8]">
    </div>

    <div class="space-y-2">
        <label class="text-sm font-medium">treść</label>
        <div class="flex flex-wrap gap-2">
            <button type="button" title="pogrubienie" onclick="document.execCommand('bold')" class="rounded-md border border-slate-300 p-2 text-sm">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 5h7a4 4 0 0 1 0 8H7z"/><path d="M7 13h8a4 4 0 0 1 0 8H7z"/></svg>
            </button>
            <button type="button" title="kursywa" onclick="document.execCommand('italic')" class="rounded-md border border-slate-300 p-2 text-sm">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 4h-6"/><path d="M9 20h6"/><path d="M14 4l-4 16"/></svg>
            </button>
            <button type="button" title="podkreślenie" onclick="document.execCommand('underline')" class="rounded-md border border-slate-300 p-2 text-sm">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 4v7a5 5 0 0 0 10 0V4"/><path d="M5 20h14"/></svg>
            </button>
            <button type="button" title="przekreślenie" onclick="document.execCommand('strikeThrough')" class="rounded-md border border-slate-300 p-2 text-sm">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12h16"/><path d="M8 6h8"/><path d="M8 18h8"/></svg>
            </button>
            <button type="button" title="do lewej" onclick="document.execCommand('justifyLeft')" class="rounded-md border border-slate-300 p-2 text-sm">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16"/><path d="M4 10h10"/><path d="M4 14h16"/><path d="M4 18h10"/></svg>
            </button>
            <button type="button" title="do środka" onclick="document.execCommand('justifyCenter')" class="rounded-md border border-slate-300 p-2 text-sm">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16"/><path d="M7 10h10"/><path d="M4 14h16"/><path d="M7 18h10"/></svg>
            </button>
            <button type="button" title="do prawej" onclick="document.execCommand('justifyRight')" class="rounded-md border border-slate-300 p-2 text-sm">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16"/><path d="M10 10h10"/><path d="M4 14h16"/><path d="M10 18h10"/></svg>
            </button>
            <button type="button" title="wyjustowanie" onclick="document.execCommand('justifyFull')" class="rounded-md border border-slate-300 p-2 text-sm">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16"/><path d="M4 10h16"/><path d="M4 14h16"/><path d="M4 18h16"/></svg>
            </button>
        </div>
        <div id="editor" contenteditable="true" class="min-h-48 w-full rounded-md border border-slate-300 px-3 py-2 outline-none focus:border-[#1754d8]">{!! old('content', $item?->content ?? '') !!}</div>
        <textarea id="content" name="content" class="hidden"></textarea>
    </div>

    <div class="space-y-1">
        <label for="images" class="text-sm font-medium">zdjęcia</label>
        <input id="images" name="images[]" type="file" multiple accept="image/*" class="block w-full text-sm">
    </div>

    <button type="submit" class="rounded-md bg-[#1754d8] px-4 py-2 font-medium text-white">zapisz</button>
</form>

<script>
    const form = document.currentScript.previousElementSibling;
    const editor = document.getElementById('editor');
    const content = document.getElementById('content');
    form.addEventListener('submit', function () {
        content.value = editor.innerHTML.trim();
    });
</script>
