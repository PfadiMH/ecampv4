<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260610120000 extends AbstractMigration {
    public function getDescription(): string {
        return 'Add oidcId to profile for generic OpenID Connect login';
    }

    public function up(Schema $schema): void {
        $this->addSql('ALTER TABLE profile ADD oidcId VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void {
        $this->addSql('ALTER TABLE profile DROP oidcId');
    }
}
