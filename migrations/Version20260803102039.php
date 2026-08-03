<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260803102039 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE batches (id SERIAL NOT NULL, product_id INT NOT NULL, supplier_id INT NOT NULL, receipt_id INT NOT NULL, number VARCHAR(255) NOT NULL, received_at DATE NOT NULL, initial_qty NUMERIC(14, 3) NOT NULL, purchase_price NUMERIC(14, 2) NOT NULL, currency VARCHAR(255) NOT NULL, rate_sell NUMERIC(12, 4) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F06E65534584665A ON batches (product_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F06E65532ADD6D8C ON batches (supplier_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F06E65532B5CA896 ON batches (receipt_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_batches_product_number ON batches (product_id, number)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE categories (id SERIAL NOT NULL, slug VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, sort_order INT DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_3AF34668989D9B62 ON categories (slug)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE clients (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, contact VARCHAR(255) DEFAULT NULL, phone VARCHAR(255) DEFAULT NULL, address TEXT DEFAULT NULL, is_active BOOLEAN NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE exchange_rates (rate_date DATE NOT NULL, rate_buy NUMERIC(12, 4) NOT NULL, rate_sell NUMERIC(12, 4) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(rate_date))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE payment_allocations (id SERIAL NOT NULL, payment_id INT NOT NULL, sale_id INT DEFAULT NULL, currency VARCHAR(255) NOT NULL, amount_closed NUMERIC(18, 2) NOT NULL, amount_spent NUMERIC(18, 2) NOT NULL, doc_rate NUMERIC(12, 4) NOT NULL, pay_rate NUMERIC(12, 4) DEFAULT NULL, is_rounding BOOLEAN NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_366592244C3A3BB ON payment_allocations (payment_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_366592244A7E4868 ON payment_allocations (sale_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE payments (id SERIAL NOT NULL, client_id INT NOT NULL, accepted_by_id INT NOT NULL, number VARCHAR(255) NOT NULL, doc_date DATE NOT NULL, posted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, amount NUMERIC(18, 2) NOT NULL, currency VARCHAR(255) NOT NULL, rate_kind VARCHAR(255) DEFAULT NULL, rate NUMERIC(12, 4) DEFAULT NULL, method VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(255) NOT NULL, note TEXT DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_65D29B3296901F54 ON payments (number)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_65D29B3219EB6921 ON payments (client_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_65D29B3220F699D9 ON payments (accepted_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE product (id SERIAL NOT NULL, category_id INT NOT NULL, sku VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, currency VARCHAR(255) NOT NULL, unit VARCHAR(255) NOT NULL, pack_qty NUMERIC(14, 3) NOT NULL, pack_unit VARCHAR(255) NOT NULL, min_stock NUMERIC(14, 3) NOT NULL, purchase_price NUMERIC(14, 2) NOT NULL, sale_price NUMERIC(14, 2) NOT NULL, is_active BOOLEAN NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_D34A04AD12469DE2 ON product (category_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE receipt_items (id SERIAL NOT NULL, receipt_id INT NOT NULL, product_id INT NOT NULL, batch_id INT NOT NULL, quantity NUMERIC(14, 3) NOT NULL, price NUMERIC(14, 2) NOT NULL, currency VARCHAR(255) NOT NULL, rate NUMERIC(12, 4) NOT NULL, total NUMERIC(18, 2) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_5865D7D2B5CA896 ON receipt_items (receipt_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_5865D7D4584665A ON receipt_items (product_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_5865D7DF39EBE7A ON receipt_items (batch_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE receipts (id SERIAL NOT NULL, supplier_id INT NOT NULL, received_by_id INT NOT NULL, number VARCHAR(255) NOT NULL, doc_date DATE NOT NULL, posted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, total_usd NUMERIC(14, 2) NOT NULL, total_uzs NUMERIC(18, 2) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(255) NOT NULL, note TEXT DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_1DEBE3A296901F54 ON receipts (number)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_1DEBE3A22ADD6D8C ON receipts (supplier_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_1DEBE3A26F8DDD17 ON receipts (received_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE sale_items (id SERIAL NOT NULL, sale_id INT NOT NULL, product_id INT NOT NULL, batch_id INT NOT NULL, quantity NUMERIC(14, 3) NOT NULL, price NUMERIC(14, 2) NOT NULL, currency VARCHAR(255) NOT NULL, total NUMERIC(18, 2) NOT NULL, cost_price NUMERIC(14, 2) NOT NULL, cost_currency VARCHAR(255) NOT NULL, cost_rate NUMERIC(12, 4) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_31C2B1CE4A7E4868 ON sale_items (sale_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_31C2B1CE4584665A ON sale_items (product_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_31C2B1CEF39EBE7A ON sale_items (batch_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE sales (id SERIAL NOT NULL, customer_id INT NOT NULL, sold_by_id INT NOT NULL, number VARCHAR(255) NOT NULL, doc_date DATE NOT NULL, posted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, rate NUMERIC(12, 4) NOT NULL, total_usd NUMERIC(14, 2) NOT NULL, total_uzs NUMERIC(18, 2) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(255) NOT NULL, note TEXT DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_6B81704496901F54 ON sales (number)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6B8170449395C3F3 ON sales (customer_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6B817044148EA8A1 ON sales (sold_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE stock_movements (id SERIAL NOT NULL, product_id INT NOT NULL, batch_id INT NOT NULL, created_by_id INT NOT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, type VARCHAR(255) NOT NULL, quantity NUMERIC(14, 3) NOT NULL, doc_type VARCHAR(255) NOT NULL, doc_id INT NOT NULL, doc_number VARCHAR(255) NOT NULL, note TEXT DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A0BE93C94584665A ON stock_movements (product_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A0BE93C9B03A8386 ON stock_movements (created_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_stock_movements_batch ON stock_movements (batch_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_stock_movements_product_occurred ON stock_movements (product_id, occurred_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_stock_movements_doc ON stock_movements (doc_type, doc_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE suppliers (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, contact VARCHAR(255) DEFAULT NULL, phone VARCHAR(255) DEFAULT NULL, address TEXT DEFAULT NULL, is_active BOOLEAN NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE writeoff_items (id SERIAL NOT NULL, writeoff_id INT NOT NULL, product_id INT NOT NULL, batch_id INT NOT NULL, quantity NUMERIC(14, 3) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_EFA32BB527EBE371 ON writeoff_items (writeoff_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_EFA32BB54584665A ON writeoff_items (product_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_EFA32BB5F39EBE7A ON writeoff_items (batch_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE writeoffs (id SERIAL NOT NULL, created_by_id INT NOT NULL, number VARCHAR(255) NOT NULL, doc_date DATE NOT NULL, reason TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(255) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_96452EE396901F54 ON writeoffs (number)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_96452EE3B03A8386 ON writeoffs (created_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE batches ADD CONSTRAINT FK_F06E65534584665A FOREIGN KEY (product_id) REFERENCES product (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE batches ADD CONSTRAINT FK_F06E65532ADD6D8C FOREIGN KEY (supplier_id) REFERENCES suppliers (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE batches ADD CONSTRAINT FK_F06E65532B5CA896 FOREIGN KEY (receipt_id) REFERENCES receipts (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment_allocations ADD CONSTRAINT FK_366592244C3A3BB FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment_allocations ADD CONSTRAINT FK_366592244A7E4868 FOREIGN KEY (sale_id) REFERENCES sales (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payments ADD CONSTRAINT FK_65D29B3219EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payments ADD CONSTRAINT FK_65D29B3220F699D9 FOREIGN KEY (accepted_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product ADD CONSTRAINT FK_D34A04AD12469DE2 FOREIGN KEY (category_id) REFERENCES categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE receipt_items ADD CONSTRAINT FK_5865D7D2B5CA896 FOREIGN KEY (receipt_id) REFERENCES receipts (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE receipt_items ADD CONSTRAINT FK_5865D7D4584665A FOREIGN KEY (product_id) REFERENCES product (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE receipt_items ADD CONSTRAINT FK_5865D7DF39EBE7A FOREIGN KEY (batch_id) REFERENCES batches (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE receipts ADD CONSTRAINT FK_1DEBE3A22ADD6D8C FOREIGN KEY (supplier_id) REFERENCES suppliers (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE receipts ADD CONSTRAINT FK_1DEBE3A26F8DDD17 FOREIGN KEY (received_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sale_items ADD CONSTRAINT FK_31C2B1CE4A7E4868 FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sale_items ADD CONSTRAINT FK_31C2B1CE4584665A FOREIGN KEY (product_id) REFERENCES product (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sale_items ADD CONSTRAINT FK_31C2B1CEF39EBE7A FOREIGN KEY (batch_id) REFERENCES batches (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sales ADD CONSTRAINT FK_6B8170449395C3F3 FOREIGN KEY (customer_id) REFERENCES clients (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sales ADD CONSTRAINT FK_6B817044148EA8A1 FOREIGN KEY (sold_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT FK_A0BE93C94584665A FOREIGN KEY (product_id) REFERENCES product (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT FK_A0BE93C9F39EBE7A FOREIGN KEY (batch_id) REFERENCES batches (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT FK_A0BE93C9B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE writeoff_items ADD CONSTRAINT FK_EFA32BB527EBE371 FOREIGN KEY (writeoff_id) REFERENCES writeoffs (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE writeoff_items ADD CONSTRAINT FK_EFA32BB54584665A FOREIGN KEY (product_id) REFERENCES product (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE writeoff_items ADD CONSTRAINT FK_EFA32BB5F39EBE7A FOREIGN KEY (batch_id) REFERENCES batches (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE writeoffs ADD CONSTRAINT FK_96452EE3B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE batches DROP CONSTRAINT FK_F06E65534584665A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE batches DROP CONSTRAINT FK_F06E65532ADD6D8C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE batches DROP CONSTRAINT FK_F06E65532B5CA896
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment_allocations DROP CONSTRAINT FK_366592244C3A3BB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment_allocations DROP CONSTRAINT FK_366592244A7E4868
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payments DROP CONSTRAINT FK_65D29B3219EB6921
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payments DROP CONSTRAINT FK_65D29B3220F699D9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product DROP CONSTRAINT FK_D34A04AD12469DE2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE receipt_items DROP CONSTRAINT FK_5865D7D2B5CA896
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE receipt_items DROP CONSTRAINT FK_5865D7D4584665A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE receipt_items DROP CONSTRAINT FK_5865D7DF39EBE7A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE receipts DROP CONSTRAINT FK_1DEBE3A22ADD6D8C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE receipts DROP CONSTRAINT FK_1DEBE3A26F8DDD17
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sale_items DROP CONSTRAINT FK_31C2B1CE4A7E4868
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sale_items DROP CONSTRAINT FK_31C2B1CE4584665A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sale_items DROP CONSTRAINT FK_31C2B1CEF39EBE7A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sales DROP CONSTRAINT FK_6B8170449395C3F3
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sales DROP CONSTRAINT FK_6B817044148EA8A1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_movements DROP CONSTRAINT FK_A0BE93C94584665A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_movements DROP CONSTRAINT FK_A0BE93C9F39EBE7A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_movements DROP CONSTRAINT FK_A0BE93C9B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE writeoff_items DROP CONSTRAINT FK_EFA32BB527EBE371
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE writeoff_items DROP CONSTRAINT FK_EFA32BB54584665A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE writeoff_items DROP CONSTRAINT FK_EFA32BB5F39EBE7A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE writeoffs DROP CONSTRAINT FK_96452EE3B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE batches
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE categories
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE clients
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE exchange_rates
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE payment_allocations
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE payments
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE product
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE receipt_items
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE receipts
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE sale_items
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE sales
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE stock_movements
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE suppliers
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE writeoff_items
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE writeoffs
        SQL);
    }
}
