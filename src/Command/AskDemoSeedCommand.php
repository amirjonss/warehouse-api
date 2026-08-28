<?php

declare(strict_types=1);

namespace App\Command;

use App\Component\Core\Enums\DocStatus;
use App\Component\Core\Enums\PaymentMethod;
use App\Component\Expense\ExpenseFactory;
use App\Component\Payment\PaymentFactory;
use App\Component\Product\Enums\Currency;
use App\Component\Product\Enums\UnitCode;
use App\Component\Receipt\ReceiptFactory;
use App\Component\ReceiptItem\ReceiptItemFactory;
use App\Component\Sale\SaleFactory;
use App\Component\User\UserFactory;
use App\Component\User\UserManager;
use App\Component\Writeoff\WriteoffFactory;
use App\Component\WriteoffItem\WriteoffItemFactory;
use App\Entity\CashSession;
use App\Entity\Category;
use App\Entity\Client;
use App\Entity\ExchangeRate;
use App\Entity\Product;
use App\Entity\Receipt;
use App\Entity\ReceiptItem;
use App\Entity\Payment;
use App\Entity\Sale;
use App\Entity\SaleItem;
use App\Entity\Supplier;
use App\Entity\User;
use App\Entity\WriteoffItem;
use App\Repository\BatchRepository;
use App\Repository\CashSessionRepository;
use App\Repository\ClientRepository;
use App\Repository\StockMovementRepository;
use App\Repository\UserRepository;
use App\Service\CashExpenseService;
use App\Service\CashHandoverService;
use App\Service\CashSessionCloseService;
use App\Service\CashSessionOpenService;
use App\Service\PaymentAutoAllocationService;
use App\Service\PaymentChangeStatusService;
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
 * ledgers all come out exactly as they would from real usage.
 *
 * The money side is generated too: payments in three methods, expenses, and the sellers'
 * cash floats with their journals. Sessions are replayed in order — open, collect, spend,
 * hand over, close — because that is the only order the services accept: a cash payment
 * is refused unless the person accepting it has an open session at that moment.
 *
 * Timestamps are rewritten at the end. Every factory in the project stamps "now", so
 * without that pass a year's worth of documents would carry today's date in every ledger.
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

    /** Demo sellers. The password is an option, never a constant. */
    private const SELLER_DEFS = [
        ['sales1@example.com', 'Дилноза', 'Каримова'],
        ['sales2@example.com', 'Отабек', 'Раҳимов'],
        ['sales3@example.com', 'Шахноза', 'Юсупова'],
        ['sales4@example.com', 'Жасур', 'Тошматов'],
        ['sales5@example.com', 'Феруза', 'Абдуллаева'],
    ];

    private const EXPENSE_REASONS = [
        'Бензин для доставки', 'Обед водителю', 'Ремонт погрузчика', 'Упаковочный материал',
        'Мобильная связь', 'Стоянка на рынке', 'Мелкий ремонт склада', 'Канцелярия',
        'Питьевая вода в офис', 'Грузчики на разгрузке', 'Скотч и стрейч-плёнка', 'Такси до клиента',
    ];

    private const HANDOVER_NOTES = [
        'Сдал в офисе', 'Передал у склада', 'Инкассация', 'Отдал бухгалтеру', 'Сдал вечером',
    ];

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
        private ClientRepository $clientRepository,
        private CashSessionRepository $cashSessionRepository,
        private UserFactory $userFactory,
        private UserManager $userManager,
        private PaymentFactory $paymentFactory,
        private PaymentAutoAllocationService $paymentAutoAllocationService,
        private PaymentChangeStatusService $paymentChangeStatusService,
        private ExpenseFactory $expenseFactory,
        private CashExpenseService $cashExpenseService,
        private CashSessionOpenService $cashSessionOpenService,
        private CashSessionCloseService $cashSessionCloseService,
        private CashHandoverService $cashHandoverService,
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
            ->addOption('sellers', null, InputOption::VALUE_REQUIRED, 'How many demo sellers to create/use', 5)
            ->addOption('seller-password', null, InputOption::VALUE_REQUIRED, 'Password for the demo sellers', 'string')
            ->addOption('sessions', null, InputOption::VALUE_REQUIRED, 'Cash sessions per seller over the whole period', 9)
            ->addOption('expenses', null, InputOption::VALUE_REQUIRED, 'How many company-wide expenses (outside any float)', 80)
            ->addOption('months', null, InputOption::VALUE_REQUIRED, 'How many months back documents are spread over', 12);
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
            'sellers' => (int) $input->getOption('sellers'),
            'sessions' => (int) $input->getOption('sessions'),
            'expenses' => (int) $input->getOption('expenses'),
        ];
        $months = (int) $input->getOption('months');
        $sellerPassword = (string) $input->getOption('seller-password');

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

        $io->section('Продавцы');
        $sellers = $this->seedSellers($io, $counts['sellers'], $sellerPassword);
        if ($sellers === []) {
            $io->error('Не удалось получить ни одного продавца.');

            return Command::FAILURE;
        }

        $io->section('Категории');
        $categories = $this->seedCategories($io, $counts['categories']);

        $io->section('Товары');
        $products = $this->seedProducts($io, $categories, $counts['products']);

        $io->section('Клиенты');
        $clients = $this->seedClients($io, $counts['clients']);

        $io->section('Поставщики');
        $suppliers = $this->seedSuppliers($io, $counts['suppliers']);

        $io->section('Курсы валют');
        $this->seedExchangeRates($io, $actor, $windowStart, $windowEnd);

        $io->section('Приходы');
        $this->seedReceipts($io, $actor, $suppliers, $products, $counts['receipts'], $windowStart, $windowEnd);

        $io->section('Продажи');
        $remainingByProduct = $this->stockMovementRepository->getRemainingQtyByProduct();
        $this->seedSales($io, $sellers, $clients, $products, $counts['sales'], $windowStart, $windowEnd, $remainingByProduct);

        $io->section('Списания');
        $this->impersonate($actor);
        $this->seedWriteoffs($io, $actor, $counts['writeoffs'], $windowStart, $windowEnd);

        $io->section('Расходы компании');
        $this->seedCompanyExpenses($io, $actor, $counts['expenses'], $windowStart, $windowEnd);

        $io->section('Касса: смены, платежи, сдачи');
        $this->seedCashFloats($io, $actor, $sellers, $counts['sessions'], $windowStart, $windowEnd);

        $io->section('Исторические даты');
        $this->alignTimestamps($io);

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

        // The slug and the sort order survive only as keys inside CATEGORY_DEFS: the
        // entity itself no longer has those fields.
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

                // Oils are sold by the litre, everything else by the kilo. Keyed off the name
                // because CATEGORY_DEFS lost its slug together with the entity field.
                $baseUnit = str_contains($def['name'], 'Масло подсолнечное') ? UnitCode::L : UnitCode::KG;
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
            // Matching used to go by slug; the field is gone, and CATEGORY_DEFS names are unique.
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
     * @param User[] $sellers
     * @param Client[] $clients
     * @param Product[] $products
     * @param array<int, string> $remainingByProduct product id => remaining qty, mutated as sales consume stock
     */
    private function seedSales(
        SymfonyStyle $io,
        array $sellers,
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

            // Every sale belongs to a seller: that is what makes the debt and the float
            // attributable to a person later on.
            $actor = $this->randomFrom($sellers);
            $this->impersonate($actor);

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
                // Nothing fitted: a draft with no lines cannot be posted, so drop it and retry.
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

    /**
     * Demo sellers are reused if they already exist: rerunning the seed must not fail on a
     * taken email, and the password is only applied to the ones it creates.
     *
     * @return User[]
     */
    private function seedSellers(SymfonyStyle $io, int $count, string $password): array
    {
        $sellers = [];
        $created = 0;

        foreach (array_slice(self::SELLER_DEFS, 0, $count) as [$email, $firstName, $lastName]) {
            $user = $this->userRepository->findOneByEmail($email);

            if ($user === null) {
                $user = $this->userFactory->create($email, $password, ['ROLE_SALES'], $firstName, $lastName);
                $this->userManager->save($user, true);
                $created++;
            }

            $sellers[] = $user;
        }

        $io->text(sprintf('Продавцов создано: %d, всего задействовано: %d', $created, count($sellers)));
        $io->table(
            ['Email', 'Имя', 'Пароль'],
            array_map(
                fn (User $u) => [$u->getEmail(), trim($u->getFirstName() . ' ' . $u->getLastName()), $password],
                $sellers
            )
        );

        return $sellers;
    }

    /** One quote a week, following the same drift the documents use. */
    private function seedExchangeRates(SymfonyStyle $io, User $actor, DateTime $windowStart, DateTime $windowEnd): void
    {
        $created = 0;
        $cursor = (clone $windowStart);

        while ($cursor <= $windowEnd) {
            $sell = (int) round((float) $this->rateForDate($cursor, $windowStart, $windowEnd));

            // The setters on this entity come from traits and return void, so no chaining.
            $rate = new ExchangeRate();
            $rate->setRateBuy($sell - random_int(30, 90));
            $rate->setRateSell($sell);
            $rate->setCreatedAt(clone $cursor);
            $rate->setCreatedBy($actor);

            $this->entityManager->persist($rate);
            $created++;
            $cursor = (clone $cursor)->modify('+7 days');
        }
        $this->entityManager->flush();

        $io->text(sprintf('Создано курсов: %d', $created));
    }

    /**
     * Expenses with nobody's float behind them — the administrator paying for the office.
     * The float's own expenses are generated later, inside the sessions.
     */
    private function seedCompanyExpenses(SymfonyStyle $io, User $actor, int $count, DateTime $windowStart, DateTime $windowEnd): void
    {
        $this->impersonate($actor);
        $created = 0;

        for ($i = 0; $i < $count; $i++) {
            $currency = random_int(1, 4) === 1 ? Currency::USD : Currency::UZS;
            $amount = $currency === Currency::USD
                ? number_format(random_int(20, 400) + random_int(0, 99) / 100, 2, '.', '')
                : (string) (random_int(80, 3000) * 1000) . '.00';

            $expense = $this->expenseFactory->create(
                $actor,
                $this->randomFrom(self::EXPENSE_REASONS),
                $amount,
                $currency,
                $this->randomDate($windowStart, $windowEnd)
            );

            // Straight through the entity manager: the administrator has no open float, and
            // CashExpenseService would simply pass it through anyway.
            $this->entityManager->persist($expense);
            $created++;

            if ($created % 50 === 0) {
                $this->entityManager->flush();
            }
        }
        $this->entityManager->flush();

        $io->text(sprintf('Создано расходов компании: %d', $created));
    }

    /**
     * The heart of the money side: each seller's year is cut into consecutive float
     * periods, and every period is replayed the way it happens in real life —
     * open, collect, spend, hand over, close. The last period stays open, with one
     * declared but unconfirmed handover, so the dashboard has something to act on.
     *
     * @param User[] $sellers
     */
    private function seedCashFloats(
        SymfonyStyle $io,
        User $admin,
        array $sellers,
        int $sessionsPerSeller,
        DateTime $windowStart,
        DateTime $windowEnd,
    ): void {
        $progress = $io->createProgressBar(count($sellers) * $sessionsPerSeller);
        $stats = ['sessions' => 0, 'open' => 0, 'payments' => 0, 'cancelled' => 0, 'expenses' => 0, 'handovers' => 0, 'shortages' => 0];

        $spanSeconds = $windowEnd->getTimestamp() - $windowStart->getTimestamp();
        $step = intdiv($spanSeconds, max(1, $sessionsPerSeller));

        foreach ($sellers as $seller) {
            for ($i = 0; $i < $sessionsPerSeller; $i++) {
                $from = (new DateTime())->setTimestamp($windowStart->getTimestamp() + $i * $step);
                $to = (new DateTime())->setTimestamp($windowStart->getTimestamp() + ($i + 1) * $step - 1);
                $isLast = $i === $sessionsPerSeller - 1;

                $this->runFloatPeriod($seller, $admin, $from, $to, $isLast, $stats);

                $stats['sessions']++;
                $progress->advance();
            }
        }

        $progress->finish();
        $io->newLine(2);
        $io->table(
            ['Что', 'Сколько'],
            [
                ['смен всего', $stats['sessions']],
                ['из них открытых', $stats['open']],
                ['платежей проведено', $stats['payments']],
                ['платежей отменено', $stats['cancelled']],
                ['расходов из подотчёта', $stats['expenses']],
                ['сдач денег', $stats['handovers']],
                ['смен с недостачей', $stats['shortages']],
            ]
        );
    }

    /**
     * @param array<string, int> $stats mutated in place
     */
    private function runFloatPeriod(User $seller, User $admin, DateTime $from, DateTime $to, bool $keepOpen, array &$stats): void
    {
        $this->impersonate($seller);

        try {
            $session = $this->cashSessionOpenService->open($seller, $seller);
        } catch (\Throwable) {
            // A float is already open for this seller (a rerun, or the previous period could
            // not be closed). Nothing sensible to add on top of it.
            return;
        }

        $payments = $this->collectPayments($seller, $from, $to, random_int(6, 14), $stats);

        // Cancelling has to happen before the money is spent: the service refuses a reversal
        // once the cash has left the float.
        if ($payments !== [] && random_int(1, 3) === 1) {
            $this->cancelPayment($this->randomFrom($payments), $stats);
        }

        $this->spendFromFloat($seller, $from, $to, random_int(1, 4), $stats);
        $this->handOverCash($session, $seller, $admin, random_int(1, 3), $stats);

        if ($keepOpen) {
            // One unconfirmed handover left hanging: this is what unconfirmed* is for.
            $this->declareWithoutConfirming($session, $seller, $stats);
            $stats['open']++;
        } else {
            $this->closeFloat($session, $admin, $stats);
        }

        $this->backdateSession($session, $from, $to);
    }

    /**
     * @return Payment[]
     * @param array<string, int> $stats
     */
    private function collectPayments(User $seller, DateTime $from, DateTime $to, int $count, array &$stats): array
    {
        $posted = [];

        for ($i = 0; $i < $count; $i++) {
            $currency = random_int(1, 3) === 1 ? Currency::UZS : Currency::USD;
            $candidate = $this->clientWithDebt($currency);
            if ($candidate === null) {
                continue;
            }

            [$client, $payable] = $candidate;
            $amount = $this->portionOf($payable, $currency);
            if (bccomp($amount, '0', 2) <= 0) {
                continue;
            }

            $payment = $this->paymentFactory->create(
                $seller,
                $client,
                $amount,
                $currency,
                $this->randomPaymentMethod(),
                null,
                null,
                '',
                $this->randomDate($from, $to)
            );
            $this->entityManager->persist($payment);
            $this->entityManager->flush();

            try {
                $this->paymentAutoAllocationService->autoAllocateAndPost($payment);
                $posted[] = $payment;
                $stats['payments']++;
            } catch (\Throwable) {
                // The debt moved under us (another payment in this same period took it), or
                // the amount no longer fits. Drop the draft and move on.
                $this->discard($payment);
            }
        }

        return $posted;
    }

    /** @param array<string, int> $stats */
    private function cancelPayment(Payment $payment, array &$stats): void
    {
        try {
            $payment->setStatus(DocStatus::CANCELLED);
            $this->paymentChangeStatusService->changeStatus($payment);
            $stats['cancelled']++;
        } catch (\Throwable) {
            $this->entityManager->clear();
        }
    }

    /** @param array<string, int> $stats */
    private function spendFromFloat(User $seller, DateTime $from, DateTime $to, int $count, array &$stats): void
    {
        for ($i = 0; $i < $count; $i++) {
            $session = $this->cashSessionRepository->findOpenForUser($seller);
            if ($session === null) {
                return;
            }

            // Spend a slice of what is actually in the bag, in whichever currency has more.
            [$currency, $balance] = $this->richerSide($session);
            if (bccomp($balance, '1', 2) <= 0) {
                return;
            }

            $amount = $this->portionOf($balance, $currency, 5, 25);
            if (bccomp($amount, '0', 2) <= 0) {
                return;
            }

            $expense = $this->expenseFactory->create(
                $seller,
                $this->randomFrom(self::EXPENSE_REASONS),
                $amount,
                $currency,
                $this->randomDate($from, $to)
            );

            try {
                $this->cashExpenseService->create($expense);
                $stats['expenses']++;
            } catch (\Throwable) {
                $this->entityManager->clear();

                return;
            }
        }
    }

    /** @param array<string, int> $stats */
    private function handOverCash(CashSession $session, User $seller, User $admin, int $count, array &$stats): void
    {
        for ($i = 0; $i < $count; $i++) {
            $fresh = $this->cashSessionRepository->findOpenForUser($seller);
            if ($fresh === null) {
                return;
            }

            [$currency, $balance] = $this->richerSide($fresh);
            if (bccomp($balance, '1', 2) <= 0) {
                return;
            }

            $amount = $this->portionOf($balance, $currency, 40, 80);
            if (bccomp($amount, '0', 2) <= 0) {
                return;
            }

            try {
                $entry = $this->cashHandoverService->declareHandover(
                    $fresh,
                    $amount,
                    $currency,
                    $this->randomFrom(self::HANDOVER_NOTES),
                    $seller
                );
                $this->cashHandoverService->confirm($entry, $admin);
                $stats['handovers']++;
            } catch (\Throwable) {
                $this->entityManager->clear();

                return;
            }
        }
    }

    /** @param array<string, int> $stats */
    private function declareWithoutConfirming(CashSession $session, User $seller, array &$stats): void
    {
        $fresh = $this->cashSessionRepository->findOpenForUser($seller);
        if ($fresh === null) {
            return;
        }

        [$currency, $balance] = $this->richerSide($fresh);
        if (bccomp($balance, '1', 2) <= 0) {
            return;
        }

        $amount = $this->portionOf($balance, $currency, 20, 50);
        if (bccomp($amount, '0', 2) <= 0) {
            return;
        }

        try {
            $this->cashHandoverService->declareHandover($fresh, $amount, $currency, 'Отдал, жду подтверждения', $seller);
            $stats['handovers']++;
        } catch (\Throwable) {
            $this->entityManager->clear();
        }
    }

    /**
     * The owner accepts what the seller brought. Every fourth time a little goes missing —
     * that shortage row is the whole reason the float exists.
     *
     * @param array<string, int> $stats
     */
    private function closeFloat(CashSession $session, User $admin, array &$stats): void
    {
        $fresh = $this->cashSessionRepository->find($session->getId());
        if ($fresh === null || !$fresh->isOpen()) {
            return;
        }

        $short = random_int(1, 4) === 1;
        $acceptedUsd = $short ? $this->portionOf($fresh->getBalanceUsd(), Currency::USD, 80, 97) : $fresh->getBalanceUsd();
        $acceptedUzs = $short ? $this->portionOf($fresh->getBalanceUzs(), Currency::UZS, 80, 97) : $fresh->getBalanceUzs();

        try {
            $this->cashSessionCloseService->close($fresh, $acceptedUsd, $acceptedUzs, 'Принял остаток', $admin);
            if ($short) {
                $stats['shortages']++;
            }
        } catch (\Throwable) {
            $this->entityManager->clear();
        }
    }

    /**
     * Cash factories stamp "now", so a period generated for last spring would otherwise sit
     * in the journal with today's date. The rows are spread evenly across the period in id
     * order, which keeps the journal readable chronologically.
     */
    private function backdateSession(CashSession $session, DateTime $from, DateTime $to): void
    {
        $connection = $this->entityManager->getConnection();
        $id = $session->getId();

        $ids = $connection->fetchFirstColumn('SELECT id FROM cash_entries WHERE session_id = :id ORDER BY id', ['id' => $id]);
        $span = max(1, $to->getTimestamp() - $from->getTimestamp());
        $stepSeconds = intdiv($span, max(1, count($ids) + 1));

        foreach ($ids as $index => $entryId) {
            $at = date('Y-m-d H:i:s', $from->getTimestamp() + ($index + 1) * $stepSeconds);
            $connection->executeStatement(
                'UPDATE cash_entries
                    SET occurred_at = :at::timestamp,
                        confirmed_at = CASE WHEN confirmed_at IS NULL THEN NULL ELSE :at::timestamp END
                  WHERE id = :id',
                ['at' => $at, 'id' => $entryId]
            );
        }

        $connection->executeStatement(
            'UPDATE cash_sessions
                SET opened_at = :from::timestamp,
                    closed_at = CASE WHEN closed_at IS NULL THEN NULL ELSE :to::timestamp END
              WHERE id = :id',
            ['from' => $from->format('Y-m-d H:i:s'), 'to' => $to->format('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    /**
     * Ledger rows carry the moment they were written, which for a seed is "now" for all of
     * them at once. Pull each row back onto the date of the document that produced it, so
     * charts and period filters have a year of history to show.
     */
    private function alignTimestamps(SymfonyStyle $io): void
    {
        $connection = $this->entityManager->getConnection();

        $statements = [
            'UPDATE sales SET created_at = doc_date, posted_at = CASE WHEN posted_at IS NULL THEN NULL ELSE doc_date::timestamp END',
            'UPDATE receipts SET created_at = doc_date, posted_at = CASE WHEN posted_at IS NULL THEN NULL ELSE doc_date::timestamp END',
            'UPDATE payments SET created_at = doc_date, posted_at = CASE WHEN posted_at IS NULL THEN NULL ELSE doc_date::timestamp END',
            'UPDATE writeoffs SET created_at = doc_date',
            'UPDATE expenses SET created_at = doc_date',
            'UPDATE profits p SET occurred_at = s.doc_date FROM sales s WHERE s.id = p.sale_id',
            'UPDATE debts d SET occurred_at = p.doc_date FROM payments p WHERE p.id = d.payment_id',
            'UPDATE debts d SET occurred_at = s.doc_date FROM sales s WHERE s.id = d.sale_id AND d.payment_id IS NULL',
            "UPDATE stock_movements m SET occurred_at = r.doc_date FROM receipts r WHERE m.doc_type = 'receipt' AND r.id = m.doc_id",
            "UPDATE stock_movements m SET occurred_at = s.doc_date FROM sales s WHERE m.doc_type = 'sale' AND s.id = m.doc_id",
            "UPDATE stock_movements m SET occurred_at = w.doc_date FROM writeoffs w WHERE m.doc_type = 'writeoff' AND w.id = m.doc_id",
        ];

        foreach ($statements as $sql) {
            $connection->executeStatement($sql);
        }

        $io->text(sprintf('Переписано таблиц: %d', count($statements)));
    }

    // ---------- small helpers for the money side ----------

    /**
     * A client who still owes something in this currency, plus how much of that debt sits on
     * sales old enough to be paid now.
     *
     * @return array{0: Client, 1: string}|null
     */
    private function clientWithDebt(Currency $currency): ?array
    {
        $column = $currency === Currency::USD ? 'debt_usd' : 'debt_uzs';

        $row = $this->entityManager->getConnection()->fetchAssociative(
            sprintf(
                'SELECT id, %s AS debt FROM clients WHERE %s > 0 ORDER BY random() LIMIT 1',
                $column,
                $column
            )
        );

        if ($row === false) {
            return null;
        }

        $client = $this->clientRepository->find((int) $row['id']);

        return $client === null ? null : [$client, (string) $row['debt']];
    }

    private function randomPaymentMethod(): PaymentMethod
    {
        // Cash dominates in this trade, but the other two have to be present: they are what
        // shows the difference between the session's turnover and the money on hand.
        return match (random_int(1, 10)) {
            1, 2 => PaymentMethod::CARD,
            3, 4 => PaymentMethod::TRANSFER,
            default => PaymentMethod::CASH,
        };
    }

    /** @return array{0: Currency, 1: string} the currency the float holds more of */
    private function richerSide(CashSession $session): array
    {
        $usd = $session->getBalanceUsd() ?? '0';
        $uzs = $session->getBalanceUzs() ?? '0';

        // Sums are numerically far larger, so compare against a rough rate rather than raw.
        return bccomp(bcmul($usd, '12500', 2), $uzs, 2) > 0
            ? [Currency::USD, $usd]
            : [Currency::UZS, $uzs];
    }

    private function portionOf(string $amount, Currency $currency, int $minPercent = 30, int $maxPercent = 100): string
    {
        $value = (float) $amount * random_int($minPercent, $maxPercent) / 100;

        return $currency === Currency::USD
            ? number_format($value, 2, '.', '')
            : number_format(floor($value / 1000) * 1000, 2, '.', '');
    }

    private function discard(Payment $payment): void
    {
        try {
            $this->entityManager->remove($payment);
            $this->entityManager->flush();
        } catch (\Throwable) {
            $this->entityManager->clear();
        }
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
