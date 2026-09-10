import { execFileSync } from 'node:child_process';

let failed = false;
function check(label, command, args) {
    try {
        execFileSync(command, args, { stdio: 'pipe', timeout: 20000 });
        console.log(`PASS ${label}`);
    } catch {
        failed = true;
        console.error(`FAIL ${label} — sprawdź make logs oraz make setup.`);
    }
}

function checkViteCors() {
    const appOrigin = `http://localhost:${process.env.APP_PORT ?? 8080}`;
    const vitePort = process.env.VITE_PORT ?? 5173;

    try {
        const headers = execFileSync(
            'curl',
            [
                '--fail',
                '--silent',
                '--max-time',
                '10',
                '--header',
                `Origin: ${appOrigin}`,
                '--dump-header',
                '-',
                '--output',
                '/dev/null',
                `http://127.0.0.1:${vitePort}/@vite/client`,
            ],
            { encoding: 'utf8', timeout: 20000 },
        );

        if (
            !headers
                .toLowerCase()
                .includes(
                    `access-control-allow-origin: ${appOrigin}`.toLowerCase(),
                )
        ) {
            throw new Error('Vite did not allow the Laravel origin.');
        }

        console.log('PASS Vite/HMR CORS');
    } catch {
        failed = true;
        console.error(
            'FAIL Vite/HMR CORS — sprawdź make logs oraz make setup.',
        );
    }
}

check('Node 24', 'node', [
    '-e',
    "process.exit(process.versions.node.startsWith('24.') ? 0 : 1)",
]);
check('PHP 8.5 i rozszerzenia', 'php', [
    '-r',
    `
    $required = ['pdo_pgsql', 'pdo_sqlite', 'redis', 'intl', 'mbstring', 'zip', 'pcntl'];
    exit(str_starts_with(PHP_VERSION, '8.5.') && !array_diff($required, get_loaded_extensions()) ? 0 : 1);
`,
]);
check('Zależności PHP', 'composer', ['check-platform-reqs']);
check('PostgreSQL i migracje', 'php', [
    'artisan',
    'migrate:status',
    '--no-interaction',
]);
check('Redis i lokalny SMTP', 'php', [
    '-r',
    `
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
    if (config('app.env') !== 'local' || config('database.default') !== 'pgsql') { exit(1); }
    Illuminate\\Support\\Facades\\Redis::connection()->ping();
    $smtp = @fsockopen('mailpit', 1025, $code, $message, 5);
    exit($smtp ? 0 : 1);
`,
]);
check('HTTP Laravel', 'curl', [
    '--fail',
    '--silent',
    '--max-time',
    '10',
    'http://web/up',
]);
check('Vite/HMR', 'curl', [
    '--fail',
    '--silent',
    '--max-time',
    '10',
    `http://127.0.0.1:${process.env.VITE_PORT ?? 5173}/@vite/client`,
]);
checkViteCors();
process.exitCode = failed ? 1 : 0;
