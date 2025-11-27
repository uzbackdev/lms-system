<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251109093629 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `admin` (id INT AUTO_INCREMENT NOT NULL, person_id INT NOT NULL, UNIQUE INDEX UNIQ_880E0D76217BBB47 (person_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE course (id INT AUTO_INCREMENT NOT NULL, number INT NOT NULL, UNIQUE INDEX UNIQ_169E6FB996901F54 (number), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE faculty (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_179660435E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE person (id INT AUTO_INCREMENT NOT NULL, login VARCHAR(15) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, UNIQUE INDEX UNIQ_34DCD176AA08CB10 (login), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE student (id INT AUTO_INCREMENT NOT NULL, group_id INT NOT NULL, person_id INT NOT NULL, surname VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, address VARCHAR(255) NOT NULL, INDEX IDX_B723AF33FE54D947 (group_id), UNIQUE INDEX UNIQ_B723AF33217BBB47 (person_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE student_group (id INT AUTO_INCREMENT NOT NULL, course_id INT NOT NULL, faculty_id INT NOT NULL, group_number INT NOT NULL, INDEX IDX_E5F73D58591CC992 (course_id), INDEX IDX_E5F73D58680CAB68 (faculty_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE teacher (id INT AUTO_INCREMENT NOT NULL, faculty_id INT NOT NULL, person_id INT NOT NULL, name VARCHAR(255) NOT NULL, surname VARCHAR(255) NOT NULL, INDEX IDX_B0F6A6D5680CAB68 (faculty_id), UNIQUE INDEX UNIQ_B0F6A6D5217BBB47 (person_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE `admin` ADD CONSTRAINT FK_880E0D76217BBB47 FOREIGN KEY (person_id) REFERENCES person (id)');
        $this->addSql('ALTER TABLE student ADD CONSTRAINT FK_B723AF33FE54D947 FOREIGN KEY (group_id) REFERENCES student_group (id)');
        $this->addSql('ALTER TABLE student ADD CONSTRAINT FK_B723AF33217BBB47 FOREIGN KEY (person_id) REFERENCES person (id)');
        $this->addSql('ALTER TABLE student_group ADD CONSTRAINT FK_E5F73D58591CC992 FOREIGN KEY (course_id) REFERENCES course (id)');
        $this->addSql('ALTER TABLE student_group ADD CONSTRAINT FK_E5F73D58680CAB68 FOREIGN KEY (faculty_id) REFERENCES faculty (id)');
        $this->addSql('ALTER TABLE teacher ADD CONSTRAINT FK_B0F6A6D5680CAB68 FOREIGN KEY (faculty_id) REFERENCES faculty (id)');
        $this->addSql('ALTER TABLE teacher ADD CONSTRAINT FK_B0F6A6D5217BBB47 FOREIGN KEY (person_id) REFERENCES person (id)');
        $this->addSql('DROP TABLE department');
        $this->addSql('ALTER TABLE group_subject_teacher ADD CONSTRAINT FK_ABE797AEFE54D947 FOREIGN KEY (group_id) REFERENCES student_group (id)');
        $this->addSql('ALTER TABLE group_subject_teacher ADD CONSTRAINT FK_ABE797AE23EDC87 FOREIGN KEY (subject_id) REFERENCES subject (id)');
        $this->addSql('ALTER TABLE group_subject_teacher ADD CONSTRAINT FK_ABE797AE41807E1D FOREIGN KEY (teacher_id) REFERENCES teacher (id)');
        $this->addSql('DROP INDEX IDX_FBCE3E7AAE80F5DF ON subject');
        $this->addSql('ALTER TABLE subject ADD course_number INT NOT NULL, ADD is_common_first_year TINYINT(1) NOT NULL, CHANGE department_id faculty_id INT NOT NULL');
        $this->addSql('ALTER TABLE subject ADD CONSTRAINT FK_FBCE3E7A680CAB68 FOREIGN KEY (faculty_id) REFERENCES faculty (id)');
        $this->addSql('CREATE INDEX IDX_FBCE3E7A680CAB68 ON subject (faculty_id)');
        $this->addSql('ALTER TABLE teacher_subject ADD CONSTRAINT FK_360CB33B41807E1D FOREIGN KEY (teacher_id) REFERENCES teacher (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE teacher_subject ADD CONSTRAINT FK_360CB33B23EDC87 FOREIGN KEY (subject_id) REFERENCES subject (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE subject DROP FOREIGN KEY FK_FBCE3E7A680CAB68');
        $this->addSql('ALTER TABLE group_subject_teacher DROP FOREIGN KEY FK_ABE797AEFE54D947');
        $this->addSql('ALTER TABLE group_subject_teacher DROP FOREIGN KEY FK_ABE797AE41807E1D');
        $this->addSql('ALTER TABLE teacher_subject DROP FOREIGN KEY FK_360CB33B41807E1D');
        $this->addSql('CREATE TABLE department (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, UNIQUE INDEX UNIQ_CD1DE18A5E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE `admin` DROP FOREIGN KEY FK_880E0D76217BBB47');
        $this->addSql('ALTER TABLE student DROP FOREIGN KEY FK_B723AF33FE54D947');
        $this->addSql('ALTER TABLE student DROP FOREIGN KEY FK_B723AF33217BBB47');
        $this->addSql('ALTER TABLE student_group DROP FOREIGN KEY FK_E5F73D58591CC992');
        $this->addSql('ALTER TABLE student_group DROP FOREIGN KEY FK_E5F73D58680CAB68');
        $this->addSql('ALTER TABLE teacher DROP FOREIGN KEY FK_B0F6A6D5680CAB68');
        $this->addSql('ALTER TABLE teacher DROP FOREIGN KEY FK_B0F6A6D5217BBB47');
        $this->addSql('DROP TABLE `admin`');
        $this->addSql('DROP TABLE course');
        $this->addSql('DROP TABLE faculty');
        $this->addSql('DROP TABLE person');
        $this->addSql('DROP TABLE student');
        $this->addSql('DROP TABLE student_group');
        $this->addSql('DROP TABLE teacher');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE group_subject_teacher DROP FOREIGN KEY FK_ABE797AE23EDC87');
        $this->addSql('DROP INDEX IDX_FBCE3E7A680CAB68 ON subject');
        $this->addSql('ALTER TABLE subject ADD department_id INT NOT NULL, DROP faculty_id, DROP course_number, DROP is_common_first_year');
        $this->addSql('CREATE INDEX IDX_FBCE3E7AAE80F5DF ON subject (department_id)');
        $this->addSql('ALTER TABLE teacher_subject DROP FOREIGN KEY FK_360CB33B23EDC87');
    }
}
