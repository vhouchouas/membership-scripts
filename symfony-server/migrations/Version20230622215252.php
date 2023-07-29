<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230622215252 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE member (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, postal_code VARCHAR(10) DEFAULT NULL, hello_asso_last_registration_event_id INTEGER NOT NULL, city VARCHAR(255) DEFAULT NULL, how_did_you_know_zwp VARCHAR(1000) DEFAULT NULL, want_to_do VARCHAR(1000) DEFAULT NULL, first_registration_date DATETIME NOT NULL, last_registration_date DATETIME NOT NULL, is_zwprofessional BOOLEAN NOT NULL, notification_sent_to_admin BOOLEAN NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_70E4FA78E7927C74 ON member (email)');
        $this->addSql('CREATE TABLE option (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, value VARCHAR(255) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5A8600B05E237E06 ON option (name)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE member');
        $this->addSql('DROP TABLE option');
    }
}
