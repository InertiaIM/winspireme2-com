<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20140626120110 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != "mysql", "Migration can only be executed safely on 'mysql'.");
        
        $this->addSql("CREATE TABLE packages_partners (package_id INT NOT NULL, partner_id INT NOT NULL, INDEX IDX_3EA43A91F44CABFF (package_id), INDEX IDX_3EA43A919393F8FE (partner_id), PRIMARY KEY(package_id, partner_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB");
        $this->addSql("CREATE TABLE partner (id INT AUTO_INCREMENT NOT NULL, subdomain VARCHAR(128) DEFAULT NULL, content LONGTEXT DEFAULT NULL, active TINYINT(1) DEFAULT NULL, sf_id VARCHAR(128) NOT NULL, created DATETIME NOT NULL, updated DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB");
        $this->addSql("ALTER TABLE packages_partners ADD CONSTRAINT FK_3EA43A91F44CABFF FOREIGN KEY (package_id) REFERENCES package (id) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE packages_partners ADD CONSTRAINT FK_3EA43A919393F8FE FOREIGN KEY (partner_id) REFERENCES partner (id) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE package_origin DROP FOREIGN KEY FK_E00A25C2F44CABFF");
        $this->addSql("ALTER TABLE package_origin ADD CONSTRAINT FK_E00A25C2F44CABFF FOREIGN KEY (package_id) REFERENCES package (id) ON DELETE CASCADE");
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != "mysql", "Migration can only be executed safely on 'mysql'.");
        
        $this->addSql("ALTER TABLE packages_partners DROP FOREIGN KEY FK_3EA43A919393F8FE");
        $this->addSql("DROP TABLE packages_partners");
        $this->addSql("DROP TABLE partner");
        $this->addSql("ALTER TABLE package_origin DROP FOREIGN KEY FK_E00A25C2F44CABFF");
        $this->addSql("ALTER TABLE package_origin ADD CONSTRAINT FK_E00A25C2F44CABFF FOREIGN KEY (package_id) REFERENCES package (id)");
    }
}
