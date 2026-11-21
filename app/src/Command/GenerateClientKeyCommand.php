<?php

declare(strict_types=1);

namespace App\Command;

use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:atproto:generate-client-key',
    description: 'Prints a new ES256 private key for the confidential OAuth client, as a JWK for ATPROTO_CLIENT_PRIVATE_JWK',
    help: 'Store it as a secret: <info>bin/console app:atproto:generate-client-key | bin/console secrets:set ATPROTO_CLIENT_PRIVATE_JWK -</info>',
)]
final class GenerateClientKeyCommand
{
    public function __invoke(OutputInterface $output): int
    {
        $key = JWKFactory::createECKey('P-256', ['alg' => 'ES256', 'use' => 'sig']);
        $key = new JWK([...$key->all(), 'kid' => $key->thumbprint('sha256')]);

        $output->writeln(json_encode($key, \JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
