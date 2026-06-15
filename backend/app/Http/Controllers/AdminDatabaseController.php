<?php

namespace App\Http\Controllers;

use App\Services\AdminDatabaseSchemaService;
use App\Support\ValidationRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminDatabaseController extends Controller
{
    public function __construct(
        private readonly AdminDatabaseSchemaService $schema
    ) {}

    public function index(): View
    {
        $tables = collect($this->schema->listTables())
            ->map(fn (string $table) => [
                'name' => $table,
                'count' => $this->schema->rowCount($table),
            ]);

        return view('admin.database.index', compact('tables'));
    }

    public function table(Request $request, string $table): View
    {
        $this->guardTable($table);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', Rule::in($this->sortableColumns($table))],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ValidationRules::paginationPage(),
            'per_page' => ValidationRules::paginationPerPage(50),
        ]);

        $columns = $this->schema->getColumns($table);
        $primaryKey = $this->schema->getPrimaryKeyColumn($table);
        $sortColumn = $validated['sort'] ?? $primaryKey;
        $direction = $validated['direction'] ?? 'desc';
        $perPage = min((int) ($validated['per_page'] ?? 20), 50);

        $query = DB::table($table);

        if (filled($validated['q'] ?? null)) {
            $term = '%'.$validated['q'].'%';
            $query->where(function ($inner) use ($columns, $term) {
                foreach ($columns as $column) {
                    if (in_array($column->data_type, ['character varying', 'character', 'text'], true)) {
                        $inner->orWhere($column->column_name, 'ilike', $term);
                    }
                }
            });
        }

        $rows = $query
            ->orderBy($sortColumn, $direction)
            ->paginate($perPage)
            ->withQueryString();

        $displayColumns = collect($columns)
            ->take(8)
            ->pluck('column_name')
            ->all();

        return view('admin.database.table', [
            'table' => $table,
            'rows' => $rows,
            'columns' => $columns,
            'displayColumns' => $displayColumns,
            'primaryKey' => $primaryKey,
            'filters' => [
                'q' => $validated['q'] ?? '',
                'sort' => $sortColumn,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(string $table): View
    {
        $this->guardTable($table);

        return view('admin.database.create', $this->formContext($table));
    }

    public function store(Request $request, string $table): RedirectResponse
    {
        $this->guardTable($table);

        $validated = $request->validate($this->schema->buildValidationRules($table, false));
        $attributes = $this->schema->prepareAttributes($table, $validated, false);

        if ($attributes === []) {
            throw ValidationException::withMessages([
                'form' => 'Podaj przynajmniej jedną wartość do zapisania.',
            ]);
        }

        $primaryKey = $this->schema->getPrimaryKeyColumn($table);
        $primaryKeyColumn = collect($this->schema->getColumns($table))
            ->firstWhere('column_name', $primaryKey);

        if ($primaryKeyColumn && $this->schema->isAutoIncrementColumn($primaryKeyColumn)) {
            $recordValue = DB::table($table)->insertGetId($attributes, $primaryKey);
        } else {
            if (! array_key_exists($primaryKey, $attributes)) {
                throw ValidationException::withMessages([
                    $primaryKey => 'Klucz główny jest wymagany dla tej tabeli.',
                ]);
            }

            DB::table($table)->insert($attributes);
            $recordValue = $attributes[$primaryKey];
        }

        return redirect()
            ->route('admin.database.show', [
                'table' => $table,
                'record' => (string) $recordValue,
            ])
            ->with('status', 'Rekord został dodany.');
    }

    public function show(string $table, string $record): View
    {
        $this->guardTable($table);
        $row = $this->findRecordOrFail($table, $record);

        return view('admin.database.show', [
            'table' => $table,
            'row' => $row,
            'columns' => $this->schema->getColumns($table),
            'primaryKey' => $this->schema->getPrimaryKeyColumn($table),
        ]);
    }

    public function edit(string $table, string $record): View
    {
        $this->guardTable($table);
        $row = $this->findRecordOrFail($table, $record);

        return view('admin.database.edit', array_merge(
            $this->formContext($table),
            [
                'row' => $row,
                'record' => $record,
            ]
        ));
    }

    public function update(Request $request, string $table, string $record): RedirectResponse
    {
        $this->guardTable($table);
        $row = $this->findRecordOrFail($table, $record);

        $validated = $request->validate($this->schema->buildValidationRules($table, true));
        $attributes = $this->schema->prepareAttributes($table, $validated, true);

        $primaryKey = $this->schema->getPrimaryKeyColumn($table);
        $keyValue = $row->{$primaryKey};

        DB::table($table)
            ->where($primaryKey, $keyValue)
            ->update($attributes);

        return redirect()
            ->route('admin.database.show', [
                'table' => $table,
                'record' => (string) $record,
            ])
            ->with('status', 'Rekord został zaktualizowany.');
    }

    public function destroy(string $table, string $record): RedirectResponse
    {
        $this->guardTable($table);
        $row = $this->findRecordOrFail($table, $record);

        $primaryKey = $this->schema->getPrimaryKeyColumn($table);

        if ($table === 'users' && (int) $row->{$primaryKey} === (int) auth()->id()) {
            return redirect()
                ->route('admin.database.table', $table)
                ->withErrors(['record' => 'Nie możesz usunąć własnego konta administratora.']);
        }

        DB::table($table)
            ->where($primaryKey, $row->{$primaryKey})
            ->delete();

        return redirect()
            ->route('admin.database.table', $table)
            ->with('status', 'Rekord został usunięty.');
    }

    private function guardTable(string $table): void
    {
        if (! $this->schema->isAllowedTable($table)) {
            abort(404);
        }
    }

    private function findRecordOrFail(string $table, string $record): object
    {
        $row = $this->schema->findRecord($table, $record);

        if (! $row) {
            abort(404);
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function formContext(string $table): array
    {
        $columns = $this->schema->getColumns($table);
        $primaryKey = $this->schema->getPrimaryKeyColumn($table);
        $fields = [];

        foreach ($columns as $column) {
            $name = $column->column_name;
            $fields[] = [
                'column' => $column,
                'name' => $name,
                'input_type' => $this->schema->inputTypeForColumn($column, $table, $name),
                'check_values' => $this->schema->getCheckConstraintValues($table, $name),
                'foreign_options' => $this->schema->inputTypeForColumn($column, $table, $name) === 'foreign-select'
                    ? $this->schema->foreignKeyOptions($table, $name)
                    : [],
                'is_primary' => $name === $primaryKey,
                'is_auto' => $this->schema->isAutoIncrementColumn($column),
                'foreign' => $this->schema->getForeignKeys($table)[$name] ?? null,
            ];
        }

        return [
            'table' => $table,
            'columns' => $columns,
            'fields' => $fields,
            'primaryKey' => $primaryKey,
        ];
    }

    /** @return list<string> */
    private function sortableColumns(string $table): array
    {
        return collect($this->schema->getColumns($table))
            ->pluck('column_name')
            ->all();
    }
}
