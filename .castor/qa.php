<?php

namespace qa;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;

use function Castor\exit_code;
use function Castor\io;
use function Castor\parallel;

#[AsTask(
    description: 'Runs all QA tasks. In CI, checks the formatting instead of fixing it and audits the dependencies',
    aliases: ['qa', 'ci'],
)]
function all(): int
{
    $inCi = false !== getenv('CI');

    // Everything the jobs below need, built once: their own make calls then find nothing to do, and
    // parallel jobs cannot race on the same recipe.
    \make('stylesheet', 'chromium');

    // The formatter writes the files that the linter and the analyzer read, so it runs first, alone.
    $format = format(check: $inCi);

    $jobs = [
        'lint' => fail_on_exit_code(lint(...)),
        'analyze' => fail_on_exit_code(analyze(...)),
        'symfony_lsp' => fail_on_exit_code(symfony_lsp(...)),
        'phpunit' => fail_on_exit_code(phpunit(...)),
        'e2e' => fail_on_exit_code(e2e(...)),
    ];

    if ($inCi) {
        $jobs['security_audit'] = fail_on_exit_code(security_audit(...));
    }

    parallel(...$jobs);

    return $format;
}

/**
 * parallel() marks a job as failed only when it throws, not when it returns a non-zero exit code.
 *
 * @param callable(): int $task
 */
function fail_on_exit_code(callable $task): \Closure
{
    return static function () use ($task): void {
        $exitCode = $task();

        if (0 !== $exitCode) {
            throw new \RuntimeException(\sprintf('Exited with code %d.', $exitCode));
        }
    };
}

#[AsTask(description: 'Formats PHP and Twig files with Mago')]
function format(
    #[AsOption(description: 'Fail if files need formatting, without writing them')]
    bool $check = false,
): int {
    io()->section('Formatting with Mago...');

    $mode = $check ? ['--check'] : [];

    return max(exit_code(['mago', 'format', ...$mode]), exit_code(['mago', 'twig:fmt', ...$mode]));
}

#[AsTask(description: 'Lints PHP and Twig files with Mago')]
function lint(): int
{
    io()->section('Linting with Mago...');

    \make('vendor');

    return max(exit_code(['mago', 'lint']), exit_code(['mago', 'twig:lint']));
}

#[AsTask(description: 'Runs the Mago static analyzer')]
function analyze(): int
{
    io()->section('Analyzing with Mago...');

    \make('vendor');

    return exit_code(['mago', 'analyze']);
}

#[AsTask(description: 'Checks Symfony-specific problems (routes, services, templates...) with symfony-lsp')]
function symfony_lsp(): int
{
    io()->section('Checking with symfony-lsp...');

    \make('vendor');

    return exit_code(['symfony-lsp', 'check']);
}

/**
 * @param list<string> $rawTokens
 */
#[AsTask(description: 'Runs the PHPUnit "default" suite', aliases: ['phpunit'])]
function phpunit(#[AsRawTokens] array $rawTokens = []): int
{
    io()->section('Running PHPUnit...');

    // The functional tests render pages, which need the stylesheet.
    \make('stylesheet');

    return exit_code(['bin/phpunit', '--testsuite', 'default', ...$rawTokens]);
}

/**
 * @param list<string> $rawTokens
 */
#[AsTask(description: 'Runs the PHPUnit "e2e" suite in Chromium, with requests handled by the test kernel', aliases: ['e2e'])]
function e2e(#[AsRawTokens] array $rawTokens = []): int
{
    io()->section('Running the browser tests...');

    \make('stylesheet', 'chromium');

    return exit_code(['bin/phpunit', '--testsuite', 'e2e', ...$rawTokens]);
}

#[AsTask(description: 'Audits the Composer dependencies for security advisories')]
function security_audit(): int
{
    io()->section('Running Composer audit...');

    return exit_code(['composer', 'audit']);
}
