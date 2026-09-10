#!/usr/bin/env node

import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { resolve, relative, join } from 'node:path';

const ROOT = resolve(import.meta.dirname, '..');
const EXCEPTIONS_FILE = resolve(ROOT, 'design-system.exceptions.json');
const PAGES_DIR = resolve(ROOT, 'resources/js/pages');

let hasViolations = false;

// 1. Load and validate exceptions registry
const exceptions = new Map();
if (existsSync(EXCEPTIONS_FILE)) {
    try {
        const raw = JSON.parse(readFileSync(EXCEPTIONS_FILE, 'utf8'));
        const now = new Date();

        for (const entry of raw) {
            if (!entry.id || !entry.file || !entry.rule || !entry.expires) {
                console.error(
                    `FAIL ui-contract: Niepoprawny wpis w rejestrze wyjątków: ${JSON.stringify(entry)}`,
                );
                hasViolations = true;
                continue;
            }

            const expiry = new Date(entry.expires);
            if (now > expiry) {
                console.error(
                    `FAIL ui-contract: Wyjątek ${entry.id} dla ${entry.file} wygasł dnia ${entry.expires} (zadanie: ${entry.task ?? 'brak'})`,
                );
                hasViolations = true;
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

// 3. Check each file for ADR-019 violations
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

        if ((hasClassName || hasStyle) && !exception) {
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
    });
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
