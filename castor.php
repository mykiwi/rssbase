<?php

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\guard_min_version;
use function Castor\import;
use function Castor\io;
use function Castor\run;

defined('CASTOR_USE_CHDIR') || define('CASTOR_USE_CHDIR', true);

guard_min_version('1.9.0');

import(__DIR__ . '/.castor');

#[AsTask(description: 'Serves the application in the foreground, in worker mode, restarting the workers when the code changes')]
function start(): void
{
    $c = context();

    io()->title("Serving the application on http://localhost:{$c['http_port']}");

    run(
        ['frankenphp', 'run', '--config', $c['caddyfile'], '--adapter', 'caddyfile'],
        context: $c
            ->withEnvironment([
                'SERVER_NAME' => "http://localhost:{$c['http_port']}",
                'CADDY_ADMIN' => $c['admin_address'],
                'FRANKENPHP_WORKER_CONFIG' => 'import watch_code',
            ])
            // Castor forwards no signal without pcntl: without a PTY, FrankenPHP stays in the
            // terminal's process group and receives CTRL+C itself.
            ->withPty(false),
    );
}

#[AsTask(description: 'Stops the server started by "castor start"')]
function stop(): void
{
    run(['frankenphp', 'stop', '--address', context()['admin_address']]);
}

#[AsTask(description: 'Installs the application (composer, importmap, Tailwind build...)', namespace: 'app', aliases: ['install'])]
function install(): void
{
    io()->title('Installing the application');

    // The auto-scripts of composer.json also install the importmap packages in assets/vendor/.
    io()->section('Installing PHP and JavaScript dependencies');
    run(['composer', 'install', '-n', '--prefer-dist', '--optimize-autoloader']);

    io()->section('Installing Chromium for the browser tests');
    run(['vendor/bin/playwright-install', 'chromium']);

    io()->section('Building the stylesheet with Tailwind');
    run(['bin/console', 'tailwind:build']);

    migrate();
}

#[AsTask(description: 'Clears and warms the application cache', namespace: 'app', aliases: ['cache-clear'])]
function cache_clear(): void
{
    io()->title('Clearing the application cache');

    run(['bin/console', 'cache:clear']);
}

#[AsTask(description: 'Migrates the database schema', namespace: 'app:db', aliases: ['migrate'])]
function migrate(): void
{
    io()->title('Migrating the database schema');

    // SQLite creates the database file on the first connection: DBAL 4 cannot list SQLite databases,
    // so doctrine:database:create fails.
    run(['bin/console', 'doctrine:migrations:migrate', '-n', '--allow-no-migration', '--all-or-nothing']);
}
