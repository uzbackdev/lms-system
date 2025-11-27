<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251125141032 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE lesson_material (id INT AUTO_INCREMENT NOT NULL, lesson_plan_id INT NOT NULL, file_name VARCHAR(255) NOT NULL, file_type VARCHAR(50) NOT NULL, original_name VARCHAR(255) NOT NULL, INDEX IDX_34F93F8C1DE5503C (lesson_plan_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE lesson_material ADD CONSTRAINT FK_34F93F8C1DE5503C FOREIGN KEY (lesson_plan_id) REFERENCES lesson_plan (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lesson_material DROP FOREIGN KEY FK_34F93F8C1DE5503C');
        $this->addSql('DROP TABLE lesson_material');
    }
}
