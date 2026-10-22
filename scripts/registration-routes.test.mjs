import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { test } from 'node:test';

// Wayfinder generates the registration routes only while
// APP_REGISTRATION_ENABLED=true, so a static import of them breaks the build
// of every project with sign-up switched off. The backend shares the URLs
// (`auth.registerUrl`, `registerAction`) instead.
const SOURCE_ROOT = 'resources/js';
const GENERATED = new Set(['actions', 'routes', 'wayfinder']);

function sourceFiles(directory) {
    return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const path = join(directory, entry.name);

        if (entry.isDirectory()) {
            return directory === SOURCE_ROOT && GENERATED.has(entry.name)
                ? []
                : sourceFiles(path);
        }

        return /\.(ts|tsx)$/.test(entry.name) ? [path] : [];
    });
}

function registrationImports(source) {
    const found = [];
    const importPattern =
        /import\s+(?:type\s+)?([\s\S]*?)\s+from\s+['"](@\/(?:routes|actions)[^'"]*)['"]/g;

    for (const [, clause, specifier] of source.matchAll(importPattern)) {
        if (/\/register(\/|$)|RegisteredUserController/.test(specifier)) {
            found.push(specifier);
        } else if (
            /^@\/routes(\/localized)?$/.test(specifier) &&
            /\{[^}]*\bregister\b[^}]*\}/.test(clause)
        ) {
            found.push(`${specifier} { register }`);
        }
    }

    return found;
}

void test('the guard recognises every form of a registration route import', () => {
    assert.deepEqual(
        registrationImports(`
            import { home, register } from '@/routes';
            import {
                register as localizedRegister,
            } from '@/routes/localized';
            import { store } from '@/routes/register';
            import { store as s } from '@/routes/localized/register';
            import Controller from '@/actions/Laravel/Fortify/Http/Controllers/RegisteredUserController';
            import { login } from '@/routes';
            import { registerUrl } from '@/lib/urls';
        `),
        [
            '@/routes { register }',
            '@/routes/localized { register }',
            '@/routes/register',
            '@/routes/localized/register',
            '@/actions/Laravel/Fortify/Http/Controllers/RegisteredUserController',
        ],
    );
});

void test('frontend code does not import registration routes that may not be generated', () => {
    const offenders = sourceFiles(SOURCE_ROOT).flatMap((path) =>
        registrationImports(readFileSync(path, 'utf8')).map(
            (specifier) => `${path}: ${specifier}`,
        ),
    );

    assert.deepEqual(offenders, []);
});
