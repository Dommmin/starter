<?php

namespace App\Actions\Import;

use App\Actions\Audit\RecordAuditEvent;
use App\Contracts\Import\ImportDefinition;
use App\Data\Admin\Import\ImportPreviewData;
use App\Data\Admin\Import\ImportRowData;
use App\Enums\AuditAction;
use App\Models\User;
use App\Support\Import\CsvReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Two-step CSV import: `preview()` validates every row (and duplicates
 * inside the file) without writing anything; a clean file gets a token that
 * `commit()` turns into records in one transaction with one audit entry.
 */
class ImportCsv
{
    public const int MAX_ROWS = 1000;

    public const int MAX_KILOBYTES = 2048;

    /** Valid rows shown in the preview after the invalid ones. */
    private const int VALID_ROWS_SHOWN = 20;

    private const int TOKEN_MINUTES = 15;

    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function preview(ImportDefinition $definition, UploadedFile $file, User $user): ImportPreviewData
    {
        $read = CsvReader::read((string) $file->getRealPath(), $definition->columns(), $definition->requiredColumns(), self::MAX_ROWS);
        $fileName = Str::limit($file->getClientOriginalName(), 120);

        if ($read['errors'] !== []) {
            return new ImportPreviewData($fileName, $read['errors'], [], count($read['rows']), 0, null);
        }

        $values = array_map(fn (array $row): array => $definition->normalize($row['cells']), $read['rows']);
        $messages = $this->validate($definition, $values);

        $invalid = [];
        $valid = [];
        foreach ($read['rows'] as $index => $row) {
            $item = new ImportRowData($row['line'], $definition->label($values[$index]), $messages[$index] ?? []);

            if ($item->errors !== []) {
                $invalid[] = $item;
            } elseif (count($valid) < self::VALID_ROWS_SHOWN) {
                $valid[] = $item;
            }
        }

        $token = null;
        if ($invalid === []) {
            $token = Str::random(40);
            Cache::put($this->cacheKey($user, $token), ['module' => $definition->key(), 'rows' => $values], now()->addMinutes(self::TOKEN_MINUTES));
        }

        return new ImportPreviewData($fileName, [], [...$invalid, ...$valid], count($values), count($invalid), $token);
    }

    /**
     * @throws ValidationException When the token expired or the data changed since the preview (`token`).
     */
    public function commit(ImportDefinition $definition, string $token, User $user): int
    {
        /** @var array{module: string, rows: list<array<string, mixed>>}|null $payload */
        $payload = Cache::pull($this->cacheKey($user, $token));

        if ($payload === null || $payload['module'] !== $definition->key()) {
            throw ValidationException::withMessages(['token' => __('admin.import.errors.expired')]);
        }

        if ($this->validate($definition, $payload['rows']) !== []) {
            throw ValidationException::withMessages(['token' => __('admin.import.errors.changed')]);
        }

        DB::transaction(function () use ($definition, $payload, $user): void {
            foreach ($payload['rows'] as $row) {
                $definition->store($row);
            }

            $model = $definition->model();
            $this->audit->handle(AuditAction::ResourceImported, new $model, $user, [
                'module' => RecordAuditEvent::change(null, $definition->key()),
                'rows' => RecordAuditEvent::change(null, count($payload['rows'])),
            ]);
        });

        return count($payload['rows']);
    }

    /**
     * Messages per row index; rows without problems are absent.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, list<string>>
     */
    private function validate(ImportDefinition $definition, array $rows): array
    {
        $rules = [];
        $attributes = [];
        foreach ($definition->rules() as $field => $fieldRules) {
            $rules["rows.*.{$field}"] = $fieldRules;
            $attributes["rows.*.{$field}"] = $field;
        }

        $validator = Validator::make(['rows' => $rows], $rules, [], $attributes);
        $messages = [];

        foreach ($validator->errors()->messages() as $key => $fieldMessages) {
            $index = (int) explode('.', $key)[1];
            $messages[$index] = array_values([...($messages[$index] ?? []), ...array_map(strval(...), $fieldMessages)]);
        }

        return $messages;
    }

    private function cacheKey(User $user, string $token): string
    {
        return "import:{$user->id}:".hash('sha256', $token);
    }
}
