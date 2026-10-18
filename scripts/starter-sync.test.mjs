import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { test } from 'node:test';

const script = resolve('scripts/starter-sync.sh');
const identity = {
    GIT_AUTHOR_NAME: 'Test',
    GIT_AUTHOR_EMAIL: 'test@example.test',
    GIT_COMMITTER_NAME: 'Test',
    GIT_COMMITTER_EMAIL: 'test@example.test',
};

function git(cwd, ...args) {
    return execFileSync('git', args, {
        cwd,
        env: { ...process.env, ...identity },
        encoding: 'utf8',
    }).trim();
}

function commit(cwd, files, message) {
    for (const [name, content] of Object.entries(files)) {
        writeFileSync(join(cwd, name), content);
    }
    git(cwd, 'add', '.');
    git(cwd, 'commit', '--quiet', '-m', message);
}

/** A starter repository and a client cloned from it with its own commit. */
function fixture(run) {
    const root = mkdtempSync(join(tmpdir(), 'starter-sync-'));
    const starter = join(root, 'starter');
    const client = join(root, 'client');

    try {
        execFileSync('git', [
            'init',
            '--quiet',
            '--initial-branch=main',
            starter,
        ]);
        commit(
            starter,
            { STARTER_VERSION: '1\n', 'shared.txt': 'starter v1\n' },
            'starter 1',
        );
        execFileSync('git', ['clone', '--quiet', starter, client]);
        commit(client, { 'client.txt': 'client\n' }, 'client work');
        run({ starter, client });
    } finally {
        rmSync(root, { recursive: true, force: true });
    }
}

function sync(client, starter) {
    return spawnSync('sh', [script], {
        cwd: client,
        env: { ...process.env, ...identity, STARTER_REPOSITORY: starter },
        encoding: 'utf8',
    });
}

void test('merges a new starter version into its own branch', () => {
    fixture(({ starter, client }) => {
        commit(
            starter,
            { STARTER_VERSION: '2\n', 'feature.txt': 'new\n' },
            'starter 2',
        );

        const result = sync(client, starter);

        assert.equal(result.status, 0, result.stderr);
        assert.equal(result.stdout.trim().split('\n').at(-1), 'starter-sync/2');
        assert.equal(git(client, 'branch', '--show-current'), 'starter-sync/2');
        assert.equal(
            readFileSync(join(client, 'feature.txt'), 'utf8'),
            'new\n',
        );
        assert.equal(
            readFileSync(join(client, 'client.txt'), 'utf8'),
            'client\n',
        );
        assert.equal(
            git(client, 'log', '-1', '--format=%s'),
            'chore(starter): merge starter 2',
        );
        assert.notEqual(
            git(client, 'rev-parse', 'main'),
            git(client, 'rev-parse', 'HEAD'),
        );
    });
});

void test('does nothing when the client already has the starter', () => {
    fixture(({ starter, client }) => {
        const result = sync(client, starter);

        assert.equal(result.status, 2);
        assert.equal(git(client, 'branch', '--show-current'), 'main');
    });
});

void test('reports conflicts and leaves the client untouched', () => {
    fixture(({ starter, client }) => {
        commit(
            starter,
            { STARTER_VERSION: '2\n', 'shared.txt': 'starter v2\n' },
            'starter 2',
        );
        commit(client, { 'shared.txt': 'client change\n' }, 'client change');
        const head = git(client, 'rev-parse', 'HEAD');

        const result = sync(client, starter);

        assert.equal(result.status, 3);
        assert.match(result.stdout, /shared\.txt/);
        assert.equal(git(client, 'branch', '--show-current'), 'main');
        assert.equal(git(client, 'rev-parse', 'HEAD'), head);
        assert.equal(git(client, 'status', '--porcelain'), '');
        assert.equal(git(client, 'branch', '--list', 'starter-sync/*'), '');
    });
});
