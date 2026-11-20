<?php

namespace context;

use Castor\Attribute\AsContext;
use Castor\Context;
use Symfony\Component\Process\Process;

#[AsContext(default: true)]
function create_default_context(): Context
{
    $rootDir = \dirname(__DIR__);

    $data = [
        'root_dir' => $rootDir,
        'app_dir' => "{$rootDir}/app",
        'caddyfile' => "{$rootDir}/infra/frankenphp/Caddyfile",
        // A socket rather than Caddy's default localhost:2019, which any other Caddy may hold.
        'admin_address' => "unix/{$rootDir}/app/var/frankenphp-dev.sock",
        // Preferred ports: castor takes the next free one when another program holds them.
        'http_port' => 8000,
    ];

    // A PTY only helps when someone can answer prompts (composer, bin/console...): in CI, pipes
    // or AI agents, a prompt would wait forever on a terminal nobody types in.
    $canAnswerPrompts = false === getenv('CI') && stream_isatty(\STDIN);

    return new Context(
        $data,
        workingDirectory: $data['app_dir'],
        pty: Process::isPtySupported() && $canAnswerPrompts,
    );
}
