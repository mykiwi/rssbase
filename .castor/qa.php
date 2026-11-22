<?php

namespace qa;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;

use function Castor\capture;
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

    // The formatter writes the files that the other jobs read, the stylesheet included, so it runs
    // first, alone.
    $format = format(check: $inCi);

    // Everything the jobs below need, built once: their own make calls then find nothing to do, and
    // parallel jobs cannot race on the same recipe.
    \make('stylesheet', 'chromium', 'test-database');

    $jobs = [
        'lint' => fail_on_exit_code(lint(...)),
        'analyze' => fail_on_exit_code(analyze(...)),
        'symfony_lsp' => fail_on_exit_code(symfony_lsp(...)),
        'console_lint' => fail_on_exit_code(console_lint(...)),
        'translations' => fail_on_exit_code(translations(...)),
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

#[AsTask(description: 'Lints the container, the templates and the YAML files with the Symfony console')]
function console_lint(): int
{
    io()->section('Linting with the Symfony console...');

    \make('vendor');

    return max(
        exit_code(['bin/console', 'lint:container']),
        // Unlike Mago, it compiles the templates with the Twig environment of the application: it
        // sees an unknown filter, function or component.
        exit_code(['bin/console', 'lint:twig', 'templates']),
        exit_code(['bin/console', 'lint:yaml', 'config', 'translations', '--parse-tags']),
    );
}

#[AsTask(description: 'Checks the ICU syntax of the translations, and that no message is missing or unused in a language')]
function translations(): int
{
    io()->section('Checking the translations...');

    \make('vendor');

    $exitCode = exit_code(['bin/console', 'lint:translations']);

    /** @var list<string> $locales */
    $locales = json_decode(
        capture(['bin/console', 'debug:container', '--parameter', 'kernel.enabled_locales', '--format', 'json']),
        associative: true,
        flags: \JSON_THROW_ON_ERROR,
    )['kernel.enabled_locales'];

    foreach ($locales as $locale) {
        // One run per state: without an option, the command also fails on a message identical to
        // its fallback, like a word that both languages share. Only the messages of the application:
        // the security domain of Symfony holds messages that the application never shows.
        foreach (['--only-missing', '--only-unused'] as $state) {
            $exitCode = max($exitCode, exit_code(['bin/console', 'debug:translation', $locale, $state, '--domain', 'messages']));
        }
    }

    return $exitCode;
}

/**
 * @param list<string> $rawTokens
 */
#[AsTask(description: 'Runs the PHPUnit "default" suite', aliases: ['phpunit'])]
function phpunit(#[AsRawTokens] array $rawTokens = []): int
{
    io()->section('Running PHPUnit...');

    // The functional tests render pages, which need the stylesheet, and write to the database.
    \make('stylesheet', 'test-database');

    return exit_code(['bin/phpunit', '--testsuite', 'default', ...$rawTokens]);
}

/**
 * @param list<string> $rawTokens
 */
#[AsTask(description: 'Runs the PHPUnit "e2e" suite in Chromium, with requests handled by the test kernel', aliases: ['e2e'])]
function e2e(#[AsRawTokens] array $rawTokens = []): int
{
    io()->section('Running the browser tests...');

    \make('stylesheet', 'chromium', 'test-database');

    return exit_code(['bin/phpunit', '--testsuite', 'e2e', ...$rawTokens]);
}

#[AsTask(description: 'Audits the Composer dependencies for security advisories')]
function security_audit(): int
{
    io()->section('Running Composer audit...');

    return exit_code(['composer', 'audit']);
}
