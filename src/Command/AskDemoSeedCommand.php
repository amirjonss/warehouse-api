<?php

declare(strict_types=1);

namespace App\Command;

use App\Component\Core\Enums\DocStatus;
use App\Component\Product\Enums\Currency;
use App\Component\Product\Enums\UnitCode;
use App\Component\Receipt\ReceiptFactory;
use App\Component\ReceiptItem\ReceiptItemFactory;
use App\Component\Sale\SaleFactory;
use App\Component\Writeoff\WriteoffFactory;
use App\Component\WriteoffItem\WriteoffItemFactory;
use App\Entity\Category;
use App\Entity\Client;
use App\Entity\Product;
use App\Entity\Receipt;
use App\Entity\ReceiptItem;
use App\Entity\Sale;
use App\Entity\SaleItem;
use App\Entity\Supplier;
use App\Entity\User;
use App\Entity\WriteoffItem;
use App\Repository\BatchRepository;
use App\Repository\StockMovementRepository;
use App\Repository\UserRepository;
use App\Service\ReceiptItemValidationService;
use App\Service\SaleChangeStatusService;
use App\Service\SaleItemAllocationService;
use App\Service\SaleItemValidationService;
use App\Service\WriteoffChangeStatusService;
use App\Service\WriteoffItemValidationService;
use App\Service\ReceiptChangeStatusService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Fills the database with realistic demo data for a wholesale margarine/dairy/oil
 * trading business — goes through the same factories/services as the real API
 * (draft -> items -> post), so batches, FIFO allocation, StockMovement/Profit/Debt
 * ledgers all come out exactly as they would from real usage. No Payments are
 * generated (out of scope), so every posted Sale stays fully outstanding as debt.
 */
#[AsCommand(
    name: 'ask:demo:seed',
    description: 'Seeds realistic demo data (categories/products/clients/suppliers/receipts/sales/writeoffs)',
)]
class AskDemoSeedCommand extends Command
{
    private const CATEGORY_DEFS = [
        ['name' => 'Маргарин для выпечки', 'currency' => Currency::USD,
            'variants' => ['82% ведро 5кг', '82% ведро 10кг', '80% ведро 10кг', '72% ведро 10кг', '72% ведро 20кг', '82% брикет 1кг', '80% брикет 1кг', 'Люкс 84% ведро 10кг', 'Стандарт 72% ведро 20кг', 'Эконом 60% ведро 10кг'],
            'priceMin' => 1.5, 'priceMax' => 2.6, 'minStock' => [50, 300]],
        ['name' => 'Маргарин кусковой', 'currency' => Currency::USD,
            'variants' => ['82% брикет 200г', '82% брикет 400г', '72% брикет 200г', 'Крестьянский 400г', 'Сливочный вкус 200г', 'Молочный 400г', 'Люкс 250г', 'Экстра 200г', 'Домашний 400г', 'Праздничный 250г'],
            'priceMin' => 1.7, 'priceMax' => 2.8, 'minStock' => [30, 150]],
        ['name' => 'Маргарин в вёдрах', 'currency' => Currency::USD,
            'variants' => ['для слоёного теста 10кг', 'для песочного теста 10кг', 'для крема 10кг', 'кондитерский 15кг', 'кондитерский 20кг', 'для вафель 10кг', 'жаропрочный 10кг', 'для пряников 10кг', 'универсальный 15кг', 'профессиональный 20кг'],
            'priceMin' => 1.8, 'priceMax' => 3.0, 'minStock' => [40, 200]],
        ['name' => 'Маргарин премиум', 'currency' => Currency::USD,
            'variants' => ['Voliy 82%', 'Alpen Gold 82%', 'Золотой стандарт 84%', 'Деревенский 80%', 'Фермерский 82%', 'Nordica 82%', 'Baltic 80%', 'Milk Line 72%', 'Cream Style 82%', 'Gold Line 84%'],
            'priceMin' => 2.0, 'priceMax' => 3.4, 'minStock' => [20, 120]],
        ['name' => 'Молоко сгущённое ГОСТ', 'currency' => Currency::USD,
            'variants' => ['8.5% ж/б 380г', '8.5% ж/б 400г', '8.5% дой-пак 300г', '8.5% ведро 5кг', '8.5% ведро 10кг', '9% ж/б 380г', '9% ведро 5кг', '7.5% ж/б 380г', '8.5% туба 300г', '8.5% ведро 20кг'],
            'priceMin' => 1.2, 'priceMax' => 2.1, 'minStock' => [30, 200]],
        ['name' => 'Молоко сгущённое варёное', 'currency' => Currency::USD,
            'variants' => ['ж/б 380г', 'ж/б 400г', 'дой-пак 300г', 'ведро 5кг', 'ведро 10кг', 'карамель ж/б 380г', 'с какао ж/б 380г', 'ведро 20кг', 'туба 300г', 'классическое ж/б 400г'],
            'priceMin' => 1.3, 'priceMax' => 2.2, 'minStock' => [20, 150]],
        ['name' => 'Сливки сгущённые', 'currency' => Currency::USD,
            'variants' => ['19% ж/б 380г', '19% ведро 5кг', '10% ж/б 380г', '10% ведро 5кг', '19% ведро 10кг', 'ванильные ж/б 380г', '19% дой-пак 300г', '10% дой-пак 300г', '19% ведро 20кг', 'классические ж/б 400г'],
            'priceMin' => 1.4, 'priceMax' => 2.3, 'minStock' => [15, 100]],
        ['name' => 'Молоко сухое', 'currency' => Currency::USD,
            'variants' => ['26% мешок 25кг', '26% мешок 10кг', '1.5% мешок 25кг', 'цельное мешок 25кг', 'обезжиренное мешок 25кг', '26% пакет 1кг', '15% мешок 25кг', 'цельное мешок 10кг', '26% мешок 20кг', 'обезжиренное мешок 20кг'],
            'priceMin' => 2.5, 'priceMax' => 4.0, 'minStock' => [20, 150]],
        ['name' => 'Начинка фруктовая', 'currency' => Currency::USD,
            'variants' => ['Вишня ведро 10кг', 'Абрикос ведро 10кг', 'Яблоко ведро 10кг', 'Клубника ведро 10кг', 'Персик ведро 10кг', 'Малина ведро 10кг', 'Слива ведро 10кг', 'Черника ведро 10кг', 'Курага ведро 10кг', 'Апельсин ведро 10кг'],
            'priceMin' => 2.0, 'priceMax' => 4.5, 'minStock' => [20, 120]],
        ['name' => 'Начинка шоколадная', 'currency' => Currency::USD,
            'variants' => ['Шоколадная ведро 10кг', 'Какао-ореховая ведро 10кг', 'Тёмный шоколад ведро 10кг', 'Молочный шоколад ведро 10кг', 'Шоколад-вишня ведро 10кг', 'Белый шоколад ведро 10кг', 'Шоколад-апельсин ведро 10кг', 'Пралине ведро 10кг', 'Трюфель ведро 10кг', 'Брауни ведро 10кг'],
            'priceMin' => 2.5, 'priceMax' => 5.0, 'minStock' => [15, 100]],
        ['name' => 'Начинка ореховая', 'currency' => Currency::USD,
            'variants' => ['Фундук ведро 10кг', 'Арахис ведро 10кг', 'Грецкий орех ведро 10кг', 'Миндаль ведро 10кг', 'Мак ведро 10кг', 'Кунжут ведро 10кг', 'Фисташка ведро 10кг', 'Кешью ведро 10кг', 'Смесь орехов ведро 10кг', 'Кокос ведро 10кг'],
            'priceMin' => 3.0, 'priceMax' => 6.0, 'minStock' => [10, 80]],
        ['name' => 'Масло подсолнечное рафинированное', 'currency' => Currency::UZS,
            'variants' => ['дезодорированное 1л', 'дезодорированное 5л', 'дезодорированное 10л', 'дезодорированное 20л', 'премиум 1л', 'премиум 5л', 'для фритюра 20л', 'для фритюра 10л', 'экстра 1л', 'экстра 5л'],
            'priceMin' => 11000, 'priceMax' => 17000, 'minStock' => [50, 300]],
        ['name' => 'Масло подсолнечное нерафинированное', 'currency' => Currency::UZS,
            'variants' => ['холодного отжима 1л', 'холодного отжима 5л', 'ароматное 1л', 'ароматное 5л', 'деревенское 1л', 'деревенское 10л', 'фермерское 5л', 'традиционное 1л', 'традиционное 10л', 'домашнее 5л'],
            'priceMin' => 12000, 'priceMax' => 18000, 'minStock' => [30, 200]],
        ['name' => 'Масло подсолнечное фасованное', 'currency' => Currency::UZS,
            'variants' => ['ПЭТ 0.5л', 'ПЭТ 1л', 'ПЭТ 2л', 'стекло 1л', 'канистра 3л', 'канистра 5л', 'ПЭТ 1.8л', 'канистра 10л', 'дой-пак 1л', 'ПЭТ 3л'],
            'priceMin' => 6000, 'priceMax' => 32000, 'minStock' => [40, 250]],
        ['name' => 'Майонез Провансаль', 'currency' => Currency::UZS,
            'variants' => ['67% дой-пак 200г', '67% дой-пак 400г', '67% ведро 1кг', '67% ведро 5кг', '67% ведро 10кг', '67% стакан 200г', '72% дой-пак 400г', '67% ведро 20кг', '67% канистра 5кг', '72% ведро 10кг'],
            'priceMin' => 9000, 'priceMax' => 19000, 'minStock' => [40, 250]],
        ['name' => 'Майонез лёгкий', 'currency' => Currency::UZS,
            'variants' => ['30% дой-пак 200г', '30% дой-пак 400г', '40% дой-пак 400г', '30% ведро 1кг', '30% ведро 5кг', '50% дой-пак 400г', '40% ведро 5кг', '30% стакан 200г', '40% ведро 1кг', '50% ведро 5кг'],
            'priceMin' => 8000, 'priceMax' => 16000, 'minStock' => [20, 150]],
        ['name' => 'Майонез в вёдрах', 'currency' => Currency::UZS,
            'variants' => ['67% ведро 10кг', '67% ведро 15кг', '67% ведро 20кг', 'для фастфуда 10кг', 'для фастфуда 20кг', 'кетчуп-майонез 10кг', 'чесночный 10кг', 'острый 10кг', 'оливковый 10кг', 'классический 20кг'],
            'priceMin' => 9500, 'priceMax' => 18000, 'minStock' => [15, 100]],
        ['name' => 'Какао-порошок', 'currency' => Currency::USD,
            'variants' => ['10-12% мешок 25кг', '10-12% пакет 1кг', '10-12% пакет 5кг', '20-22% мешок 25кг', '20-22% пакет 1кг', 'натуральный мешок 25кг', 'натуральный пакет 1кг', '8-10% мешок 25кг', '8-10% пакет 1кг', '10-12% мешок 10кг'],
            'priceMin' => 3.0, 'priceMax' => 6.5, 'minStock' => [10, 100]],
        ['name' => 'Какао алкализованное', 'currency' => Currency::USD,
            'variants' => ['10-12% тёмное мешок 25кг', '10-12% тёмное пакет 1кг', 'красное мешок 25кг', 'красное пакет 1кг', 'чёрное мешок 25кг', 'чёрное пакет 1кг', '20-22% тёмное мешок 25кг', '20-22% тёмное пакет 1кг', 'экстра-тёмное мешок 25кг', 'экстра-тёмное пакет 1кг'],
            'priceMin' => 3.5, 'priceMax' => 7.0, 'minStock' => [10, 80]],
        ['name' => 'Какао-масло', 'currency' => Currency::USD,
            'variants' => ['натуральное блок 1кг', 'натуральное блок 5кг', 'дезодорированное блок 1кг', 'дезодорированное блок 5кг', 'прессованное блок 1кг', 'прессованное мешок 25кг', 'рафинированное блок 5кг', 'капли 1кг', 'капли 5кг', 'блок 25кг'],
            'priceMin' => 6.0, 'priceMax' => 12.0, 'minStock' => [5, 50]],
    ];

    private const SUPPLIER_LEGAL_FORMS = ['ООО', 'ЧП', 'АК', 'СП'];
    private const SUPPLIER_CITIES = ['Тошкент', 'Самарқанд', 'Андижон', 'Фарғона', 'Наманган', 'Бухоро', 'Хива', 'Қарши', 'Нукус', 'Жиззах', 'Термиз', 'Гулистон'];
    private const SUPPLIER_WORDS = ['Ёғ-Мой', 'Сут Маҳсулотлари', 'Канд-Ширинлик', 'Дон Маҳсулотлари', 'Агро Импэкс', 'Файз Трейд', 'Насаф Гуруҳи', 'Мой Экспорт', 'Захира Савдо', 'Ипак Йўли Логистика', 'Работешик Плюс', 'Ғалла Маҳсулотлари'];
    private const SUPPLIER_SUFFIXES = ['', ' Плюс', ' Гуруҳи', ' Трейд', ' Импэкс', ' Логистика', ' Сервис'];

    private const CLIENT_TYPES = ['Кондитерская', 'Пекарня', 'Кафе', 'Ресторан', 'Супермаркет', 'Магазин', 'Столовая', 'Мини-маркет', 'Продмаг', 'Точка общепита'];
    private const CLIENT_NAMES = ['Дилноза', 'Азиз', 'Санжар', 'Мадина', 'Шахноза', 'Отабек', 'Нодира', 'Жасур', 'Гулноза', 'Бахтиёр', 'Фарход', 'Зарина', 'Нигора', 'Хуршид', 'Малика', 'Тимур', 'Севара', 'Улуғбек', 'Феруза', 'Шерзод', 'Наргиза', 'Диёр', 'Камола', 'Рустам', 'Ойгуль', 'Бекзод', 'Дилдора', 'Акмал', 'Гулбахор', 'Элёр'];

    private const WRITEOFF_REASONS = ['Истёк срок годности', 'Повреждение упаковки при разгрузке', 'Порча при хранении', 'Пересортица/недостача', 'Брак при отгрузке'];

    /** @var array<int, string> spl_object_id(Product) => base purchase price, see seedProducts()/addReceiptItem() */
    private array $basePriceByProductObjectId = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TokenStorageInterface $tokenStorage,
        private UserRepository $userRepository,
        private ReceiptFactory $receiptFactory,
        private ReceiptItemFactory $receiptItemFactory,
        private ReceiptItemValidationService $receiptItemValidationService,
        private ReceiptChangeStatusService $receiptChangeStatusService,
        private WriteoffFactory $writeoffFactory,
        private WriteoffItemFactory $writeoffItemFactory,
        private WriteoffItemValidationService $writeoffItemValidationService,
        private WriteoffChangeStatusService $writeoffChangeStatusService,
        private SaleFactory $saleFactory,
        private SaleItemValidationService $saleItemValidationService,
        private SaleItemAllocationService $saleItemAllocationService,
        private SaleChangeStatusService $saleChangeStatusService,
        private BatchRepository $batchRepository,
        private StockMovementRepository $stockMovementRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('categories', null, InputOption::VALUE_REQUIRED, 'How many categories', 20)
            ->addOption('products', null, InputOption::VALUE_REQUIRED, 'How many products', 200)
            ->addOption('clients', null, InputOption::VALUE_REQUIRED, 'How many clients', 100)
            ->addOption('suppliers', null, InputOption::VALUE_REQUIRED, 'How many suppliers', 50)
            ->addOption('receipts', null, InputOption::VALUE_REQUIRED, 'How many receipts', 100)
            ->addOption('sales', null, InputOption::VALUE_REQUIRED, 'How many sales', 100)
            ->addOption('writeoffs', null, InputOption::VALUE_REQUIRED, 'How many writeoffs', 60)
            ->addOption('months', null, InputOption::VALUE_REQUIRED, 'How many months back documents are spread over', 5);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $counts = [
            'categories' => (int) $input->getOption('categories'),
            'products' => (int) $input->getOption('products'),
            'clients' => (int) $input->getOption('clients'),
            'suppliers' => (int) $input->getOption('suppliers'),
            'receipts' => (int) $input->getOption('receipts'),
            'sales' => (int) $input->getOption('sales'),
            'writeoffs' => (int) $input->getOption('writeoffs'),
        ];
        $months = (int) $input->getOption('months');

        $actor = $this->findFirstAdmin();
        if ($actor === null) {
            $io->error('No ROLE_ADMIN user found. Create one first with php bin/console ask:users:create.');
            return Command::FAILURE;
        }

        $io->title('Демо-данные склада');
        $io->table(['Что', 'Сколько'], array_map(fn ($k, $v) => [$k, $v], array_keys($counts), $counts));
        $io->text(sprintf('От имени: %s. Документы будут проведены (posted), даты — за последние %d мес.', $actor->getEmail(), $months));

        if (!$io->confirm('Начать генерацию?', true)) {
            return Command::SUCCESS;
        }

        $this->impersonate($actor);

        $windowEnd = new DateTime();
        $windowStart = (clone $windowEnd)->modify("-{$months} months");

        $io->section('Категории');
        $categories = $this->seedCategories($io, $counts['categories']);

        $io->section('Товары');
        $products = $this->seedProducts($io, $categories, $counts['products']);

        $io->section('Клиенты');
        $clients = $this->seedClients($io, $counts['clients']);

        $io->section('Поставщики');
        $suppliers = $this->seedSuppliers($io, $counts['suppliers']);

        $io->section('Приходы');
        $this->seedReceipts($io, $actor, $suppliers, $products, $counts['receipts'], $windowStart, $windowEnd);

        $io->section('Продажи');
        $remainingByProduct = $this->stockMovementRepository->getRemainingQtyByProduct();
        $this->seedSales($io, $actor, $clients, $products, $counts['sales'], $windowStart, $windowEnd, $remainingByProduct);

        $io->section('Списания');
        $this->seedWriteoffs($io, $actor, $counts['writeoffs'], $windowStart, $windowEnd);

        $io->success('Готово.');

        return Command::SUCCESS;
    }

    private function findFirstAdmin(): ?User
    {
        foreach ($this->userRepository->findAll() as $user) {
            if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                return $user;
            }
        }

        return null;
    }

    /** Providers/services read the acting user from the Security token — fake one for this CLI process. */
    private function impersonate(User $user): void
    {
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $this->tokenStorage->setToken($token);
    }

    /**
     * @return Category[]
     */
    private function seedCategories(SymfonyStyle $io, int $count): array
    {
        $categories = [];
        $defs = self::CATEGORY_DEFS;
        $count = min($count, count($defs));

        // slug и порядок сортировки остались только ключами внутри CATEGORY_DEFS —
        // в самой сущности этих полей больше нет.
        foreach (array_slice($defs, 0, $count) as $def) {
            $category = new Category();
            $category->setName($def['name']);
            $this->entityManager->persist($category);
            $categories[] = $category;
        }
        $this->entityManager->flush();

        $io->text(sprintf('Создано категорий: %d', count($categories)));

        return $categories;
    }

    /**
     * @param Category[] $categories
     * @return Product[]
     */
    private function seedProducts(SymfonyStyle $io, array $categories, int $count): array
    {
        $products = [];
        $perCategory = (int) ceil($count / max(1, count($categories)));

        foreach ($categories as $category) {
            $def = $this->categoryDefFor($category);
            if ($def === null) {
                continue;
            }

            $variants = $def['variants'];
            for ($i = 0; $i < $perCategory && count($products) < $count; $i++) {
                $variant = $variants[$i % count($variants)];
                $suffix = $i >= count($variants) ? ' ' . (intdiv($i, count($variants)) + 1) : '';

                $baseUnit = str_starts_with($def['slug'], 'oil') ? UnitCode::L : UnitCode::KG;
                $purchasePrice = $this->randomPrice($def['priceMin'], $def['priceMax'], $def['currency']);

                $product = new Product();
                $product
                    ->setName(trim($def['name'] . ' ' . $variant . $suffix))
                    ->setCategory($category)
                    ->setCurrency($def['currency'])
                    ->setUnit($baseUnit)
                    ->setMinStock((string) random_int($def['minStock'][0], $def['minStock'][1]) . '.000')
                    ->setIsActive(true);

                $this->basePriceByProductObjectId[spl_object_id($product)] = $purchasePrice;

                $salePrice = $this->markup($purchasePrice, $def['currency']);
                if ($def['currency'] === Currency::USD) {
                    $product->setPriceUsd($salePrice);
                } else {
                    $product->setPriceUzs($salePrice);
                }

                $this->entityManager->persist($product);
                $products[] = $product;
            }
        }
        $this->entityManager->flush();

        $io->text(sprintf('Создано товаров: %d', count($products)));

        return $products;
    }

    private function categoryDefFor(Category $category): ?array
    {
        foreach (self::CATEGORY_DEFS as $def) {
            // Раньше сопоставляли по slug; поля больше нет, а имена в CATEGORY_DEFS уникальны.
            if ($def['name'] === $category->getName()) {
                return $def;
            }
        }

        return null;
    }

    /**
     * @return Client[]
     */
    private function seedClients(SymfonyStyle $io, int $count): array
    {
        $clients = [];
        $used = [];

        while (count($clients) < $count) {
            $type = $this->randomFrom(self::CLIENT_TYPES);
            $name = $this->randomFrom(self::CLIENT_NAMES);
            $label = sprintf('%s «%s»', $type, $name);
            if (isset($used[$label])) {
                continue;
            }
            $used[$label] = true;

            $client = new Client();
            $client
                ->setName($label)
                ->setContact($name)
                ->setPhone($this->randomPhone())
                ->setAddress(sprintf('%s, ул. %s %d', $this->randomFrom(self::SUPPLIER_CITIES), $this->randomFrom(['Мустақиллик', 'Амир Темур', 'Бунёдкор', 'Чорсу', 'Юнусобод']), random_int(1, 90)))
                ->setIsActive(true);
            $this->entityManager->persist($client);
            $clients[] = $client;
        }
        $this->entityManager->flush();

        $io->text(sprintf('Создано клиентов: %d', count($clients)));

        return $clients;
    }

    /**
     * @return Supplier[]
     */
    private function seedSuppliers(SymfonyStyle $io, int $count): array
    {
        $suppliers = [];
        $used = [];

        while (count($suppliers) < $count) {
            $label = sprintf(
                '%s «%s %s»%s',
                $this->randomFrom(self::SUPPLIER_LEGAL_FORMS),
                $this->randomFrom(self::SUPPLIER_CITIES),
                $this->randomFrom(self::SUPPLIER_WORDS),
                $this->randomFrom(self::SUPPLIER_SUFFIXES)
            );
            if (isset($used[$label])) {
                continue;
            }
            $used[$label] = true;

            $supplier = new Supplier();
            $supplier
                ->setName($label)
                ->setContact($this->randomFrom(self::CLIENT_NAMES))
                ->setPhone($this->randomPhone())
                ->setAddress(sprintf('%s, промзона', $this->randomFrom(self::SUPPLIER_CITIES)))
                ->setIsActive(true);
            $this->entityManager->persist($supplier);
            $suppliers[] = $supplier;
        }
        $this->entityManager->flush();

        $io->text(sprintf('Создано поставщиков: %d', count($suppliers)));

        return $suppliers;
    }

    /**
     * @param Supplier[] $suppliers
     * @param Product[] $products
     */
    private function seedReceipts(
        SymfonyStyle $io,
        User $actor,
        array $suppliers,
        array $products,
        int $count,
        DateTime $windowStart,
        DateTime $windowEnd,
    ): void {
        $progress = $io->createProgressBar($count);

        for ($i = 0; $i < $count; $i++) {
            $docDate = $this->randomDate($windowStart, $windowEnd);
            $supplier = $this->randomFrom($suppliers);

            $receipt = $this->receiptFactory->create($actor, $supplier, '', $docDate);
            $this->entityManager->persist($receipt);
            $this->entityManager->flush();

            $lineProducts = $this->randomSample($products, random_int(1, 5));
            foreach ($lineProducts as $product) {
                $this->addReceiptItem($receipt, $product, $docDate, $windowStart, $windowEnd);
            }

            $receipt->setStatus(DocStatus::POSTED);
            $this->receiptChangeStatusService->changeStatus($receipt);

            $progress->advance();
        }
        $progress->finish();
        $io->newLine(2);
        $io->text(sprintf('Проведено приходов: %d', $count));
    }

    private function addReceiptItem(Receipt $receipt, Product $product, DateTime $docDate, DateTime $windowStart, DateTime $windowEnd): void
    {
        $currency = $product->getCurrency();
        $rate = $currency === Currency::UZS ? '1' : $this->rateForDate($docDate, $windowStart, $windowEnd);
        $price = $this->jitter($this->basePriceByProductObjectId[spl_object_id($product)], $currency === Currency::USD ? 2 : 0);
        $qty = (string) random_int(20, 300) . '.000';

        $prototype = new ReceiptItem();
        $prototype
            ->setReceipt($receipt)
            ->setProduct($product)
            ->setQuantity($qty)
            ->setPrice($price)
            ->setCurrency($currency)
            ->setRate($rate);

        $this->receiptItemValidationService->validate($prototype);
        $this->receiptItemFactory->create($prototype);
    }

    /**
     * @param Client[] $clients
     * @param Product[] $products
     * @param array<int, string> $remainingByProduct product id => remaining qty, mutated as sales consume stock
     */
    private function seedSales(
        SymfonyStyle $io,
        User $actor,
        array $clients,
        array $products,
        int $count,
        DateTime $windowStart,
        DateTime $windowEnd,
        array &$remainingByProduct,
    ): void {
        $progress = $io->createProgressBar($count);
        $created = 0;
        $attempts = 0;
        $maxAttempts = $count * 5;

        while ($created < $count && $attempts < $maxAttempts) {
            $attempts++;
            $docDate = $this->randomDate($windowStart, $windowEnd);
            $client = $this->randomFrom($clients);

            $available = array_filter($products, fn (Product $p) => bccomp($remainingByProduct[$p->getId()] ?? '0', '1', 3) > 0);
            if ($available === []) {
                break;
            }
            $lineProducts = $this->randomSample(array_values($available), min(random_int(1, 4), count($available)));

            $sale = $this->saleFactory->create($actor, $client, '', $docDate);
            $this->entityManager->persist($sale);
            $this->entityManager->flush();

            $itemsAdded = 0;
            foreach ($lineProducts as $product) {
                $remaining = $remainingByProduct[$product->getId()] ?? '0';
                if (bccomp($remaining, '1', 3) <= 0) {
                    continue;
                }
                $maxQty = (int) min(50, (float) $remaining);
                if ($maxQty < 1) {
                    continue;
                }
                $qty = (string) random_int(1, $maxQty) . '.000';

                if ($this->addSaleItem($sale, $product, $qty, $docDate, $windowStart, $windowEnd)) {
                    $remainingByProduct[$product->getId()] = bcsub($remaining, $qty, 3);
                    $itemsAdded++;
                }
            }

            if ($itemsAdded === 0) {
                // ничего не влезло — черновик без позиций провести нельзя, удаляем и пробуем ещё раз
                $this->entityManager->remove($sale);
                $this->entityManager->flush();
                continue;
            }

            $sale->setStatus(DocStatus::POSTED);
            $this->saleChangeStatusService->changeStatus($sale);

            $created++;
            $progress->advance();
        }
        $progress->finish();
        $io->newLine(2);
        $io->text(sprintf('Проведено продаж: %d', $created));
    }

    private function addSaleItem(Sale $sale, Product $product, string $qty, DateTime $docDate, DateTime $windowStart, DateTime $windowEnd): bool
    {
        $currency = $product->getCurrency();
        $rate = $this->rateForDate($docDate, $windowStart, $windowEnd);
        $basePrice = $currency === Currency::USD ? $product->getPriceUsd() : $product->getPriceUzs();
        $price = $this->jitter($basePrice ?? '1', $currency === Currency::USD ? 2 : 0);

        $prototype = new SaleItem();
        $prototype
            ->setSale($sale)
            ->setProduct($product)
            ->setQuantity($qty)
            ->setPrice($price)
            ->setCurrency($currency)
            ->setRate($rate);

        try {
            $this->saleItemValidationService->validate($prototype);
            $this->saleItemAllocationService->createWithAllocation($prototype);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function seedWriteoffs(SymfonyStyle $io, User $actor, int $count, DateTime $windowStart, DateTime $windowEnd): void
    {
        $remainingByBatch = $this->remainingQtyByBatch();
        $batchIds = array_keys(array_filter($remainingByBatch, fn ($qty) => bccomp($qty, '1', 3) > 0));

        if ($batchIds === []) {
            $io->text('Нет партий с остатком — списания пропущены.');
            return;
        }

        $progress = $io->createProgressBar(min($count, count($batchIds) * 3));
        $created = 0;
        $attempts = 0;
        $maxAttempts = $count * 5;

        while ($created < $count && $attempts < $maxAttempts && $batchIds !== []) {
            $attempts++;
            $batchId = $this->randomFrom($batchIds);
            $batch = $this->batchRepository->find($batchId);
            if ($batch === null) {
                continue;
            }
            $remaining = $remainingByBatch[$batchId] ?? '0';
            $maxQty = (int) min(20, (float) $remaining);
            if ($maxQty < 1) {
                continue;
            }
            $qty = (string) random_int(1, $maxQty) . '.000';
            $docDate = $this->randomDate($windowStart, $windowEnd);

            $writeoff = $this->writeoffFactory->create($actor, $this->randomFrom(self::WRITEOFF_REASONS), $docDate);
            $this->entityManager->persist($writeoff);
            $this->entityManager->flush();

            $prototype = new WriteoffItem();
            $prototype
                ->setWriteoff($writeoff)
                ->setProduct($batch->getProduct())
                ->setBatch($batch)
                ->setQuantity($qty);

            try {
                $this->writeoffItemValidationService->validate($prototype);
                $item = $this->writeoffItemFactory->create($prototype);
                $this->entityManager->persist($item);
                $this->entityManager->flush();
            } catch (\Throwable) {
                $this->entityManager->remove($writeoff);
                $this->entityManager->flush();
                continue;
            }

            $writeoff->setStatus(DocStatus::POSTED);
            $this->writeoffChangeStatusService->changeStatus($writeoff);

            $remainingByBatch[$batchId] = bcsub($remaining, $qty, 3);
            if (bccomp($remainingByBatch[$batchId], '1', 3) <= 0) {
                $batchIds = array_values(array_diff($batchIds, [$batchId]));
            }

            $created++;
            $progress->advance();
        }
        $progress->finish();
        $io->newLine(2);
        $io->text(sprintf('Проведено списаний: %d', $created));
    }

    /**
     * @return array<int, string> batch id => remaining qty
     */
    private function remainingQtyByBatch(): array
    {
        $rows = $this->entityManager->createQuery(
            'SELECT IDENTITY(sm.batch) AS batchId, SUM(sm.quantity) AS remaining
             FROM App\Entity\StockMovement sm
             GROUP BY sm.batch'
        )->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['batchId']] = (string) $row['remaining'];
        }

        return $result;
    }

    private function rateForDate(DateTime $date, DateTime $windowStart, DateTime $windowEnd): string
    {
        $span = max(1, $windowStart->diff($windowEnd)->days);
        $elapsed = min($span, max(0, $windowStart->diff($date)->days));
        $progress = $elapsed / $span;
        $rate = 12200 + (12900 - 12200) * $progress + random_int(-40, 40);

        return number_format(round($rate / 10) * 10, 4, '.', '');
    }

    private function randomPrice(float $min, float $max, Currency $currency): string
    {
        $value = $min + mt_rand() / mt_getrandmax() * ($max - $min);

        return $currency === Currency::USD ? number_format($value, 2, '.', '') : number_format(round($value / 500) * 500, 2, '.', '');
    }

    private function markup(string $purchasePrice, Currency $currency): string
    {
        $value = (float) $purchasePrice * (1 + random_int(15, 35) / 100);

        return $currency === Currency::USD ? number_format($value, 2, '.', '') : number_format(round($value / 500) * 500, 2, '.', '');
    }

    private function jitter(string $value, int $decimals): string
    {
        $factor = 1 + random_int(-5, 5) / 100;
        $result = (float) $value * $factor;

        return $decimals === 2 ? number_format($result, 2, '.', '') : (string) (round($result / 500) * 500);
    }

    private function randomDate(DateTime $from, DateTime $to): DateTime
    {
        $ts = random_int($from->getTimestamp(), $to->getTimestamp());

        return (new DateTime())->setTimestamp($ts);
    }

    private function randomPhone(): string
    {
        return sprintf('+998%02d%03d%02d%02d', random_int(90, 99), random_int(0, 999), random_int(0, 99), random_int(0, 99));
    }

    private function randomFrom(array $items): mixed
    {
        return $items[array_rand($items)];
    }

    /**
     * @return array<int, mixed>
     */
    private function randomSample(array $items, int $count): array
    {
        $items = array_values($items);
        $count = min($count, count($items));
        if ($count <= 0) {
            return [];
        }
        $keys = array_rand($items, $count);
        if (!is_array($keys)) {
            $keys = [$keys];
        }

        return array_map(fn ($k) => $items[$k], $keys);
    }
}
