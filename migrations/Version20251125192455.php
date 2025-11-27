<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251125192455 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE deadline (id INT AUTO_INCREMENT NOT NULL, group_subject_teacher_id INT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, file_name VARCHAR(255) DEFAULT NULL, original_file_name VARCHAR(255) DEFAULT NULL, max_points INT NOT NULL, deadline_date DATETIME NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_B74774F2B5C9DEF7 (group_subject_teacher_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE deadline ADD CONSTRAINT FK_B74774F2B5C9DEF7 FOREIGN KEY (group_subject_teacher_id) REFERENCES group_subject_teacher (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE deadline DROP FOREIGN KEY FK_B74774F2B5C9DEF7');
        $this->addSql('DROP TABLE deadline');
    }
}
