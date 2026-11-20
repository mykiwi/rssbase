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

    make('stylesheet', 'database');

    $port = free_port($c['http_port']);
    io()->title("Serving the application on http://localhost:{$port}");

    run(
        ['frankenphp', 'run', '--config', $c['caddyfile'], '--adapter', 'caddyfile'],
        context: $c
            ->withEnvironment([
                'SERVER_NAME' => "http://localhost:{$port}",
                'CADDY_ADMIN' => $c['admin_address'],
                'FRANKENPHP_WORKER_CONFIG' => 'import watch_code',
            ])
            // Castor forwards no signal without pcntl: without a PTY, FrankenPHP stays in the
            // terminal's process group and receives CTRL+C itself.
            ->withPty(false),
    );
}

/**
 * The first port, from $preferred on, that nothing holds on the loopback interfaces.
 */
function free_port(int $preferred): int
{
    for ($port = $preferred; $port < $preferred + 100; ++$port) {
        if (is_free_port($port)) {
            return $port;
        }
    }

    throw new \RuntimeException(\sprintf('No free port between %d and %d.', $preferred, $port - 1));
}

function is_free_port(int $port): bool
{
    // Binding, unlike connecting, also sees a port held on IPv6 only, where FrankenPHP listens too.
    foreach (['127.0.0.1', '[::1]'] as $host) {
        $server = @stream_socket_server("tcp://{$host}:{$port}");

        if (false === $server) {
            return false;
        }

        fclose($server);
    }

    return true;
}

#[AsTask(description: 'Stops the server started by "castor start"')]
function stop(): void
{
    run(['frankenphp', 'stop', '--address', context()['admin_address']]);
}

#[AsTask(
    description: 'Installs the application (composer, importmap, Tailwind build, Chromium, database), skipping what is up to date',
    namespace: 'app',
    aliases: ['install'],
)]
function install(): void
{
    io()->title('Installing the application');

    make('install');
}

/**
 * Brings targets of app/Makefile up to date: a generated file is rebuilt only when one of its sources
 * changed, and independent targets build in parallel.
 */
function make(string ...$targets): void
{
    // An unbounded --jobs is fine: the graph of app/Makefile never has more than a few runnable recipes.
    run(['make', '--jobs', '--silent', '--no-print-directory', ...$targets]);
}

#[AsTask(description: 'Clears and warms the application cache', namespace: 'app', aliases: ['cache-clear'])]
function cache_clear(): void
{
    io()->title('Clearing the application cache');

    make('vendor');
    run(['bin/console', 'cache:clear']);
}

#[AsTask(description: 'Migrates the database schema', namespace: 'app:db', aliases: ['migrate'])]
function migrate(): void
{
    io()->title('Migrating the database schema');

    make('vendor');

    // SQLite creates the database file on the first connection: DBAL 4 cannot list SQLite databases,
    // so doctrine:database:create fails.
    run(['bin/console', 'doctrine:migrations:migrate', '-n', '--allow-no-migration', '--all-or-nothing']);
}
