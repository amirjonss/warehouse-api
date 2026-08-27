<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Component\User\UserFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class UserFixtures extends Fixture
{
    public const ADMIN_EMAIL = 'admin@example.com';
    public const SALES_EMAIL = 'sales@example.com';
    public const SALES2_EMAIL = 'sales2@example.com';
    public const PASSWORD = 'passwd';

    public function __construct(private UserFactory $userFactory)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = $this->userFactory->create(self::ADMIN_EMAIL, self::PASSWORD, ['ROLE_ADMIN'], 'admin', 'adminov');
        $manager->persist($admin);
        $this->addReference('user-admin', $admin);

        $sales = $this->userFactory->create(self::SALES_EMAIL, self::PASSWORD, ['ROLE_SALES'], 'sales', 'salesov');
        $manager->persist($sales);
        $this->addReference('user-sales', $sales);

        $sales2 = $this->userFactory->create(self::SALES2_EMAIL, self::PASSWORD, ['ROLE_SALES'], 'sales2', 'salesov');
        $manager->persist($sales2);
        $this->addReference('user-sales-2', $sales2);

        $manager->flush();
    }
}
