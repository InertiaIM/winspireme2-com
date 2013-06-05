<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration,
    Doctrine\DBAL\Schema\Schema;

class Version20130605135758 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != "mysql", "Migration can only be executed safely on 'mysql'.");
        
        $this->addSql("CREATE TABLE booking (id INT AUTO_INCREMENT NOT NULL, suitcase_item_id INT DEFAULT NULL, first_name VARCHAR(128) DEFAULT NULL, last_name VARCHAR(128) DEFAULT NULL, email VARCHAR(128) DEFAULT NULL, phone VARCHAR(128) DEFAULT NULL, certificate_id VARCHAR(128) DEFAULT NULL, voucher_sent TINYINT(1) DEFAULT NULL, voucher_sent_at DATETIME DEFAULT NULL, created DATETIME NOT NULL, updated DATETIME NOT NULL, dirty TINYINT(1) DEFAULT NULL, sf_updated DATETIME DEFAULT NULL, sf_id VARCHAR(128) DEFAULT NULL, INDEX IDX_E00CEDDEB1112FD8 (suitcase_item_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB");
        $this->addSql("ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDEB1112FD8 FOREIGN KEY (suitcase_item_id) REFERENCES suitcase_item (id) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE suitcase DROP FOREIGN KEY FK_8348E81342076603");
        $this->addSql("DROP INDEX IDX_8348E81342076603 ON suitcase");
        $this->addSql("ALTER TABLE suitcase ADD status VARCHAR(1) DEFAULT NULL AFTER packed");
        $this->addSql("ALTER TABLE suitcase ADD invoice_requested_at DATETIME DEFAULT NULL AFTER packed_at");
        $this->addSql("ALTER TABLE suitcase ADD invoice_provided_at DATETIME DEFAULT NULL AFTER invoice_requested_at");
        $this->addSql("ALTER TABLE suitcase ADD invoice_paid_at DATETIME DEFAULT NULL AFTER invoice_provided_at");
        $this->addSql("ALTER TABLE suitcase ADD invoice_file_name VARCHAR(256) DEFAULT NULL AFTER invoice_paid_at, ADD sf_role_id VARCHAR(128) DEFAULT NULL AFTER sf_id, DROP salesperson_id");
        $this->addSql("UPDATE suitcase set status = 'U'");
        $this->addSql("UPDATE suitcase set status = 'P' WHERE packed");
        $this->addSql("ALTER TABLE suitcase_item CHANGE total cost NUMERIC(10, 2) NOT NULL");
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != "mysql", "Migration can only be executed safely on 'mysql'.");
        
        $this->addSql("DROP TABLE booking");
        $this->addSql("ALTER TABLE suitcase ADD salesperson_id INT DEFAULT NULL, DROP status, DROP invoice_requested_at, DROP invoice_provided_at, DROP invoice_paid_at, DROP invoice_file_name, DROP sf_role_id");
        $this->addSql("ALTER TABLE suitcase ADD CONSTRAINT FK_8348E81342076603 FOREIGN KEY (salesperson_id) REFERENCES user (id)");
        $this->addSql("CREATE INDEX IDX_8348E81342076603 ON suitcase (salesperson_id)");
        $this->addSql("ALTER TABLE suitcase_item CHANGE cost total NUMERIC(10, 2) NOT NULL");
    }
}
