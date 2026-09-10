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
        copyFileSync('.env.docker.example', join(cwd, '.env.docker.example'));
        run(cwd);
    } finally {
        rmSync(cwd, { recursive: true, force: true });
    }
}

await test('env creates Docker configuration and preserves existing local files on repeat', () => {
    fixture((cwd) => {
        writeFileSync(join(cwd, '.env'), 'APP_KEY=existing-host-key\n');
        execFileSync('make', ['env'], { cwd });
        assert.equal(
            readFileSync(join(cwd, '.env.docker'), 'utf8'),
            readFileSync('.env.docker.example', 'utf8'),
        );
        writeFileSync(
            join(cwd, '.env.docker'),
            'APP_KEY=existing-docker-key\n',
        );
        execFileSync('make', ['env'], { cwd });
        assert.equal(
            readFileSync(join(cwd, '.env.docker'), 'utf8'),
            'APP_KEY=existing-docker-key\n',
        );
        assert.equal(
            readFileSync(join(cwd, '.env'), 'utf8'),
            'APP_KEY=existing-host-key\n',
        );
    });
});

for (const env of [
    'APP_ENV=production\nDB_HOST=postgres\n',
    'APP_ENV=local\nDB_HOST=external.example\n',
]) {
    await test(`setup refuses unsafe configuration: ${env.trim().replaceAll('\n', ', ')}`, () => {
        fixture((cwd) => {
            writeFileSync(join(cwd, '.env.docker'), env);
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
