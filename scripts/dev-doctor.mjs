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
process.exitCode = failed ? 1 : 0;
