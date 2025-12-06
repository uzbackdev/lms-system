<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251206080058 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    // migrations/Version20251206080058.php ni O'ZGARTIRING:
    public function up(Schema $schema): void
    {
        // Attendance jadvali yo'q bo'lsa, DROP TABLE dan oldin tekshirish
        $this->addSql('DROP TABLE IF EXISTS attendance');
        $this->addSql('ALTER TABLE person CHANGE login login VARCHAR(8) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Faqat person jadvalini qaytarish
        $this->addSql('ALTER TABLE person CHANGE login login VARCHAR(15) NOT NULL');
    }
}
