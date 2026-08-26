<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Component\Core\Enums\DocStatus;
use App\Component\Product\Enums\Currency;
use App\Component\Receipt\ReceiptFactory;
use App\Component\ReceiptItem\ReceiptItemFactory;
use App\Entity\Product;
use App\Entity\ReceiptItem;
use App\Entity\Supplier;
use App\Entity\User;
use App\Service\ReceiptChangeStatusService;
use App\Service\ReceiptItemValidationService;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Builds the opening stock by going through the very same factories and services the API
 * uses (draft -> items -> post), so batches, stock movements and the denormalized
 * remainingQty caches come out exactly as they would from real usage.
 *
 * The two receipts for "Test Product USD 1" deliberately differ in docDate and price:
 * that fixes a deterministic FIFO order (B-0001 @ 2.00 before B-0002 @ 2.50) which the
 * allocation tests assert on.
 */
class StockFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private ReceiptFactory $receiptFactory,
        private ReceiptItemFactory $receiptItemFactory,
        private ReceiptItemValidationService $receiptItemValidationService,
        private ReceiptChangeStatusService $receiptChangeStatusService,
    ) {
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            SupplierFixtures::class,
            ProductFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $admin = $this->getReference('user-admin', User::class);
        $this->impersonate($admin);

        $this->postReceipt($manager, $admin, 'supplier-1', new DateTime('2026-08-01'), 'receipt-1', [
            ['product-usd-1', '100.000', '2.00', Currency::USD, '12500'],
        ]);

        $this->postReceipt($manager, $admin, 'supplier-1', new DateTime('2026-08-05'), 'receipt-2', [
            ['product-usd-1', '50.000', '2.50', Currency::USD, '12500'],
            ['product-usd-2', '80.000', '4.00', Currency::USD, '12500'],
        ]);

        $this->postReceipt($manager, $admin, 'supplier-2', new DateTime('2026-08-05'), 'receipt-3', [
            ['product-uzs-1', '200.000', '40000.00', Currency::UZS, null],
        ]);

        $this->tokenStorage->setToken(null);
    }

    /**
     * @param array<int, array{0: string, 1: string, 2: string, 3: Currency, 4: string|null}> $items
     */
    private function postReceipt(
        ObjectManager $manager,
        User $actor,
        string $supplierReference,
        DateTime $docDate,
        string $reference,
        array $items,
    ): void {
        $receipt = $this->receiptFactory->create(
            $actor,
            $this->getReference($supplierReference, Supplier::class),
            '',
            $docDate
        );
        $manager->persist($receipt);
        $manager->flush();

        foreach ($items as [$productReference, $quantity, $price, $currency, $rate]) {
            $prototype = new ReceiptItem();
            $prototype
                ->setReceipt($receipt)
                ->setProduct($this->getReference($productReference, Product::class))
                ->setQuantity($quantity)
                ->setPrice($price)
                ->setCurrency($currency)
                ->setRate($rate);

            $this->receiptItemValidationService->validate($prototype);
            $this->receiptItemFactory->create($prototype);
        }

        $receipt->setStatus(DocStatus::POSTED);
        $this->receiptChangeStatusService->changeStatus($receipt);
        $manager->flush();

        $this->addReference($reference, $receipt);
    }

    private function impersonate(User $user): void
    {
        $this->tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }
}
