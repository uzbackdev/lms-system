<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251126174106 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE deadline_submission (id INT AUTO_INCREMENT NOT NULL, deadline_id INT NOT NULL, student_id INT NOT NULL, file_name VARCHAR(255) NOT NULL, original_file_name VARCHAR(255) NOT NULL, submitted_at DATETIME NOT NULL, status VARCHAR(20) NOT NULL, points INT DEFAULT NULL, teacher_comment LONGTEXT DEFAULT NULL, graded_at DATETIME DEFAULT NULL, INDEX IDX_F083E0B473EA0AF8 (deadline_id), INDEX IDX_F083E0B4CB944F1A (student_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE deadline_submission ADD CONSTRAINT FK_F083E0B473EA0AF8 FOREIGN KEY (deadline_id) REFERENCES deadline (id)');
        $this->addSql('ALTER TABLE deadline_submission ADD CONSTRAINT FK_F083E0B4CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE deadline ADD CONSTRAINT FK_B74774F24A798B6F FOREIGN KEY (semester_id) REFERENCES semester (id)');
        $this->addSql('CREATE INDEX IDX_B74774F24A798B6F ON deadline (semester_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_teacher_group_subject_date ON deadline (group_subject_teacher_id, deadline_date)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE deadline_submission DROP FOREIGN KEY FK_F083E0B473EA0AF8');
        $this->addSql('ALTER TABLE deadline_submission DROP FOREIGN KEY FK_F083E0B4CB944F1A');
        $this->addSql('DROP TABLE deadline_submission');
        $this->addSql('ALTER TABLE deadline DROP FOREIGN KEY FK_B74774F24A798B6F');
        $this->addSql('DROP INDEX IDX_B74774F24A798B6F ON deadline');
        $this->addSql('DROP INDEX unique_teacher_group_subject_date ON deadline');
    }
}
