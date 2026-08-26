<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ExchangeRate;
use App\Entity\User;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ExchangeRateFixtures extends Fixture implements DependentFixtureInterface
{
    public const RATE_BUY = 12500;
    public const RATE_SELL = 12700;

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $rate = new ExchangeRate();
        $rate->setRateBuy(self::RATE_BUY);
        $rate->setRateSell(self::RATE_SELL);
        $rate->setCreatedAt(new DateTime());
        $rate->setCreatedBy($this->getReference('user-admin', User::class));

        $manager->persist($rate);
        $this->addReference('exchange-rate-1', $rate);

        $manager->flush();
    }
}
