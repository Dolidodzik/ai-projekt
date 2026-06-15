<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class AdminDatabaseSchemaService
{
    private const BLOCKED_TABLES = [
        'migrations',
        'personal_access_tokens',
    ];

    /** @var array<string, list<string>>|null */
    private static ?array $tablesCache = null;

    public function isAllowedTable(string $table): bool
    {
        return preg_match('/^[a-z][a-z0-9_]*$/', $table) === 1
            && in_array($table, $this->listTables(), true);
    }

    /** @return list<string> */
    public function listTables(): array
    {
        if (self::$tablesCache !== null) {
            return self::$tablesCache;
        }

        $rows = DB::select(
            "SELECT table_name
             FROM information_schema.tables
             WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
             ORDER BY table_name"
        );

        self::$tablesCache = array_values(array_filter(
            array_map(fn ($row) => $row->table_name, $rows),
            fn (string $name) => ! in_array($name, self::BLOCKED_TABLES, true)
        ));

        return self::$tablesCache;
    }

    public function getPrimaryKeyColumn(string $table): string
    {
        $rows = DB::select(
            "SELECT a.attname AS column_name
             FROM pg_index i
             JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
             WHERE i.indrelid = ?::regclass AND i.indisprimary
             LIMIT 1",
            [$table]
        );

        if ($rows === []) {
            throw new InvalidArgumentException("Brak klucza głównego dla tabeli {$table}.");
        }

        return $rows[0]->column_name;
    }

    /** @return list<object> */
    public function getColumns(string $table): array
    {
        return DB::select(
            "SELECT column_name, data_type, udt_name, is_nullable, column_default,
                    character_maximum_length, numeric_precision, numeric_scale
             FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = ?
             ORDER BY ordinal_position",
            [$table]
        );
    }

    /** @return array<string, array{table: string, column: string}> */
    public function getForeignKeys(string $table): array
    {
        $rows = DB::select(
            "SELECT kcu.column_name, ccu.table_name AS foreign_table, ccu.column_name AS foreign_column
             FROM information_schema.table_constraints tc
             JOIN information_schema.key_column_usage kcu
               ON tc.constraint_name = kcu.constraint_name AND tc.table_schema = kcu.table_schema
             JOIN information_schema.constraint_column_usage ccu
               ON ccu.constraint_name = tc.constraint_name AND ccu.table_schema = tc.table_schema
             WHERE tc.table_schema = 'public'
               AND tc.table_name = ?
               AND tc.constraint_type = 'FOREIGN KEY'",
            [$table]
        );

        $map = [];
        foreach ($rows as $row) {
            $map[$row->column_name] = [
                'table' => $row->foreign_table,
                'column' => $row->foreign_column,
            ];
        }

        return $map;
    }

    /** @return list<string> */
    public function getCheckConstraintValues(string $table, string $column): array
    {
        $rows = DB::select(
            "SELECT pg_get_constraintdef(oid) AS definition
             FROM pg_constraint
             WHERE conrelid = ?::regclass AND contype = 'c'",
            [$table]
        );

        foreach ($rows as $row) {
            if (! str_contains($row->definition, $column)) {
                continue;
            }

            if (preg_match_all("/'([^']+)'::/", $row->definition, $matches)) {
                return $matches[1];
            }
        }

        return [];
    }

    public function isAutoIncrementColumn(object $column): bool
    {
        return $column->column_default !== null
            && str_contains((string) $column->column_default, 'nextval(');
    }

    /** @return array<string, list<mixed>> */
    public function buildValidationRules(string $table, bool $isUpdate): array
    {
        $columns = $this->getColumns($table);
        $foreignKeys = $this->getForeignKeys($table);
        $primaryKey = $this->getPrimaryKeyColumn($table);
        $rules = [];

        foreach ($columns as $column) {
            $name = $column->column_name;

            if ($isUpdate && $name === $primaryKey) {
                continue;
            }

            $nullable = $column->is_nullable === 'YES';
            $auto = $this->isAutoIncrementColumn($column);
            $hasDefault = $column->column_default !== null && ! $auto;
            $fieldRules = [];

            if (! $isUpdate && $name === $primaryKey && ! $auto) {
                $fieldRules[] = 'required';
            } elseif (! $isUpdate && $auto) {
                $fieldRules[] = 'nullable';
            } elseif ($nullable || $hasDefault || ($isUpdate && $name === 'password_hash')) {
                $fieldRules[] = 'nullable';
            } else {
                $fieldRules[] = 'required';
            }

            if ($name === 'password_hash') {
                $rules[$name] = array_merge($fieldRules, ['string', 'min:8', 'max:255']);
                continue;
            }

            $checkValues = $this->getCheckConstraintValues($table, $name);
            if ($checkValues !== []) {
                $rules[$name] = array_merge($fieldRules, ['string', Rule::in($checkValues)]);
                continue;
            }

            if (isset($foreignKeys[$name])) {
                $foreignKey = $foreignKeys[$name];
                $referenced = $this->findColumnMeta($foreignKey['table'], $foreignKey['column']);
                if ($referenced && $this->isIntegerType($referenced->data_type)) {
                    $fieldRules[] = 'integer';
                } else {
                    $fieldRules[] = 'string';
                    if ($referenced?->character_maximum_length) {
                        $fieldRules[] = 'max:'.(int) $referenced->character_maximum_length;
                    }
                }
                $fieldRules[] = 'exists:'.$foreignKey['table'].','.$foreignKey['column'];
                $rules[$name] = $fieldRules;
                continue;
            }

            $rules[$name] = array_merge($fieldRules, $this->rulesForDataType($column, $name));
        }

        return $rules;
    }

    /** @return list<string> */
    private function rulesForDataType(object $column, string $name): array
    {
        return match (true) {
            $this->isIntegerType($column->data_type) => ['integer'],
            $column->data_type === 'boolean' => ['boolean'],
            in_array($column->data_type, ['numeric', 'decimal', 'real', 'double precision'], true) => ['numeric'],
            $column->data_type === 'date' => ['date'],
            in_array($column->data_type, ['timestamp without time zone', 'timestamp with time zone'], true) => ['date'],
            $column->data_type === 'time without time zone' => ['regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            $column->data_type === 'text' => ['string', 'max:50000'],
            in_array($column->data_type, ['character varying', 'character'], true) => $this->stringRules($column, $name),
            $column->data_type === 'uuid' => ['uuid'],
            in_array($column->data_type, ['json', 'jsonb'], true) => ['json'],
            default => ['string', 'max:65535'],
        };
    }

    /** @return list<string> */
    private function stringRules(object $column, string $name): array
    {
        $rules = ['string'];

        if ($column->character_maximum_length) {
            $rules[] = 'max:'.(int) $column->character_maximum_length;
        } elseif (str_contains($name, 'time') && ! str_contains($name, 'timestamp') && ! str_contains($name, 'datetime')) {
            $rules[] = 'regex:/^(\d{1,2}:\d{2}(:\d{2})?|\d{2}:\d{2}:\d{2})$/';
        } else {
            $rules[] = 'max:65535';
        }

        return $rules;
    }

    /** @param array<string, mixed> $data */
    public function prepareAttributes(string $table, array $data, bool $isUpdate): array
    {
        $prepared = [];

        foreach ($this->getColumns($table) as $column) {
            $name = $column->column_name;

            if (! array_key_exists($name, $data)) {
                continue;
            }

            $value = $data[$name];

            if ($value === '' || $value === null) {
                if ($column->is_nullable === 'YES') {
                    $prepared[$name] = null;
                }

                continue;
            }

            if ($name === 'password_hash') {
                $prepared[$name] = Hash::make((string) $value);
                continue;
            }

            if ($column->data_type === 'boolean') {
                $prepared[$name] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                continue;
            }

            if ($this->isIntegerType($column->data_type)) {
                $prepared[$name] = (int) $value;
                continue;
            }

            if (in_array($column->data_type, ['numeric', 'decimal', 'real', 'double precision'], true)) {
                $prepared[$name] = (string) $value;
                continue;
            }

            if (in_array($column->data_type, ['timestamp without time zone', 'timestamp with time zone'], true)) {
                $prepared[$name] = $this->normalizeTimestamp((string) $value);
                continue;
            }

            $prepared[$name] = $value;
        }

        return $prepared;
    }

    public function findRecord(string $table, string $recordValue): ?object
    {
        $primaryKey = $this->getPrimaryKeyColumn($table);

        return DB::table($table)
            ->where($primaryKey, $this->castPrimaryKeyValue($table, $primaryKey, $recordValue))
            ->first();
    }

    public function castPrimaryKeyValue(string $table, string $primaryKey, string $value): mixed
    {
        $column = $this->findColumnMeta($table, $primaryKey);

        if ($column && $this->isIntegerType($column->data_type)) {
            return (int) $value;
        }

        return $value;
    }

    public function rowCount(string $table): int
    {
        return (int) DB::table($table)->count();
    }

    public function inputTypeForColumn(object $column, string $table, string $columnName): string
    {
        if ($columnName === 'password_hash') {
            return 'password';
        }

        if ($this->getCheckConstraintValues($table, $columnName) !== []) {
            return 'select';
        }

        if (isset($this->getForeignKeys($table)[$columnName])
            && $this->foreignKeyOptionsCount($table, $columnName) <= 200) {
            return 'foreign-select';
        }

        return match ($column->data_type) {
            'boolean' => 'checkbox',
            'smallint', 'integer', 'bigint' => 'number',
            'numeric', 'decimal', 'real', 'double precision' => 'number',
            'date' => 'date',
            'timestamp without time zone', 'timestamp with time zone' => 'datetime-local',
            'time without time zone' => 'time',
            'text' => 'textarea',
            default => 'text',
        };
    }

    /** @return list<object{value: mixed, label: string}> */
    public function foreignKeyOptions(string $table, string $columnName): array
    {
        $foreignKeys = $this->getForeignKeys($table);
        if (! isset($foreignKeys[$columnName])) {
            return [];
        }

        $foreignKey = $foreignKeys[$columnName];
        $rows = DB::table($foreignKey['table'])
            ->select([$foreignKey['column']])
            ->orderBy($foreignKey['column'])
            ->limit(200)
            ->get();

        return $rows->map(function ($row) use ($foreignKey) {
            $value = $row->{$foreignKey['column']};

            return (object) [
                'value' => $value,
                'label' => (string) $value,
            ];
        })->all();
    }

    public function formatValueForInput(object $column, mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (in_array($column->data_type, ['timestamp without time zone', 'timestamp with time zone'], true)) {
            return date('Y-m-d\TH:i', strtotime((string) $value));
        }

        if ($column->data_type === 'boolean') {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    public function normalizeTimestamp(string $value): string
    {
        $normalized = str_replace('T', ' ', $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized)) {
            return $normalized.':00';
        }

        return $normalized;
    }

    private function foreignKeyOptionsCount(string $table, string $columnName): int
    {
        $foreignKeys = $this->getForeignKeys($table);
        if (! isset($foreignKeys[$columnName])) {
            return PHP_INT_MAX;
        }

        $foreignKey = $foreignKeys[$columnName];

        return (int) DB::table($foreignKey['table'])->count();
    }

    private function findColumnMeta(string $table, string $columnName): ?object
    {
        return collect($this->getColumns($table))->firstWhere('column_name', $columnName);
    }

    private function isIntegerType(string $dataType): bool
    {
        return in_array($dataType, ['smallint', 'integer', 'bigint'], true);
    }
}
