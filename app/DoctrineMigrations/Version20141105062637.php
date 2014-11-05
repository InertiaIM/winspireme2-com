<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20141105062637 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != "mysql", "Migration can only be executed safely on 'mysql'.");
        
        $this->addSql("ALTER TABLE category ADD meta_title VARCHAR(256) DEFAULT NULL AFTER slug, ADD meta_description LONGTEXT DEFAULT NULL AFTER meta_title");
        $this->addSql("ALTER TABLE suitcase ADD CONSTRAINT FK_8348E813547A264A FOREIGN KEY (sf_partner_id) REFERENCES account (sf_id)");
        $this->addSql("CREATE INDEX IDX_8348E813547A264A ON suitcase (sf_partner_id)");
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != "mysql", "Migration can only be executed safely on 'mysql'.");
        
        $this->addSql("ALTER TABLE category DROP meta_title, DROP meta_description");
        $this->addSql("ALTER TABLE suitcase DROP FOREIGN KEY FK_8348E813547A264A");
        $this->addSql("DROP INDEX IDX_8348E813547A264A ON suitcase");
    }
}
