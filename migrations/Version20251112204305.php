<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251112204305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lesson DROP FOREIGN KEY FK_F87474F323EDC87');
        $this->addSql('ALTER TABLE lesson DROP FOREIGN KEY FK_F87474F341807E1D');
        $this->addSql('ALTER TABLE lesson DROP FOREIGN KEY FK_F87474F3FE54D947');
        $this->addSql('DROP INDEX IDX_F87474F323EDC87 ON lesson');
        $this->addSql('DROP INDEX IDX_F87474F341807E1D ON lesson');
        $this->addSql('DROP INDEX IDX_F87474F3FE54D947 ON lesson');
        $this->addSql('ALTER TABLE lesson ADD group_subject_teacher_id INT NOT NULL, DROP group_id, DROP subject_id, DROP teacher_id');
        $this->addSql('ALTER TABLE lesson ADD CONSTRAINT FK_F87474F3B5C9DEF7 FOREIGN KEY (group_subject_teacher_id) REFERENCES group_subject_teacher (id)');
        $this->addSql('CREATE INDEX IDX_F87474F3B5C9DEF7 ON lesson (group_subject_teacher_id)');
        $this->addSql('ALTER TABLE student_group DROP FOREIGN KEY FK_E5F73D58680CAB68');
        $this->addSql('ALTER TABLE student_group ADD CONSTRAINT FK_E5F73D58680CAB68 FOREIGN KEY (faculty_id) REFERENCES faculty (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE subject DROP FOREIGN KEY FK_FBCE3E7A680CAB68');
        $this->addSql('ALTER TABLE subject ADD CONSTRAINT FK_FBCE3E7A680CAB68 FOREIGN KEY (faculty_id) REFERENCES faculty (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE teacher DROP FOREIGN KEY FK_B0F6A6D5680CAB68');
        $this->addSql('ALTER TABLE teacher CHANGE faculty_id faculty_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE teacher ADD CONSTRAINT FK_B0F6A6D5680CAB68 FOREIGN KEY (faculty_id) REFERENCES faculty (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE teacher DROP FOREIGN KEY FK_B0F6A6D5680CAB68');
        $this->addSql('ALTER TABLE teacher CHANGE faculty_id faculty_id INT NOT NULL');
        $this->addSql('ALTER TABLE teacher ADD CONSTRAINT FK_B0F6A6D5680CAB68 FOREIGN KEY (faculty_id) REFERENCES faculty (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE subject DROP FOREIGN KEY FK_FBCE3E7A680CAB68');
        $this->addSql('ALTER TABLE subject ADD CONSTRAINT FK_FBCE3E7A680CAB68 FOREIGN KEY (faculty_id) REFERENCES faculty (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE student_group DROP FOREIGN KEY FK_E5F73D58680CAB68');
        $this->addSql('ALTER TABLE student_group ADD CONSTRAINT FK_E5F73D58680CAB68 FOREIGN KEY (faculty_id) REFERENCES faculty (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE lesson DROP FOREIGN KEY FK_F87474F3B5C9DEF7');
        $this->addSql('DROP INDEX IDX_F87474F3B5C9DEF7 ON lesson');
        $this->addSql('ALTER TABLE lesson ADD subject_id INT NOT NULL, ADD teacher_id INT NOT NULL, CHANGE group_subject_teacher_id group_id INT NOT NULL');
        $this->addSql('ALTER TABLE lesson ADD CONSTRAINT FK_F87474F323EDC87 FOREIGN KEY (subject_id) REFERENCES subject (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE lesson ADD CONSTRAINT FK_F87474F341807E1D FOREIGN KEY (teacher_id) REFERENCES teacher (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE lesson ADD CONSTRAINT FK_F87474F3FE54D947 FOREIGN KEY (group_id) REFERENCES student_group (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_F87474F323EDC87 ON lesson (subject_id)');
        $this->addSql('CREATE INDEX IDX_F87474F341807E1D ON lesson (teacher_id)');
        $this->addSql('CREATE INDEX IDX_F87474F3FE54D947 ON lesson (group_id)');
    }
}
