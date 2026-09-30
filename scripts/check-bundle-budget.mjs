#!/usr/bin/env node
/**
 * Bundle budget gate for the public critical path.
 *
 * Reads the Vite manifest produced by `npm run build`, follows only static
 * imports (dynamic imports are lazy and do not block first render) and
 * measures gzip sizes (node:zlib, level 9) of:
 *   - the entry JS (resources/js/app.tsx + static imports),
 *   - the CSS on the critical path (CSS entries, CSS of the entry closure and
 *     the self-hosted font stylesheet from fonts-manifest.json),
 *   - each public page: the JS it adds on top of the entry closure,
 *   - the largest single JS chunk loaded by any public page.
 * Only `limits` block (total JS of each public page, critical CSS): they
 * describe what a visitor actually downloads. `warnings` (entry, page chunk,
 * largest chunk) are reported but do not fail, because Vite moves shared
 * code between those chunks without changing the total.
 * It also fails when a test module (*.test.*) leaked into the client build
 * and when a module listed in `lazyModules` is not a separate dynamic chunk
 * or is statically reachable from the entry (it would load on every page).
 *
 * Usage: node scripts/check-bundle-budget.mjs
 *          [--build-dir public/build] [--budget bundle-budget.json] [--json]
 */
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { gzipSync } from 'node:zlib';

function parseArguments(argv) {
    const options = {
        buildDir: 'public/build',
        budget: 'bundle-budget.json',
        json: false,
    };

    for (let index = 0; index < argv.length; index++) {
        const argument = argv[index];

        if (argument === '--build-dir') {
            options.buildDir = argv[++index];
        } else if (argument === '--budget') {
            options.budget = argv[++index];
        } else if (argument === '--json') {
            options.json = true;
        } else {
            throw new Error(`Nieznany argument: ${argument}`);
        }
    }

    return options;
}

function readJson(path, hint) {
    if (!existsSync(path)) {
        throw new Error(`Brak pliku ${path}. ${hint}`);
    }

    return JSON.parse(readFileSync(path, 'utf8'));
}

const kilobytes = (bytes) => Math.round((bytes / 1024) * 100) / 100;

function main() {
    const options = parseArguments(process.argv.slice(2));
    const buildDir = resolve(options.buildDir);
    const budget = readJson(
        resolve(options.budget),
        'Brak konfiguracji budżetu.',
    );
    const manifest = readJson(
        resolve(buildDir, 'manifest.json'),
        'Uruchom najpierw `npm run build`.',
    );
    const fontsManifestPath = resolve(buildDir, 'fonts-manifest.json');
    const fontsManifest = existsSync(fontsManifestPath)
        ? JSON.parse(readFileSync(fontsManifestPath, 'utf8'))
        : null;

    const gzipCache = new Map();
    const gzipSize = (file) => {
        if (!gzipCache.has(file)) {
            const path = resolve(buildDir, file);

            if (!existsSync(path)) {
                throw new Error(`Manifest wskazuje brakujący plik ${file}.`);
            }

            gzipCache.set(
                file,
                gzipSync(readFileSync(path), { level: 9 }).length,
            );
        }

        return gzipCache.get(file);
    };

    const chunk = (key) => {
        const entry = manifest[key];

        if (!entry) {
            throw new Error(`Brak wpisu ${key} w manifeście Vite.`);
        }

        return entry;
    };

    /** Static-import closure of a manifest key (dynamic imports excluded). */
    const staticClosure = (key, seen = new Set()) => {
        if (seen.has(key)) {
            return seen;
        }

        seen.add(key);

        for (const imported of chunk(key).imports ?? []) {
            staticClosure(imported, seen);
        }

        return seen;
    };

    const jsFiles = (keys) =>
        new Set(
            [...keys]
                .map((key) => chunk(key).file)
                .filter((file) => file.endsWith('.js')),
        );
    const cssFiles = (keys) =>
        new Set([...keys].flatMap((key) => chunk(key).css ?? []));
    const sum = (files) =>
        [...files].reduce((total, file) => total + gzipSize(file), 0);

    const entryKeys = staticClosure(budget.entry);
    const entryJs = jsFiles(entryKeys);
    const criticalCss = new Set([
        ...cssFiles(entryKeys),
        ...(budget.css ?? []).map((key) => chunk(key).file),
        ...(fontsManifest?.style?.file ? [fontsManifest.style.file] : []),
    ]);

    const measurements = {
        entryJsGzipKb: kilobytes(sum(entryJs)),
        cssGzipKb: kilobytes(sum(criticalCss)),
        pages: {},
        largestPublicChunk: null,
        chunkCount: new Set(
            Object.values(manifest)
                .map((entry) => entry.file)
                .filter((file) => file.endsWith('.js')),
        ).size,
    };

    let largest = { file: null, bytes: 0 };

    for (const page of budget.publicPages) {
        const pageKeys = staticClosure(page);
        const pageJs = jsFiles(pageKeys);
        const additionalJs = [...pageJs].filter((file) => !entryJs.has(file));
        const additionalCss = [...cssFiles(pageKeys)].filter(
            (file) => !criticalCss.has(file),
        );

        for (const file of new Set([...entryJs, ...pageJs])) {
            if (gzipSize(file) > largest.bytes) {
                largest = { file, bytes: gzipSize(file) };
            }
        }

        measurements.pages[page] = {
            pageJsGzipKb: kilobytes(sum(additionalJs) + sum(additionalCss)),
            totalJsGzipKb: kilobytes(sum(entryJs) + sum(additionalJs)),
        };
    }

    measurements.largestPublicChunk = {
        file: largest.file,
        gzipKb: kilobytes(largest.bytes),
    };

    const forbidden = new RegExp(
        budget.forbiddenSourcePattern ?? '\\.test\\.[jt]sx?$',
    );
    const leakedSources = Object.entries(manifest)
        .filter(
            ([key, entry]) =>
                forbidden.test(key) || forbidden.test(entry.src ?? ''),
        )
        .map(([key]) => key);

    const lazyViolations = (budget.lazyModules ?? []).flatMap((key) => {
        if (!manifest[key]?.isDynamicEntry) {
            return [
                `moduł leniwy ${key} nie jest osobnym chunkiem dynamicznym`,
            ];
        }

        return entryKeys.has(key) || entryJs.has(manifest[key].file)
            ? [`moduł leniwy ${key} jest statycznie w entry`]
            : [];
    });

    const limits = budget.limits;
    const warningLimits = budget.warnings ?? {};
    const failures = [];
    const warnings = [];
    const check = (target, label, value, limit) => {
        if (limit !== undefined && value > limit) {
            target.push(`${label}: ${value} KB > ${limit} KB`);
        }
    };

    check(
        failures,
        'krytyczny CSS (gzip)',
        measurements.cssGzipKb,
        limits.cssGzipKb,
    );
    check(
        warnings,
        'entry JS (gzip)',
        measurements.entryJsGzipKb,
        warningLimits.entryJsGzipKb,
    );
    check(
        warnings,
        `największy chunk strony publicznej ${largest.file} (gzip)`,
        measurements.largestPublicChunk.gzipKb,
        warningLimits.largestChunkGzipKb,
    );

    for (const [page, sizes] of Object.entries(measurements.pages)) {
        check(
            failures,
            `${page} — łączny JS strony publicznej (gzip)`,
            sizes.totalJsGzipKb,
            limits.publicPageTotalJsGzipKb,
        );
        check(
            warnings,
            `${page} — chunk strony (gzip)`,
            sizes.pageJsGzipKb,
            warningLimits.pageJsGzipKb,
        );
    }

    for (const key of leakedSources) {
        failures.push(`moduł testowy trafił do buildu klienta: ${key}`);
    }

    failures.push(...lazyViolations);

    if (options.json) {
        console.log(
            JSON.stringify(
                { measurements, limits, warningLimits, failures, warnings },
                null,
                2,
            ),
        );
    } else {
        console.log('Budżet bundla (gzip, poziom 9, tylko importy statyczne):');
        console.log(
            `  entry JS                  ${measurements.entryJsGzipKb} KB / ostrzeżenie ${warningLimits.entryJsGzipKb ?? '—'} KB`,
        );
        console.log(
            `  krytyczny CSS (+fonty)    ${measurements.cssGzipKb} KB / ${limits.cssGzipKb} KB`,
        );

        for (const [page, sizes] of Object.entries(measurements.pages)) {
            console.log(
                `  ${page}\n    chunk strony            ${sizes.pageJsGzipKb} KB / ostrzeżenie ${warningLimits.pageJsGzipKb ?? '—'} KB\n    łączny JS               ${sizes.totalJsGzipKb} KB / ${limits.publicPageTotalJsGzipKb} KB`,
            );
        }

        console.log(
            `  największy chunk          ${measurements.largestPublicChunk.gzipKb} KB / ostrzeżenie ${warningLimits.largestChunkGzipKb ?? '—'} KB (${largest.file})`,
        );
        console.log(`  liczba chunków JS         ${measurements.chunkCount}`);
        console.log(
            `  moduły leniwe poza entry  ${(budget.lazyModules ?? []).length - lazyViolations.length}/${(budget.lazyModules ?? []).length}`,
        );
    }

    if (warnings.length > 0) {
        console.warn('\nWARN bundle-budget (nie blokuje; sprawdź przyczynę):');

        for (const warning of warnings) {
            console.warn(`  - ${warning}`);
        }
    }

    if (failures.length > 0) {
        console.error('\nFAIL bundle-budget:');

        for (const failure of failures) {
            console.error(`  - ${failure}`);
        }

        process.exit(1);
    }

    console.log('\nPASS bundle-budget');
}

try {
    main();
} catch (error) {
    console.error(`FAIL bundle-budget: ${error.message}`);
    process.exit(1);
}
