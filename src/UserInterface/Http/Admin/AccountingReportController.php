<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use App\Application\Accounting\MonthlyAccountingReportBuilder;
use App\Infrastructure\Symfony\Security\Voter\AccountingReportVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AccountingReportController extends AbstractController
{
    /**
     * @throws \Brick\Math\Exception\MathException
     * @throws \Brick\Money\Exception\MoneyMismatchException
     * @throws \InvalidArgumentException
     */
    #[IsGranted(AccountingReportVoter::VIEW)]
    #[Route('/admin/raport-miesieczny/{month}', name: 'app_admin_accounting_report', requirements: [
        'month' => '\d{4}-(0[1-9]|1[0-2])',
    ])]
    public function __invoke(string $month, MonthlyAccountingReportBuilder $builder): Response
    {
        $report = $builder->build(new \DateTimeImmutable($month . '-01'));

        $formatter = new \IntlDateFormatter('pl_PL', pattern: 'LLLL yyyy');
        $monthLabel = $formatter->format($report->month);

        return $this->render('admin/accounting/report.html.twig', [
            'report' => $report,
            'monthLabel' => ucfirst((string) $monthLabel),
        ]);
    }
}
