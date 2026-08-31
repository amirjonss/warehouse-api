<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Component\Account\CashAccountCatalog;
use App\Entity\CashAccount;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * The four company accounts. The migration seeds the same list, but fixtures:load
 * purges every table, so without this the test database would have no accounts at all
 * and every handover, session close and non-cash payment would fail.
 */
class CashAccountFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        foreach (CashAccountCatalog::ACCOUNTS as $definition) {
            $account = new CashAccount();
            $account
                ->setKind($definition['kind'])
                ->setCurrency($definition['currency'])
                ->setName($definition['name'])
                ->setBalance('0.00')
                ->setIsActive(true);

            $manager->persist($account);
            $this->addReference($definition['reference'], $account);
        }

        $manager->flush();
    }
}
