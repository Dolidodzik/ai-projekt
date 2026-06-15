@php
    use App\Services\AdminDatabaseSchemaService;
    $schema = app(AdminDatabaseSchemaService::class);
@endphp

@if ($errors->any())
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        <ul class="list-disc pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $action }}" class="space-y-4">
    @csrf

    @foreach ($fields as $field)
        @php
            $column = $field['column'];
            $name = $field['name'];
            $value = old($name, isset($row) ? $schema->formatValueForInput($column, $row->{$name} ?? null) : '');
            $required = ! $field['is_auto'] && $column->is_nullable === 'NO' && $column->column_default === null && ! ($field['is_primary'] && $field['is_auto']);
            if (isset($row) && $name === 'password_hash') {
                $required = false;
            }
        @endphp

        @if ($field['is_auto'] && ! isset($row))
            @continue
        @endif

        <div class="space-y-1 rounded-md border border-slate-200 p-3">
            <label for="{{ $name }}" class="text-sm font-medium">
                {{ $name }}
                <span class="font-normal text-slate-500">({{ $column->data_type }})</span>
            </label>

            @if ($field['foreign'])
                <p class="text-xs text-slate-500">FK: {{ $field['foreign']['table'] }}.{{ $field['foreign']['column'] }}</p>
            @endif

            @if ($field['input_type'] === 'checkbox')
                <div class="flex items-center gap-2">
                    <input id="{{ $name }}" name="{{ $name }}" type="checkbox" value="1" @checked(old($name, $row->{$name} ?? false)) class="rounded border-slate-300 text-[#1754d8]">
                    <span class="text-sm text-slate-600">true / false</span>
                </div>
            @elseif ($field['input_type'] === 'select')
                <select id="{{ $name }}" name="{{ $name }}" @required($required) class="w-full rounded-md border border-slate-300 px-3 py-2">
                    <option value="">— wybierz —</option>
                    @foreach ($field['check_values'] as $option)
                        <option value="{{ $option }}" @selected((string) $value === (string) $option)>{{ $option }}</option>
                    @endforeach
                </select>
            @elseif ($field['input_type'] === 'foreign-select')
                <select id="{{ $name }}" name="{{ $name }}" @required($required) class="w-full rounded-md border border-slate-300 px-3 py-2">
                    <option value="">— wybierz —</option>
                    @foreach ($field['foreign_options'] as $option)
                        <option value="{{ $option->value }}" @selected((string) $value === (string) $option->value)>{{ $option->label }}</option>
                    @endforeach
                </select>
            @elseif ($field['input_type'] === 'textarea')
                <textarea id="{{ $name }}" name="{{ $name }}" rows="5" @required($required) class="w-full rounded-md border border-slate-300 px-3 py-2">{{ $value }}</textarea>
            @elseif ($field['input_type'] === 'password')
                <input id="{{ $name }}" name="{{ $name }}" type="password" @required($required && ! isset($row)) class="w-full rounded-md border border-slate-300 px-3 py-2">
                @if (isset($row))
                    <p class="text-xs text-slate-500">Pozostaw puste, aby nie zmieniać hasła.</p>
                @endif
            @else
                <input
                    id="{{ $name }}"
                    name="{{ $name }}"
                    type="{{ $field['input_type'] }}"
                    value="{{ $field['input_type'] === 'password' ? '' : $value }}"
                    @required($required)
                    @if ($field['input_type'] === 'number' && in_array($column->data_type, ['numeric', 'decimal', 'real', 'double precision'], true)) step="0.01" @endif
                    class="w-full rounded-md border border-slate-300 px-3 py-2"
                >
            @endif

            @if ($field['is_auto'])
                <p class="text-xs text-slate-500">Kolumna auto-increment — zostanie nadana automatycznie.</p>
            @elseif ($column->is_nullable === 'YES')
                <p class="text-xs text-slate-500">Pole opcjonalne (NULL dozwolony).</p>
            @endif
        </div>
    @endforeach

    <button type="submit" class="rounded-md bg-[#1754d8] px-4 py-2 font-medium text-white">zapisz</button>
</form>
