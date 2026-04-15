<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Legt den initialen Admin-Benutzer an.
 */
final class Version20260409222000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initialen Admin-Benutzer (f.fuehring@outlook.de) anlegen';
    }

    public function up(Schema $schema): void
    {
        // HINWEIS: Das Standard-Passwort "admin" muss nach dem ersten Deployment
        // umgehend über die Anwendung geändert werden.
        // Passwort wird zur Migrationszeit mit bcrypt gehasht
        $hashedPassword = password_hash('admin', \PASSWORD_BCRYPT);

        $this->addSql(
            'INSERT INTO app_user (email, first_name, last_name, roles, password, created_at)
             VALUES (:email, :firstName, :lastName, :roles, :password, :createdAt)',
            [
                'email' => 'f.fuehring@outlook.de',
                'firstName' => 'Fabian',
                'lastName' => 'Fuehring',
                'roles' => '["ROLE_ADMIN"]',
                'password' => $hashedPassword,
                'createdAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM app_user WHERE email = 'f.fuehring@outlook.de'");
    }
}
