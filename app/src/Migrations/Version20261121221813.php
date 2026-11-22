<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261121221813 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the account table, the trace of the accounts that signed in';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(
            'CREATE TABLE account (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, did VARCHAR(2048) NOT NULL, handle VARCHAR(253) NOT NULL, last_login_at DATETIME NOT NULL)',
        );
        $this->addSql('CREATE UNIQUE INDEX uniq_account_did ON account (did)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE account');
    }
}
