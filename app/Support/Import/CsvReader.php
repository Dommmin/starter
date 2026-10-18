<?php

namespace App\Support\Import;

/**
 * Reads an uploaded UTF-8 CSV (comma or semicolon, optional BOM) into rows
 * keyed by lower-case header names, with file-level problems as messages.
 */
final class CsvReader
{
    /**
     * @param  list<string>  $columns  Allowed header names.
     * @param  list<string>  $required  Header names that must be present.
     * @return array{rows: list<array{line: int, cells: array<string, string>}>, errors: list<string>}
     */
    public static function read(string $path, array $columns, array $required, int $maxRows): array
    {
        $content = (string) file_get_contents($path);

        if (! mb_check_encoding($content, 'UTF-8')) {
            return ['rows' => [], 'errors' => [__('admin.import.errors.encoding')]];
        }

        $content = (string) preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return ['rows' => [], 'errors' => [__('admin.import.errors.unreadable')]];
        }
        fwrite($stream, $content);
        rewind($stream);

        $header = fgetcsv($stream, null, $delimiter, '"', '');
        if ($header === false || $header === [null]) {
            fclose($stream);

            return ['rows' => [], 'errors' => [__('admin.import.errors.empty')]];
        }

        $header = array_map(fn (?string $name): string => mb_strtolower(trim((string) $name)), $header);
        $errors = [];

        $unknown = array_values(array_diff($header, $columns));
        if ($unknown !== []) {
            $errors[] = __('admin.import.errors.unknownColumns', ['columns' => implode(', ', $unknown)]);
        }

        $missing = array_values(array_diff($required, $header));
        if ($missing !== []) {
            $errors[] = __('admin.import.errors.missingColumns', ['columns' => implode(', ', $missing)]);
        }

        if (count($header) !== count(array_unique($header))) {
            $errors[] = __('admin.import.errors.duplicateColumns');
        }

        $rows = [];
        $line = 1;
        while (($cells = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $line++;

            if (array_filter($cells, fn (?string $cell): bool => trim((string) $cell) !== '') === []) {
                continue;
            }

            if (count($cells) > count($header)) {
                $errors[] = __('admin.import.errors.tooManyCells', ['line' => $line]);

                continue;
            }

            if (count($rows) === $maxRows) {
                $errors[] = __('admin.import.errors.tooManyRows', ['max' => $maxRows]);

                break;
            }

            $rows[] = [
                'line' => $line,
                'cells' => array_combine($header, array_map(fn (?string $cell): string => (string) $cell, array_pad($cells, count($header), ''))),
            ];
        }
        fclose($stream);

        if ($errors === [] && $rows === []) {
            $errors[] = __('admin.import.errors.noRows');
        }

        return ['rows' => $rows, 'errors' => $errors];
    }
}
