<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251206144816 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE person ADD name VARCHAR(255) NOT NULL, ADD surname VARCHAR(255) NOT NULL, ADD address VARCHAR(255) DEFAULT NULL, ADD phone VARCHAR(255) DEFAULT NULL, ADD created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE login login VARCHAR(8) NOT NULL');
        $this->addSql('ALTER TABLE student DROP surname, DROP name, DROP address');
        $this->addSql('ALTER TABLE teacher DROP name, DROP surname');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE person DROP name, DROP surname, DROP address, DROP phone, DROP created_at, DROP updated_at, CHANGE login login VARCHAR(15) NOT NULL');
        $this->addSql('ALTER TABLE student ADD surname VARCHAR(255) NOT NULL, ADD name VARCHAR(255) NOT NULL, ADD address VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE teacher ADD name VARCHAR(255) NOT NULL, ADD surname VARCHAR(255) NOT NULL');
    }
}
