<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Repository\SettingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StaticEndpointAction extends AbstractController
{
    public function __construct(
        private readonly SettingRepository $settingRepository
    ) {}

    #[Route('/{path}', name: 'static_endpoint', requirements: [
        'path' => 'robots\.txt|sitemap\.xml|security\.txt',
    ], methods: ['GET'])]
    public function __invoke(Request $request, string $path): Response
    {
        $setting = $this->settingRepository->findOneByKey('static_endpoint_' . $path);

        if ($setting === null) {
            return new Response('Not found', Response::HTTP_NOT_FOUND);
        }

        $content = $setting->getContent();
        $contentType = is_string($content['content_type'] ?? null) ? $content['content_type'] : 'text/plain';
        $body = is_string($content['body'] ?? null) ? $content['body'] : '';

        return new Response($body, Response::HTTP_OK, [
            'Content-Type' => $contentType,
        ]);
    }
}
