<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the stocktake document: inventories and inventory_items.
 *
 * Until now the only answer to "is the ledger right" was to re-sum the ledger. A stocktake
 * compares it against the shelf, and the difference has to become a document rather than a
 * silent edit of a remainder.
 *
 * Counting happens per product, because that is the only thing a warehouse can actually count.
 * Batches are a costing device, not a physical arrangement: new stock is stacked behind the old
 * and nobody can tell one layer of the same margarine from another on the shelf. A line is
 * therefore one product, and posting spreads the difference across that product's batches —
 * FIFO from the oldest batch that still has stock for a shortage, all onto that same batch for
 * a surplus, so found goods rejoin the front of the queue at the cost they left it with.
 *
 * A receipt could not do this job: it would invent a batch dated today, push found-but-old goods
 * to the back of the FIFO queue and, because posting a receipt writes to supplier_debts, raise a
 * payable to a supplier who was never involved.
 *
 * actual_qty is nullable on purpose. NULL means "not counted yet", 0 means "counted, the shelf
 * is empty". Collapsing the two would let a half-finished sheet write off every batch it never
 * reached, which is the single most destructive mistake this document can make; posting refuses
 * while any line is still NULL. expected_qty is a snapshot taken when the line is created and is
 * never recomputed — posting applies actual - expected so that goods which legitimately moved
 * during the count are not resurrected.
 */
final class Version20260903100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the inventories and inventory_items tables for stocktaking.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE inventories (
                id SERIAL NOT NULL,
                created_by_id INT NOT NULL,
                category_id INT DEFAULT NULL,
                number VARCHAR(255) NOT NULL,
                doc_date DATE NOT NULL,
                note TEXT DEFAULT NULL,
                status VARCHAR(255) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                posted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_inventories_number ON inventories (number)');
        $this->addSql('CREATE INDEX idx_inventories_created_by ON inventories (created_by_id)');
        $this->addSql('CREATE INDEX idx_inventories_category ON inventories (category_id)');
        $this->addSql('CREATE INDEX idx_inventories_doc_date ON inventories (doc_date)');
        $this->addSql(<<<'SQL'
            ALTER TABLE inventories ADD CONSTRAINT FK_INVENTORY_CREATED_BY FOREIGN KEY (created_by_id)
                REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inventories ADD CONSTRAINT FK_INVENTORY_CATEGORY FOREIGN KEY (category_id)
                REFERENCES categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE inventory_items (
                id SERIAL NOT NULL,
                inventory_id INT NOT NULL,
                product_id INT NOT NULL,
                expected_qty NUMERIC(14, 3) NOT NULL,
                actual_qty NUMERIC(14, 3) DEFAULT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_inventory_items_inventory ON inventory_items (inventory_id)');
        $this->addSql('CREATE INDEX idx_inventory_items_product ON inventory_items (product_id)');
        // Counting the same product twice on one sheet is always a mistake, never a second reading.
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_inventory_items_inventory_product
                ON inventory_items (inventory_id, product_id)
        SQL);
        // A counted figure is absolute, never a delta — a negative one is nonsense on a shelf.
        $this->addSql(<<<'SQL'
            ALTER TABLE inventory_items ADD CONSTRAINT chk_inventory_items_actual_qty_non_negative
                CHECK (actual_qty IS NULL OR actual_qty >= 0)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inventory_items ADD CONSTRAINT chk_inventory_items_expected_qty_non_negative
                CHECK (expected_qty >= 0)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inventory_items ADD CONSTRAINT FK_INVITEM_INVENTORY FOREIGN KEY (inventory_id)
                REFERENCES inventories (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE inventory_items ADD CONSTRAINT FK_INVITEM_PRODUCT FOREIGN KEY (product_id)
                REFERENCES product (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE inventory_items');
        $this->addSql('DROP TABLE inventories');
    }
}
