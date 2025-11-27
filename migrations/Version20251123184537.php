<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251123184537 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lesson_plan DROP FOREIGN KEY FK_E42B9D1955D229DE');
        $this->addSql('ALTER TABLE lesson_schedule DROP FOREIGN KEY FK_127F5BE2CDF80196');
        $this->addSql('ALTER TABLE assignment DROP FOREIGN KEY FK_30C544BAB5C9DEF7');
        $this->addSql('ALTER TABLE assignment_file DROP FOREIGN KEY FK_D799FB8CD19302F8');
        $this->addSql('ALTER TABLE student_submission DROP FOREIGN KEY FK_36DAB712D19302F8');
        $this->addSql('ALTER TABLE student_submission DROP FOREIGN KEY FK_36DAB712CB944F1A');
        $this->addSql('ALTER TABLE assignment_deadline DROP FOREIGN KEY FK_73450007D19302F8');
        $this->addSql('DROP TABLE lesson_plan');
        $this->addSql('DROP TABLE lesson_schedule');
        $this->addSql('DROP TABLE assignment');
        $this->addSql('DROP TABLE assignment_file');
        $this->addSql('DROP TABLE student_submission');
        $this->addSql('DROP TABLE assignment_deadline');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE lesson_plan (id INT AUTO_INCREMENT NOT NULL, lesson_schedule_id INT NOT NULL, topic VARCHAR(500) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_E42B9D1955D229DE (lesson_schedule_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE lesson_schedule (id INT AUTO_INCREMENT NOT NULL, lesson_id INT NOT NULL, date DATE NOT NULL, INDEX IDX_127F5BE2CDF80196 (lesson_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE assignment (id INT AUTO_INCREMENT NOT NULL, group_subject_teacher_id INT NOT NULL, title VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_30C544BAB5C9DEF7 (group_subject_teacher_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE assignment_file (id INT AUTO_INCREMENT NOT NULL, assignment_id INT NOT NULL, filename VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, original_name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_D799FB8CD19302F8 (assignment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE student_submission (id INT AUTO_INCREMENT NOT NULL, assignment_id INT NOT NULL, student_id INT NOT NULL, filename VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, submitted_at DATETIME NOT NULL, score INT DEFAULT NULL, INDEX IDX_36DAB712D19302F8 (assignment_id), INDEX IDX_36DAB712CB944F1A (student_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE assignment_deadline (id INT AUTO_INCREMENT NOT NULL, assignment_id INT NOT NULL, due_at DATETIME NOT NULL, max_score INT NOT NULL, INDEX IDX_73450007D19302F8 (assignment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE lesson_plan ADD CONSTRAINT FK_E42B9D1955D229DE FOREIGN KEY (lesson_schedule_id) REFERENCES lesson_schedule (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE lesson_schedule ADD CONSTRAINT FK_127F5BE2CDF80196 FOREIGN KEY (lesson_id) REFERENCES lesson (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE assignment ADD CONSTRAINT FK_30C544BAB5C9DEF7 FOREIGN KEY (group_subject_teacher_id) REFERENCES group_subject_teacher (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE assignment_file ADD CONSTRAINT FK_D799FB8CD19302F8 FOREIGN KEY (assignment_id) REFERENCES assignment (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE student_submission ADD CONSTRAINT FK_36DAB712D19302F8 FOREIGN KEY (assignment_id) REFERENCES assignment (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE student_submission ADD CONSTRAINT FK_36DAB712CB944F1A FOREIGN KEY (student_id) REFERENCES student (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE assignment_deadline ADD CONSTRAINT FK_73450007D19302F8 FOREIGN KEY (assignment_id) REFERENCES assignment (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
