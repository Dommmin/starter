#!/usr/bin/env node

import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { resolve, relative, join } from 'node:path';

const ROOT = resolve(import.meta.dirname, '..');
const EXCEPTIONS_FILE = process.env.UI_CONTRACT_EXCEPTIONS_FILE
    ? resolve(process.env.UI_CONTRACT_EXCEPTIONS_FILE)
    : resolve(ROOT, 'design-system.exceptions.json');
const PAGES_DIR = resolve(ROOT, 'resources/js/pages');

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

// 1. Load and validate exceptions registry
const exceptions = new Map();
if (existsSync(EXCEPTIONS_FILE)) {
    try {
        const raw = JSON.parse(readFileSync(EXCEPTIONS_FILE, 'utf8'));
        const now = new Date();
        const seenFiles = new Set();

        for (const entry of raw) {
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

// 2. Collect all files in pages directory
function getFiles(dir) {
    if (!existsSync(dir)) return [];
    const files = [];
    for (const item of readdirSync(dir)) {
        const fullPath = join(dir, item);
        const stat = statSync(fullPath);
        if (stat.isDirectory()) {
            files.push(...getFiles(fullPath));
        } else if (/\.(tsx|jsx)$/.test(item)) {
            files.push(fullPath);
        }
    }
    return files;
}

const pageFiles = getFiles(PAGES_DIR);

// 3. Detect orphaned exceptions (file exists but is not in pages dir scan)
const pageRelPaths = new Set(pageFiles.map((f) => relative(ROOT, f)));
for (const [file, entry] of exceptions) {
    if (!pageRelPaths.has(file)) {
        fail(
            `Osierocony wyjątek ${entry.id}: plik ${file} nie jest w katalogu stron (${relative(ROOT, PAGES_DIR)}/)`,
        );
    }
}

// 4. Check each file for ADR-019 violations
const matchedExceptions = new Set();

for (const file of pageFiles) {
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

        // Check for className or style usage outside approved exceptions
        const hasClassName = /\bclassName\s*=\s*["'{]/.test(line);
        const hasStyle = /\bstyle\s*=\s*\{\{/.test(line);

        if (hasClassName || hasStyle) {
            if (exception) {
                matchedExceptions.add(relPath);
            } else {
                console.error(
                    `FAIL ui-contract: [ADR-019] Niedozwolone stylowanie w ${relPath}:${lineNum}`,
                );
                console.error(`    > ${trimmed}`);
                console.error(
                    `    Reguła: Kod aplikacji w resources/js/pages/** nie może używać bezpośredniego className ani style.`,
                );
                console.error(
                    `    Użyj typowanych prymitywów design systemu lub zarejestruj wyjątek w design-system.exceptions.json.\n`,
                );
                hasViolations = true;
            }
        }
    });
}

// 5. Detect unused/orphaned exceptions for clean files
for (const [file, entry] of exceptions) {
    if (pageRelPaths.has(file) && !matchedExceptions.has(file)) {
        fail(
            `Osierocony wyjątek ${entry.id}: plik ${file} nie zawiera niedozwolonych stylów (brak className/style) i nie wymaga wyjątku`,
        );
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
        `PASS ui-contract (ADR-019): Zgodność z zamkniętym API UI potwierdzona (${pageFiles.length} stron sprawdzonych, ${excCount} zarejestrowanych wyjątków upstream).`,
    );
    process.exit(0);
}
