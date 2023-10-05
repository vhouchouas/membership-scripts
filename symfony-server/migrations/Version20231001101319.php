<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231001101319 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE member_additional_email (member_id INT NOT NULL, email VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_AB8774A3E7927C74 (email), PRIMARY KEY(member_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE member_additional_email ADD CONSTRAINT FK_AB8774A37597D3FE FOREIGN KEY (member_id) REFERENCES `member` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE member_additional_email DROP FOREIGN KEY FK_AB8774A37597D3FE');
        $this->addSql('DROP TABLE member_additional_email');
    }
}
