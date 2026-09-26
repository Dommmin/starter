#!/usr/bin/env node

import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { resolve, relative, join } from 'node:path';

const ROOT = process.env.UI_CONTRACT_ROOT
    ? resolve(process.env.UI_CONTRACT_ROOT)
    : resolve(import.meta.dirname, '..');
const EXCEPTIONS_FILE = process.env.UI_CONTRACT_EXCEPTIONS_FILE
    ? resolve(process.env.UI_CONTRACT_EXCEPTIONS_FILE)
    : resolve(ROOT, 'design-system.exceptions.json');
const SOURCE_DIRECTORIES = [
    resolve(ROOT, 'resources/js/pages'),
    resolve(ROOT, 'resources/js/components'),
    resolve(ROOT, 'resources/js/layouts'),
    resolve(ROOT, 'resources/js/design-system'),
];
const CSS_DIRECTORY = resolve(ROOT, 'resources/css');
const STAGED_ONLY = process.argv.includes('--staged');
const BASE_REF_INDEX = process.argv.indexOf('--base');
const BASE_REF =
    BASE_REF_INDEX === -1 ? null : process.argv[BASE_REF_INDEX + 1];
const FILES_INDEX = process.argv.indexOf('--files');
const REQUESTED_FILES =
    FILES_INDEX === -1
        ? null
        : new Set(
              process.argv
                  .slice(FILES_INDEX + 1)
                  .flatMap((value) => value.split(','))
                  .filter(Boolean)
                  .map((file) => relative(ROOT, resolve(ROOT, file))),
          );

const REQUIRED_FIELDS = [
    'id',
    'rule',
    'file',
    'contentHash',
    'reason',
    'alternatives',
    'scope',
    'owner',
    'reviewRef',
    'expires',
    'task',
];

let hasViolations = false;

function fail(message) {
    console.error(`FAIL ui-contract: ${message}`);
    hasViolations = true;
}

if (
    (STAGED_ONLY && BASE_REF) ||
    (REQUESTED_FILES && (STAGED_ONLY || BASE_REF))
) {
    fail(
        'Bramka UI przyjmuje dokładnie jeden tryb: --staged, --base <ref> albo --files <plik...>.',
    );
}

if (REQUESTED_FILES?.size === 0) {
    fail('Tryb --files wymaga co najmniej jednego pliku UI.');
}

for (const file of REQUESTED_FILES ?? []) {
    if (file === '..' || file.startsWith('../')) {
        fail(`Plik poza repozytorium nie może być sprawdzony: ${file}`);
    }
}

// 1. Load and validate exceptions registry
const exceptions = new Map();
if (existsSync(EXCEPTIONS_FILE)) {
    try {
        const raw = JSON.parse(readFileSync(EXCEPTIONS_FILE, 'utf8'));
        const now = new Date();
        const seenFiles = new Set();

        for (const entry of raw) {
            if (REQUESTED_FILES && !REQUESTED_FILES.has(entry.file)) {
                continue;
            }
            // Check all required fields
            const missingFields = REQUIRED_FIELDS.filter(
                (f) =>
                    entry[f] === undefined ||
                    entry[f] === null ||
                    entry[f] === '',
            );
            if (missingFields.length > 0) {
                fail(
                    `Wpis ${entry.id ?? '(brak id)'} nie zawiera wymaganych pól: ${missingFields.join(', ')}`,
                );
                continue;
            }

            // Check for duplicate file entries
            if (seenFiles.has(entry.file)) {
                fail(
                    `Zduplikowany wpis dla pliku ${entry.file} (więcej niż jeden wyjątek dla tego samego pliku)`,
                );
                continue;
            }
            seenFiles.add(entry.file);

            // Check expiry
            const expiry = new Date(entry.expires);
            if (now > expiry) {
                fail(
                    `Wyjątek ${entry.id} dla ${entry.file} wygasł dnia ${entry.expires} (zadanie: ${entry.task ?? 'brak'})`,
                );
            }

            // Check that file exists
            const absPath = resolve(ROOT, entry.file);
            if (!existsSync(absPath)) {
                fail(
                    `Wyjątek ${entry.id} wskazuje na nieistniejący plik: ${entry.file}`,
                );
                continue;
            }

            // Verify content hash (SHA-256)
            const fileContent = readFileSync(absPath);
            const actualHash =
                'sha256:' +
                createHash('sha256').update(fileContent).digest('hex');
            if (entry.contentHash !== actualHash) {
                fail(
                    `Wyjątek ${entry.id}: treść pliku ${entry.file} zmieniła się od momentu rejestracji wyjątku.\n` +
                        `    Oczekiwany hash: ${entry.contentHash}\n` +
                        `    Aktualny hash:   ${actualHash}\n` +
                        `    Wyjątek wymaga ponownego review i aktualizacji hasha.`,
                );
            }

            // Verify granular line scope (ADR-019: whole-file exemptions prohibited)
            const hasRanges =
                Array.isArray(entry.lineRanges) && entry.lineRanges.length > 0;
            const hasLines =
                Array.isArray(entry.lines) && entry.lines.length > 0;
            if (!hasRanges && !hasLines) {
                fail(
                    `Wyjątek ${entry.id} dla ${entry.file} nie precyzuje zakresu linii ('lineRanges' lub 'lines'). Całoplikowe wyjątki są niedozwolone w ADR-019.`,
                );
            }

            exceptions.set(entry.file, entry);
        }
    } catch (err) {
        console.error(
            `FAIL ui-contract: Błąd odczytu ${EXCEPTIONS_FILE}:`,
            err.message,
        );
        process.exit(1);
    }
}

// 2. Collect every application UI source file covered by ADR-019.
function getFiles(dir, extensions) {
    if (!existsSync(dir)) return [];
    const files = [];
    for (const item of readdirSync(dir)) {
        const fullPath = join(dir, item);
        const stat = statSync(fullPath);
        if (stat.isDirectory()) {
            files.push(...getFiles(fullPath, extensions));
        } else if (extensions.test(item)) {
            files.push(fullPath);
        }
    }
    return files;
}

function getChangedFiles() {
    try {
        const range = BASE_REF ? [BASE_REF + '...HEAD'] : ['--cached'];
        return new Set(
            execFileSync(
                'git',
                [
                    'diff',
                    ...range,
                    '--name-only',
                    '--diff-filter=ACMR',
                    '--',
                    'resources/js',
                    'resources/css',
                ],
                { cwd: ROOT, encoding: 'utf8' },
            )
                .split('\n')
                .filter(Boolean),
        );
    } catch (error) {
        fail('Nie można odczytać staged diff dla bramki UI: ' + error.message);
        return new Set();
    }
}

const uiFiles = SOURCE_DIRECTORIES.flatMap((dir) =>
    getFiles(dir, /\.(ts|tsx|js|jsx)$/),
);
const cssFiles = getFiles(CSS_DIRECTORY, /\.css$/);
const allSourceFiles = [...uiFiles, ...cssFiles];
const selectedFiles =
    REQUESTED_FILES ?? (STAGED_ONLY || BASE_REF ? getChangedFiles() : null);
const sourceFiles = selectedFiles
    ? allSourceFiles.filter((file) => selectedFiles.has(relative(ROOT, file)))
    : allSourceFiles;

for (const file of REQUESTED_FILES ?? []) {
    if (
        !allSourceFiles.some(
            (sourceFile) => relative(ROOT, sourceFile) === file,
        )
    ) {
        fail(
            `Tryb --files wskazuje plik poza zakresem UI lub nieistniejący: ${file}`,
        );
    }
}

// 3. Detect orphaned exceptions (file exists but is not in a covered source directory)
const sourceRelPaths = new Set(allSourceFiles.map((f) => relative(ROOT, f)));
for (const [file, entry] of exceptions) {
    if (!sourceRelPaths.has(file)) {
        fail(
            `Osierocony wyjątek ${entry.id}: plik ${file} nie jest w monitorowanym źródle UI`,
        );
    }
}

// 4. Check each file for ADR-019 violations
const matchedExceptions = new Set();
const matchedRanges = new Set();

for (const file of sourceFiles) {
    const relPath = relative(ROOT, file);
    const content = readFileSync(file, 'utf8');
    const lines = content.split('\n');

    const exception = exceptions.get(relPath);

    lines.forEach((line, index) => {
        const trimmed = line.trim();
        // Skip pure comments
        if (
            trimmed.startsWith('//') ||
            trimmed.startsWith('/*') ||
            trimmed.startsWith('*')
        ) {
            return;
        }

        const lineNum = index + 1;
        const normalizedRel = relPath.replace(/\\/g, '/');
        const isPrimitive = normalizedRel.startsWith(
            'resources/js/design-system/primitives/',
        );

        // Check direct styling in TSX and raw colours outside CSS token declarations.
        const hasClassName =
            !isPrimitive && /\bclassName\s*=\s*["'{]/.test(line);
        const hasStyle = !isPrimitive && /\bstyle\s*=\s*\{\{/.test(line);
        const hasRawTsxColor =
            /\.(tsx|ts|jsx|js)$/.test(file) && /#[0-9a-fA-F]{3,8}\b/.test(line);
        const hasDarkPrefix =
            /\.(tsx|ts|jsx|js)$/.test(file) && /\bdark:/.test(line);
        const hasRawCssColor =
            file.endsWith('.css') &&
            /#[0-9a-fA-F]{3,8}\b/.test(line) &&
            !/--[\w-]+\s*:/.test(line);

        if (
            hasClassName ||
            hasStyle ||
            hasRawTsxColor ||
            hasDarkPrefix ||
            hasRawCssColor
        ) {
            if (exception) {
                // Verify line number is strictly covered by exception's lineRanges or lines
                let isPermitted = false;
                if (Array.isArray(exception.lineRanges)) {
                    exception.lineRanges.forEach((range, rIdx) => {
                        const [start, end] = range;
                        if (lineNum >= start && lineNum <= end) {
                            isPermitted = true;
                            matchedRanges.add(`${relPath}:${rIdx}`);
                        }
                    });
                }
                if (
                    Array.isArray(exception.lines) &&
                    exception.lines.includes(lineNum)
                ) {
                    isPermitted = true;
                }

                if (isPermitted) {
                    matchedExceptions.add(relPath);
                } else {
                    console.error(
                        `FAIL ui-contract: [ADR-019] Niedozwolone stylowanie w ${relPath}:${lineNum} poza zatwierdzonym zakresem wyjątku ${exception.id}`,
                    );
                    console.error(`    > ${trimmed}`);
                    console.error(
                        `    Zatwierdzony zakres wyjątku: ${JSON.stringify(exception.lineRanges || exception.lines)}`,
                    );
                    console.error(
                        `    Reguła: Wyjątki w ADR-019 są ściśle ograniczone do wskazanych linii/zakresów. Nowe lub przesunięte style w tym pliku są blokowane.\n`,
                    );
                    hasViolations = true;
                }
            } else {
                console.error(
                    `FAIL ui-contract: [ADR-019] Niedozwolone stylowanie w ${relPath}:${lineNum}`,
                );
                console.error(`    > ${trimmed}`);
                console.error(
                    `    Reguła: Kod aplikacji w pages, components, layouts i CSS nie może używać bezpośredniego stylowania poza zatwierdzonym wyjątkiem.`,
                );
                console.error(
                    `    Użyj typowanych prymitywów design systemu lub zarejestruj wyjątek w design-system.exceptions.json.\n`,
                );
                hasViolations = true;
            }
        }
    });
}

// 5. Detect unused/orphaned exceptions for clean files or stale ranges
for (const [file, entry] of exceptions) {
    if (
        sourceRelPaths.has(file) &&
        (!selectedFiles || selectedFiles.has(file))
    ) {
        if (!matchedExceptions.has(file)) {
            fail(
                `Osierocony wyjątek ${entry.id}: plik ${file} nie zawiera niedozwolonych stylów (brak className/style) i nie wymaga wyjątku`,
            );
        } else if (Array.isArray(entry.lineRanges) && !selectedFiles) {
            // In full scan, ensure every declared range matched at least one actual styling occurrence
            entry.lineRanges.forEach((range, rIdx) => {
                if (!matchedRanges.has(`${file}:${rIdx}`)) {
                    fail(
                        `Przestarzały zakres w wyjątku ${entry.id} dla ${file}: zakres linii [${range[0]}, ${range[1]}] nie zawierał żadnego niedozwolonego stylu i powinien zostać usunięty lub zawężony.`,
                    );
                }
            });
        }
    }
}

if (hasViolations) {
    console.error(
        'FAIL ui-contract: Wykryto naruszenia LLM-safe UI contract (ADR-019).',
    );
    process.exit(1);
} else {
    const excCount = exceptions.size;
    console.log(
        `PASS ui-contract (ADR-019): Zgodność z zamkniętym API UI potwierdzona (${sourceFiles.length} plików UI sprawdzonych, ${excCount} zarejestrowanych wyjątków).`,
    );
    process.exit(0);
}
