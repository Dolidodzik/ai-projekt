@php($item = $announcement ?? null)

<link href="https://cdn.jsdelivr.net/npm/suneditor@2.47.0/dist/css/suneditor.min.css" rel="stylesheet">
<style>
    #content-editor-wrapper .sun-editor {
        width: 100%;
    }
</style>

@if ($errors->any())
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        {{ $errors->first() }}
    </div>
@endif

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-4" id="announcement-form">
    @csrf
    <div class="space-y-1">
        <label for="title" class="text-sm font-medium">tytuł</label>
        <input id="title" name="title" type="text" required value="{{ old('title', $item?->title ?? '') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 outline-none focus:border-[#1754d8]">
    </div>

    <div id="content-editor-wrapper" class="w-full space-y-1">
        <label for="content-editor" class="text-sm font-medium">treść</label>
        <textarea id="content-editor" class="block w-full">{!! old('content', $item?->content ?? '') !!}</textarea>
        <textarea id="content" name="content" class="hidden"></textarea>
    </div>

    <div class="space-y-1">
        <label for="images" class="text-sm font-medium">zdjęcie</label>
        <input id="images" name="images[]" type="file" accept="image/*" class="block w-full text-sm">
    </div>

    <button type="submit" class="rounded-md bg-[#1754d8] px-4 py-2 font-medium text-white">zapisz</button>
</form>

<script src="https://cdn.jsdelivr.net/npm/suneditor@2.47.0/dist/suneditor.min.js"></script>
<script>
    const editor = SUNEDITOR.create('content-editor', {
        width: '100%',
        height: '320',
        buttonList: [
            ['undo', 'redo'],
            ['font', 'fontSize', 'formatBlock'],
            ['paragraphStyle', 'blockquote'],
            ['bold', 'underline', 'italic', 'strike', 'subscript', 'superscript'],
            ['fontColor', 'hiliteColor', 'textStyle'],
            ['removeFormat'],
            ['outdent', 'indent'],
            ['align', 'horizontalRule', 'list', 'lineHeight'],
            ['table', 'link'],
            ['fullScreen', 'showBlocks', 'codeView'],
            ['preview', 'print'],
        ],
    });

    document.getElementById('announcement-form').addEventListener('submit', function () {
        document.getElementById('content').value = editor.getContents();
    });

    document.getElementById('images').addEventListener('change', function () {
        if (this.files.length > 1) {
            this.value = '';
            alert('Można dodać tylko jedno zdjęcie.');
        }
    });
</script>
