<?php

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\exit_code;
use function Castor\guard_min_version;
use function Castor\import;
use function Castor\io;
use function Castor\run;

defined('CASTOR_USE_CHDIR') || define('CASTOR_USE_CHDIR', true);

guard_min_version('1.9.0');

import(__DIR__ . '/.castor');

#[AsTask(description: 'Serves the application in the foreground, in worker mode, restarting the workers and rebuilding the stylesheet when the code changes')]
function start(): void
{
    $c = context();

    // A second server would take over the admin socket, and "castor stop" could no longer reach the first one.
    if (admin_socket_answers($c['admin_address'])) {
        throw new \RuntimeException('A server started by "castor start" is already running. Stop it with "castor stop".');
    }

    make('stylesheet', 'database');

    $port = free_port($c['http_port']);
    io()->title("Serving the application on http://rssbase.localhost:{$port}");

    run(
        [
            'bash',
            '-c',
            // A background job of a non-interactive shell ignores CTRL+C: the trap stops the watcher
            // once FrankenPHP exits, through CTRL+C, "castor stop" or a crash.
            'bin/console tailwind:build --watch & trap "kill $!" EXIT; frankenphp run "$@"',
            'start',
            '--config',
            $c['caddyfile'],
            '--adapter',
            'caddyfile',
        ],
        context: $c
            ->withEnvironment([
                // The subdomains are the other languages: fr.localhost is the French site. Browsers
                // refuse a cookie for "localhost" itself, so a session serves both languages only on
                // rssbase.localhost and fr.rssbase.localhost. 127.0.0.1 receives the OAuth callback
                // of the loopback client, which the specification forbids on localhost.
                'SERVER_NAME' => implode(', ', [
                    "http://localhost:{$port}",
                    "http://*.localhost:{$port}",
                    "http://*.rssbase.localhost:{$port}",
                    "http://127.0.0.1:{$port}",
                ]),
                'CADDY_ADMIN' => $c['admin_address'],
                'CADDY_SITE_CONFIG' => 'import hot_reload_hub',
                'FRANKENPHP_SITE_CONFIG' => 'import hot_reload',
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

/**
 * Whether a server listens on the admin socket. A socket file left by a crash refuses the connection.
 */
function admin_socket_answers(string $adminAddress): bool
{
    $socket = @stream_socket_client('unix://' . substr($adminAddress, \strlen('unix/')));

    if (false === $socket) {
        return false;
    }

    fclose($socket);

    return true;
}

#[AsTask(description: 'Stops the server started by "castor start"')]
function stop(): void
{
    run(['frankenphp', 'stop', '--address', context()['admin_address']]);
}

/**
 * @param list<string> $rawTokens
 */
#[AsTask(description: 'Runs Mate, the diagnostic tools of the application for coding agents')]
function mate(#[AsRawTokens] array $rawTokens = []): int
{
    make('vendor');

    return exit_code(['vendor/bin/mate', ...$rawTokens]);
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
