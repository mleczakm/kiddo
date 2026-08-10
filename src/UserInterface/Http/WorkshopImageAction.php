<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Entity\Lesson;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WorkshopImageAction extends AbstractController
{
    #[Route('/warsztat/{id}/obraz', name: 'workshop_image', requirements: [
        'id' => '[A-Za-z0-9]+',
    ])]
    public function __invoke(Lesson $lesson): Response
    {
        $metadata = $lesson->getMetadata();
        if (! $metadata->hasImage()) {
            throw $this->createNotFoundException();
        }

        $data = base64_decode((string) $metadata->imageData, true);
        if ($data === false) {
            throw $this->createNotFoundException();
        }

        $response = new Response($data, 200, [
            'Content-Type' => $metadata->imageMimeType,
        ]);
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
