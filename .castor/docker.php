<?php

namespace docker;

use Castor\Attribute\AsTask;
use Castor\Context;

use function Castor\capture;
use function Castor\context;
use function Castor\io;
use function Castor\run;

#[AsTask(description: 'Builds the development image (the runtime, without the application) from the flake and loads it into Docker')]
function build(): void
{
    io()->section('Building the development image');

    $c = context()->withWorkingDirectory(context()['root_dir']);
    $stream = capture(['nix', 'build', '.#docker-image-dev', '--no-link', '--print-out-paths'], context: $c);

    // The flake builds a script that streams the image, so that no image archive lands in the Nix store.
    run("{$stream} | docker load --quiet", context: $c->withQuiet());
}

#[AsTask(description: 'Serves the application, mounted in the development image, in the background, on the first free port from 8080')]
function up(): void
{
    build();
    \make('stylesheet', 'database');

    $port = published_port() ?? \free_port(context()['docker_http_port']);
    run(['docker', 'compose', 'up', '--detach', '--wait'], context: compose_context($port));

    io()->success("The application is served on http://localhost:{$port}");
}

#[AsTask(description: 'Stops and removes the containers of compose.yaml')]
function down(): void
{
    // compose.yaml requires a port even to stop: any value does.
    run(['docker', 'compose', 'down', '--remove-orphans'], context: compose_context(context()['docker_http_port']));
}

/**
 * The host port of the running container, which keeps it across docker:up.
 */
function published_port(): ?int
{
    $address = capture(
        ['docker', 'compose', 'port', 'app', '8080'],
        context: compose_context(context()['docker_http_port'])->withAllowFailure()->withQuiet(),
    );

    return preg_match('/:(\d+)$/', trim($address), $matches) ? (int) $matches[1] : null;
}

function compose_context(int $port): Context
{
    $c = context();

    return $c
        ->withWorkingDirectory($c['root_dir'])
        ->withEnvironment([
            'HOST_UID' => trim(capture(['id', '-u'])),
            'HOST_GID' => trim(capture(['id', '-g'])),
            'HTTP_PORT' => (string) $port,
        ]);
}
