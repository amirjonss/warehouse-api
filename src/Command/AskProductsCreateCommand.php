<?php

declare(strict_types=1);

namespace App\Command;

use App\Component\Product\Enums\Currency;
use App\Component\Product\Enums\UnitCode;
use App\Entity\Category;
use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Inserts the live margarine catalog: one category and twelve SKUs with both price lists.
 * Stock stays at zero — only a posted receipt fills remainingQty.
 */
#[AsCommand(
    name: 'ask:products:create',
    description: 'Creates the margarine catalog (margarin / evrofood, maselko, milter) if missing',
)]
class AskProductsCreateCommand extends Command
{
    private const CATEGORY_NAME = 'margarin';

    /**
     * name, dealing currency, priceUsd, priceUzs — copied from the live product table.
     *
     * @var array<int, array{0: string, 1: Currency, 2: string, 3: string}>
     */
    private const PRODUCTS = [
        ['evrofood 80/20', Currency::USD, '40.00', '500000.00'],
        ['evrofood 72/20', Currency::USD, '35.00', '430000.00'],
        ['evrofood 60/20', Currency::UZS, '30.00', '370000.00'],
        ['evrofood 40/20', Currency::UZS, '25.00', '310000.00'],
        ['maselko 80/20', Currency::USD, '40.00', '480000.00'],
        ['maselko 72/20', Currency::USD, '35.00', '430000.00'],
        ['maselko 60/20', Currency::UZS, '30.00', '370000.00'],
        ['maselko 40/20', Currency::UZS, '25.00', '300000.00'],
        ['milter 80/20', Currency::USD, '40.00', '480000.00'],
        ['milter 72/20', Currency::USD, '35.00', '420000.00'],
        ['milter 60/20', Currency::UZS, '30.00', '360000.00'],
        ['milter 40/20', Currency::UZS, '25.00', '310000.00'],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CategoryRepository $categoryRepository,
        private ProductRepository $productRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print what would be created without writing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $category = $this->categoryRepository->findOneBy(['name' => self::CATEGORY_NAME]);
        if ($category === null) {
            $io->text(sprintf('Категория «%s»: будет создана', self::CATEGORY_NAME));
            if (!$dryRun) {
                $category = new Category();
                $category->setName(self::CATEGORY_NAME);
                $this->entityManager->persist($category);
                $this->entityManager->flush();
            }
        } else {
            $io->text(sprintf('Категория «%s»: уже есть', self::CATEGORY_NAME));
        }

        $created = 0;
        $skipped = 0;

        foreach (self::PRODUCTS as [$name, $currency, $priceUsd, $priceUzs]) {
            if ($this->productRepository->findOneActiveByName($name) !== null) {
                $io->text(sprintf('  skip  %s', $name));
                $skipped++;
                continue;
            }

            $io->text(sprintf('  create %s (%s %s / %s UZS)', $name, $priceUsd, $currency->value, $priceUzs));
            $created++;

            if ($dryRun || $category === null) {
                continue;
            }

            $product = new Product();
            $product
                ->setName($name)
                ->setCategory($category)
                ->setCurrency($currency)
                ->setUnit(UnitCode::PCS)
                ->setMinStock('10.000')
                ->setPriceUsd($priceUsd)
                ->setPriceUzs($priceUzs)
                ->setIsActive(true)
                ->setRemainingQty('0.000');

            $this->entityManager->persist($product);
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->success(sprintf(
            '%s: создано %d, пропущено %d.',
            $dryRun ? 'Dry-run' : 'Готово',
            $created,
            $skipped
        ));

        return Command::SUCCESS;
    }
}
