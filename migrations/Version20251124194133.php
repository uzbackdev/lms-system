<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251124194133 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE lesson_plan (id INT AUTO_INCREMENT NOT NULL, lesson_id INT DEFAULT NULL, date DATE NOT NULL, topic LONGTEXT NOT NULL, INDEX IDX_E42B9D19CDF80196 (lesson_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE lesson_plan ADD CONSTRAINT FK_E42B9D19CDF80196 FOREIGN KEY (lesson_id) REFERENCES lesson (id)');
        $this->addSql('ALTER TABLE lesson DROP topic');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lesson_plan DROP FOREIGN KEY FK_E42B9D19CDF80196');
        $this->addSql('DROP TABLE lesson_plan');
        $this->addSql('ALTER TABLE lesson ADD topic LONGTEXT DEFAULT NULL');
    }
}
