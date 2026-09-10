import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import {
    copyFileSync,
    mkdtempSync,
    readFileSync,
    rmSync,
    writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';

function fixture(run) {
    const cwd = mkdtempSync(join(tmpdir(), 'starter-setup-'));
    try {
        copyFileSync('Makefile', join(cwd, 'Makefile'));
        copyFileSync('.env.example', join(cwd, '.env.example'));
        run(cwd);
    } finally {
        rmSync(cwd, { recursive: true, force: true });
    }
}

function readEnv(path) {
    return Object.fromEntries(
        readFileSync(path, 'utf8')
            .split('\n')
            .filter((line) => line && !line.startsWith('#'))
            .map((line) => line.split(/=(.*)/s, 2)),
    );
}

await test('env creates and preserves the local application configuration', () => {
    fixture((cwd) => {
        execFileSync('make', ['env'], { cwd });
        assert.equal(
            readFileSync(join(cwd, '.env'), 'utf8'),
            readFileSync('.env.example', 'utf8'),
        );

        writeFileSync(join(cwd, '.env'), 'APP_KEY=existing-local-key\n');
        execFileSync('make', ['env'], { cwd });
        assert.equal(
            readFileSync(join(cwd, '.env'), 'utf8'),
            'APP_KEY=existing-local-key\n',
        );
    });
});

await test('shell opens Bash in the application container', () => {
    fixture((cwd) => {
        const command = execFileSync('make', ['--dry-run', 'shell'], {
            cwd,
            encoding: 'utf8',
        });

        assert.match(command, /run --rm --no-deps app bash/);
    });
});

await test('parallel test target runs Pest in four processes by default', () => {
    fixture((cwd) => {
        const command = execFileSync('make', ['--dry-run', 'test-parallel'], {
            cwd,
            encoding: 'utf8',
        });

        assert.match(
            command,
            /php artisan test --compact --parallel --processes=4/,
        );
    });
});

await test('Docker environment configures every local service', () => {
    const environment = readEnv('.env.example');

    assert.deepEqual(
        {
            app: environment.APP_URL,
            database: `${environment.DB_CONNECTION}://${environment.DB_HOST}:${environment.DB_PORT}`,
            cache: environment.CACHE_STORE,
            session: environment.SESSION_DRIVER,
            queue: environment.QUEUE_CONNECTION,
            redis: environment.REDIS_HOST,
            mail: `${environment.MAIL_HOST}:${environment.MAIL_PORT}`,
            vite: environment.VITE_PORT,
        },
        {
            app: 'http://localhost:8080',
            database: 'pgsql://postgres:5432',
            cache: 'redis',
            session: 'redis',
            queue: 'redis',
            redis: 'redis',
            mail: 'mailpit:1025',
            vite: '5173',
        },
    );
});

await test('testing environment isolates application dependencies', () => {
    const environment = readEnv('.env.testing');

    assert.deepEqual(
        {
            environment: environment.APP_ENV,
            bcryptRounds: environment.BCRYPT_ROUNDS,
            cache: environment.CACHE_STORE,
            database: `${environment.DB_CONNECTION}:${environment.DB_DATABASE}`,
            mail: environment.MAIL_MAILER,
            queue: environment.QUEUE_CONNECTION,
            session: environment.SESSION_DRIVER,
        },
        {
            environment: 'testing',
            bcryptRounds: '4',
            cache: 'array',
            database: 'sqlite::memory:',
            mail: 'array',
            queue: 'sync',
            session: 'array',
        },
    );
});

for (const env of [
    'APP_ENV=production\nDB_HOST=postgres\n',
    'APP_ENV=local\nDB_HOST=external.example\n',
]) {
    await test(`setup refuses unsafe configuration: ${env.trim().replaceAll('\n', ', ')}`, () => {
        fixture((cwd) => {
            writeFileSync(join(cwd, '.env'), env);
            const result = spawnSync('make', ['setup'], {
                cwd,
                encoding: 'utf8',
            });
            assert.notEqual(result.status, 0);
            assert.match(result.stdout, /Setup wymaga/);
            assert.doesNotMatch(result.stdout, /docker compose/);
        });
    });
}
