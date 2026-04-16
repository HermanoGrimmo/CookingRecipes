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
        // Passwort wird aus der Umgebungsvariable ADMIN_INITIAL_PASSWORD gelesen
        // und zur Migrationszeit mit bcrypt gehasht. Beispiel:
        //   ADMIN_INITIAL_PASSWORD=geheimesPasswort php bin/console doctrine:migrations:migrate
        if (empty($_ENV['ADMIN_INITIAL_PASSWORD'])) {
            throw new \RuntimeException('Die Umgebungsvariable ADMIN_INITIAL_PASSWORD muss gesetzt sein, bevor diese Migration ausgeführt wird.');
        }

        $plainPassword = (string) $_ENV['ADMIN_INITIAL_PASSWORD'];

        $hashedPassword = password_hash($plainPassword, \PASSWORD_BCRYPT);

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
