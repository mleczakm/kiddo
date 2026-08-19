<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait RangeResponseTrait
{
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
     *
     * @throws \InvalidArgumentException
     */
    protected function rangeResponse(
        Request $request,
        string $data,
        string $mimeType,
        ?string $etag = null,
        ?\DateTimeImmutable $lastModified = null,
    ): Response {
        $size = \strlen($data);
        $range = $request->headers->get('Range');
        $matches = [];

        // If-Range makes a resumed download safe: when the file has changed
        // since the client's last chunk (validator no longer matches), fall
        // through to a full 200 instead of stitching stale and fresh bytes
        // together.
        $ifRange = $request->headers->get('If-Range');
        $rangeIsStale = $ifRange !== null && $etag !== null && trim($ifRange, '"') !== $etag;

        if ($range === null || $rangeIsStale || preg_match('/^bytes=(\d*)-(\d*)$/', $range, $matches) !== 1) {
            $response = new Response($data, 200, [
                'Content-Type' => $mimeType,
                'Accept-Ranges' => 'bytes',
            ]);
            $response->headers->set('X-Cache-Public-Max-Age', '3600');
            $this->applyRangeValidators($response, $etag, $lastModified);

            return $response;
        }

        if ($matches[1] === '') {
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
        $this->applyRangeValidators($response, $etag, $lastModified);

        return $response;
    }

    private function applyRangeValidators(Response $response, ?string $etag, ?\DateTimeImmutable $lastModified): void
    {
        if ($etag !== null) {
            $response->setEtag($etag);
        }
        if ($lastModified !== null) {
            $response->setLastModified($lastModified);
        }
    }
}
