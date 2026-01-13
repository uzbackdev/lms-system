<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251206145053 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // MySQL uchun - avval maydonlar borligini tekshirish kerak
        // Lekin oddiyroq: faqat login o'zgartirish, qolganlarini skip qilish

        // 1. Login uzunligini o'zgartirish
        $this->addSql('ALTER TABLE person CHANGE login login VARCHAR(8) NOT NULL');

        // 2. Qolgan o'zgarishlarni skip qilish (qo'lda bajaramiz)
        // Chunki IF NOT EXISTS MySQL'da ishlamaydi
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE person CHANGE login login VARCHAR(15) NOT NULL');
    }
}
