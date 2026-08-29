<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\Core\Exceptions\InvalidReportCriteriaException;
use App\Component\Core\ReportCriteria;
use App\Component\Product\AbcClassifier;
use App\Component\Product\Dtos\AbcAnalysisDto;
use App\Component\Product\Enums\AbcMetric;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Repository\ProfitRepository;
use App\Repository\SaleItemRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * ABC analysis: which products carry the business and which only take up shelf space.
 *
 * Arguments come from the query string, like every other report here (this operation is
 * declared with input: false). The thresholds are shares of the total, so C needs no
 * parameter — it is whatever thresholdB leaves behind.
 */
class ProductAbcAnalysisAction extends AbstractController
{
    private const DEFAULT_THRESHOLD_A = 80.0;
    private const DEFAULT_THRESHOLD_B = 95.0;

    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private SaleItemRepository $saleItemRepository,
        private ProfitRepository $profitRepository,
        private AbcClassifier $abcClassifier,
        private ReportCriteria $criteria,
        private RequestStack $requestStack,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(): AbcAnalysisDto
    {
        $request = $this->requestStack->getCurrentRequest();

        $metric = $this->metric($request);
        // Quantity is not money: it ranks the whole catalogue and ignores the currency even
        // when one is sent, which a UI that always fills the field will do.
        $currency = $metric->isMoney() ? $this->criteria->requiredCurrency($request) : null;
        $from = $this->criteria->date($request, 'from');
        $to = $this->criteria->date($request, 'to');
        $categoryId = $this->criteria->categoryId($request);
        [$thresholdA, $thresholdB] = $this->thresholds($request);

        $rows = $metric === AbcMetric::PROFIT
            ? $this->profitRepository->getAbcTotals($currency, $from, $to, $categoryId)
            : $this->saleItemRepository->getAbcTotals($metric, $from, $to, $categoryId, $currency);

        return $this->abcClassifier->classify($rows, $metric, $currency?->value, $thresholdA, $thresholdB);
    }

    private function metric(?Request $request): AbcMetric
    {
        $raw = (string) ($request?->query->get('metric') ?? AbcMetric::REVENUE->value);
        $metric = AbcMetric::tryFrom($raw);

        if ($metric === null) {
            throw new InvalidReportCriteriaException(sprintf(
                'Неизвестная метрика «%s». Допустимые: %s.',
                $raw,
                implode(', ', array_column(AbcMetric::cases(), 'value'))
            ));
        }

        return $metric;
    }

    /** @return array{0: float, 1: float} */
    private function thresholds(?Request $request): array
    {
        $a = $this->criteria->float($request, 'thresholdA', self::DEFAULT_THRESHOLD_A);
        $b = $this->criteria->float($request, 'thresholdB', self::DEFAULT_THRESHOLD_B);

        if ($a <= 0.0 || $b >= 100.0 || $a >= $b) {
            throw new InvalidReportCriteriaException(
                'Пороги должны идти по возрастанию внутри интервала: 0 < A < B < 100.'
            );
        }

        return [$a, $b];
    }
}
