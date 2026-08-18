<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Entity\Lesson;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

        if ($metadata->isVideo()) {
            return $this->rangeResponse($request, $data, (string) $metadata->imageMimeType);
        }

        $response = new Response($data, 200, [
            'Content-Type' => $metadata->imageMimeType,
        ]);
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }

    /**
     * Videos need real byte-range support: iOS Safari sends a Range
     * request before it will play an inline <video>, and refuses to play
     * at all if the response comes back as a plain 200.
     *
     * This can't use BinaryFileResponse: under the Swoole runtime,
     * swoole-bundle's EndResponseProcessor sends a BinaryFileResponse via
     * `Swoole\Http\Response::sendfile($path)` without the byte offset/length
     * Symfony computed from the Range header, so it silently streams the
     * file from byte 0 (truncated to the requested Content-Length) no
     * matter which range was asked for — the headers claim the right
     * range, but the body is wrong for anything past the first chunk.
     * Slicing the range ourselves into a plain Response sidesteps that
     * bridge entirely, since non-binary responses are sent via
     * `$swooleResponse->end($content)`.
     */
    private function rangeResponse(Request $request, string $data, string $mimeType): Response
    {
        $size = \strlen($data);
        $range = $request->headers->get('Range');

        if ($range === null || preg_match('/^bytes=(\d*)-(\d*)$/', $range, $matches) !== 1) {
            $response = new Response($data, 200, [
                'Content-Type' => $mimeType,
                'Accept-Ranges' => 'bytes',
            ]);
            $response->setPublic();
            $response->setMaxAge(3600);

            return $response;
        }

        if ($matches[1] === '') {
            // Suffix range, e.g. "bytes=-500" means the last 500 bytes.
            $start = max(0, $size - (int) $matches[2]);
            $end = $size - 1;
        } else {
            $start = (int) $matches[1];
            $end = $matches[2] === '' ? $size - 1 : min((int) $matches[2], $size - 1);
        }

        if ($start >= $size || $start > $end) {
            return new Response('', 416, [
                'Content-Range' => "bytes */{$size}",
            ]);
        }

        $slice = substr($data, $start, $end - $start + 1);

        $response = new Response($slice, 206, [
            'Content-Type' => $mimeType,
            'Accept-Ranges' => 'bytes',
            'Content-Range' => "bytes {$start}-{$end}/{$size}",
            'Content-Length' => (string) \strlen($slice),
        ]);
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
