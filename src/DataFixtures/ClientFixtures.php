<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Client;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ClientFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        foreach ([1, 2, 3] as $i) {
            $client = new Client();
            $client
                ->setName('Test Client ' . $i)
                ->setContact('Contact ' . $i)
                ->setPhone('+99891000000' . $i)
                ->setAddress('Tashkent, block ' . $i)
                ->setIsActive(true)
                ->setDebtUsd('0.00')
                ->setDebtUzs('0.00');
            $manager->persist($client);
            $this->addReference('client-' . $i, $client);
        }

        $manager->flush();
    }
}
