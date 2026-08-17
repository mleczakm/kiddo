<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Entity\Lesson;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WorkshopImageAction extends AbstractController
{
    #[Route('/warsztat/{id}/obraz', name: 'workshop_image', requirements: [
        'id' => '[A-Za-z0-9]+',
    ])]
    public function __invoke(Request $request, Lesson $lesson): Response
    {
        $metadata = $lesson->getMetadata();
        if (! $metadata->hasImage()) {
            throw $this->createNotFoundException();
        }

        $data = base64_decode((string) $metadata->imageData, true);
        if ($data === false) {
            throw $this->createNotFoundException();
        }

        // Videos need real byte-range support: iOS Safari sends a Range
        // request before it will play an inline <video>, and refuses to
        // play at all if the response comes back as a plain 200. The data
        // only lives in the DB as base64, so it's spooled to a temp file
        // and handed to BinaryFileResponse, which implements Range/206
        // for us.
        if ($metadata->isVideo()) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'workshop_video_');
            file_put_contents($tmpFile, $data);

            $response = new BinaryFileResponse($tmpFile, 200, [
                'Content-Type' => $metadata->imageMimeType,
            ]);
            $response->deleteFileAfterSend(true);
            $response->setPublic();
            $response->setMaxAge(3600);
            $response->prepare($request);

            return $response;
        }

        $response = new Response($data, 200, [
            'Content-Type' => $metadata->imageMimeType,
        ]);
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
