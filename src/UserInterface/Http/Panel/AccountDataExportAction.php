<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Panel;

use App\Application\Account\AccountDataExporter;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** RODO art. 15/20 self-service export: the account holder's own data as a JSON download. */
final class AccountDataExportAction extends AbstractController
{
    /** @throws \UnexpectedValueException */
    #[Route(
        path: [
            'en' => '/account/settings/data-export.json',
            'pl' => '/panel/konto/moje-dane.json',
        ],
        name: 'panel_account_data_export',
        methods: ['GET'],
    )]
    #[IsGranted('ROLE_USER')]
    public function __invoke(AccountDataExporter $exporter, #[CurrentUser] User $user): Response
    {
        $response = new JsonResponse($exporter->export($user), Response::HTTP_OK, [], false);
        $response->setEncodingOptions(\JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $response->headers->set('Content-Disposition', 'attachment; filename="kiddo-moje-dane.json"');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
