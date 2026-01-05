<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260104175508 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE commande (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, chef_id INT DEFAULT NULL, livreur_id INT DEFAULT NULL, date DATETIME NOT NULL, total NUMERIC(10, 2) NOT NULL, statut VARCHAR(50) NOT NULL, adresse_livraison VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, date_livraison DATETIME DEFAULT NULL, INDEX IDX_6EEAA67DA76ED395 (user_id), INDEX IDX_6EEAA67D150A48F1 (chef_id), INDEX IDX_6EEAA67DF8646701 (livreur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE commande_item (id INT AUTO_INCREMENT NOT NULL, commande_id INT NOT NULL, plat_id INT NOT NULL, quantite INT NOT NULL, prix_unitaire NUMERIC(10, 2) NOT NULL, INDEX IDX_747724FD82EA2E54 (commande_id), INDEX IDX_747724FDD73DB560 (plat_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67D150A48F1 FOREIGN KEY (chef_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67DF8646701 FOREIGN KEY (livreur_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE commande_item ADD CONSTRAINT FK_747724FD82EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id)');
        $this->addSql('ALTER TABLE commande_item ADD CONSTRAINT FK_747724FDD73DB560 FOREIGN KEY (plat_id) REFERENCES plat (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67DA76ED395');
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67D150A48F1');
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67DF8646701');
        $this->addSql('ALTER TABLE commande_item DROP FOREIGN KEY FK_747724FD82EA2E54');
        $this->addSql('ALTER TABLE commande_item DROP FOREIGN KEY FK_747724FDD73DB560');
        $this->addSql('DROP TABLE commande');
        $this->addSql('DROP TABLE commande_item');
    }
}
