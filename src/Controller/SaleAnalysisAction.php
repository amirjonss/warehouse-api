<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\Core\Exceptions\InvalidReportCriteriaException;
use App\Component\Core\ReportCriteria;
use App\Component\Sale\Dtos\SalesAnalysisDto;
use App\Component\Sale\Dtos\SalesAnalysisRowDto;
use App\Component\Sale\Enums\SalesInterval;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Repository\SaleRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Sales analysis: how trade went, period by period.
 *
 * Where the ABC report ranks products, this one follows time — quantity, revenue, cost and
 * profit per day, week or month. The margin column next to the revenue one is the point:
 * turnover can grow while the money made on it shrinks, and only these two side by side
 * show it.
 */
class SaleAnalysisAction extends AbstractController
{
    private const SCALE = 2;

    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private SaleRepository $saleRepository,
        private ReportCriteria $criteria,
        private RequestStack $requestStack,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(): SalesAnalysisDto
    {
        $request = $this->requestStack->getCurrentRequest();

        $interval = $this->interval($request);
        $currency = $this->criteria->requiredCurrency($request);

        $rows = $this->saleRepository->getAnalysis(
            $interval,
            $this->criteria->date($request, 'from'),
            $this->criteria->date($request, 'to'),
            $this->criteria->categoryId($request),
            $currency,
        );

        return new SalesAnalysisDto(
            $interval->value,
            $currency->value,
            array_map(fn (array $row) => $this->row($row), $rows),
            $this->grandTotal($rows),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function row(array $row): SalesAnalysisRowDto
    {
        $revenue = $this->money($row['revenue'] ?? '0');
        $profit = $this->money($row['profit'] ?? '0');

        return new SalesAnalysisRowDto(
            (string) $row['period'],
            (int) $row['documents'],
            bcadd((string) ($row['quantity'] ?? '0'), '0', 3),
            $revenue,
            bcsub($revenue, $profit, self::SCALE),
            $profit,
            $this->margin($profit, $revenue),
        );
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function grandTotal(array $rows): SalesAnalysisRowDto
    {
        $documents = 0;
        $quantity = '0';
        $revenue = '0';
        $profit = '0';

        foreach ($rows as $row) {
            $documents += (int) $row['documents'];
            $quantity = bcadd($quantity, (string) ($row['quantity'] ?? '0'), 3);
            $revenue = bcadd($revenue, $this->money($row['revenue'] ?? '0'), self::SCALE);
            $profit = bcadd($profit, $this->money($row['profit'] ?? '0'), self::SCALE);
        }

        return new SalesAnalysisRowDto(
            // The total spans every interval at once, so it belongs to none of them.
            null,
            $documents,
            $quantity,
            $revenue,
            bcsub($revenue, $profit, self::SCALE),
            $profit,
            $this->margin($profit, $revenue),
        );
    }

    private function margin(string $profit, string $revenue): string
    {
        if (bccomp($revenue, '0', self::SCALE) === 0) {
            return '0.00';
        }

        return bcdiv(bcmul($profit, '100', 6), $revenue, self::SCALE);
    }

    private function money(mixed $value): string
    {
        return bcadd((string) $value, '0', self::SCALE);
    }

    private function interval(?Request $request): SalesInterval
    {
        $raw = (string) ($request?->query->get('interval') ?? SalesInterval::MONTH->value);
        $interval = SalesInterval::tryFrom($raw);

        if ($interval === null) {
            throw new InvalidReportCriteriaException(sprintf(
                'Неизвестный интервал «%s». Допустимые: %s.',
                $raw,
                implode(', ', array_column(SalesInterval::cases(), 'value'))
            ));
        }

        return $interval;
    }
}
