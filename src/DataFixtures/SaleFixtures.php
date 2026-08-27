<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Component\Core\Enums\DocStatus;
use App\Component\Product\Enums\Currency;
use App\Component\Sale\SaleFactory;
use App\Entity\Client;
use App\Entity\Product;
use App\Entity\Sale;
use App\Entity\SaleItem;
use App\Entity\User;
use App\Service\SaleChangeStatusService;
use App\Service\SaleItemAllocationService;
use App\Service\SaleItemValidationService;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Two sales that the tests lean on:
 *  - a POSTED sale for "Test Client 2" (10 x 5.00 USD = 50.00 USD), which leaves an
 *    outstanding USD debt for the payment / allocation / auto-allocation tests;
 *  - a DRAFT sale for "Test Client 3", used by the "draft only" guard tests.
 */
class SaleFixtures extends Fixture implements DependentFixtureInterface
{
    /** Fixed so that tests can create sales that are provably older or newer than these. */
    public const POSTED_SALE_DOC_DATE = '2026-08-10';
    public const DRAFT_SALE_DOC_DATE = '2026-08-12';
    public const POSTED_SALE_TOTAL_USD = '50.00';
    public const POSTED_SALE_QUANTITY = '10.000';
    public const POSTED_SALE_PRICE_USD = '5.00';

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private SaleFactory $saleFactory,
        private SaleItemValidationService $saleItemValidationService,
        private SaleItemAllocationService $saleItemAllocationService,
        private SaleChangeStatusService $saleChangeStatusService,
    ) {
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            ClientFixtures::class,
            ProductFixtures::class,
            StockFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $admin = $this->getReference('user-admin', User::class);
        $this->impersonate($admin);

        $postedSale = $this->saleFactory->create(
            $admin,
            $this->getReference('client-2', Client::class),
            '',
            new DateTime(self::POSTED_SALE_DOC_DATE)
        );
        $manager->persist($postedSale);
        $manager->flush();

        $this->addItem($postedSale, 'product-usd-1', self::POSTED_SALE_QUANTITY, self::POSTED_SALE_PRICE_USD);

        $postedSale->setStatus(DocStatus::POSTED);
        $this->saleChangeStatusService->changeStatus($postedSale);
        $manager->flush();
        $this->addReference('sale-posted', $postedSale);

        $draftSale = $this->saleFactory->create(
            $admin,
            $this->getReference('client-3', Client::class),
            '',
            new DateTime(self::DRAFT_SALE_DOC_DATE)
        );
        $manager->persist($draftSale);
        $manager->flush();
        $this->addReference('sale-draft', $draftSale);

        $this->tokenStorage->setToken(null);
    }

    private function addItem(Sale $sale, string $productReference, string $quantity, string $price): void
    {
        $prototype = new SaleItem();
        $prototype
            ->setSale($sale)
            ->setProduct($this->getReference($productReference, Product::class))
            ->setQuantity($quantity)
            ->setPrice($price)
            ->setCurrency(Currency::USD)
            ->setRate(null);

        $this->saleItemValidationService->validate($prototype);
        $this->saleItemAllocationService->createWithAllocation($prototype);
    }

    /** Factories and services read the acting user from the security token — fake one here. */
    private function impersonate(User $user): void
    {
        $this->tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }
}
