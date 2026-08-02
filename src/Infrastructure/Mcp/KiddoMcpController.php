<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp;

use Mcp\Server;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\AI\McpBundle\Http\MiddlewareFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MCP HTTP controller that rebuilds the PSR-7 body from Symfony's buffered content.
 *
 * The default Symfony PSR bridge uses getContent(true) + createStreamFromResource(),
 * which can yield an empty body after php://input is consumed — MCP then returns
 * JSON-RPC -32700 "Syntax error".
 */
final readonly class KiddoMcpController
{
    public function __construct(
        private Server $server,
        private HttpMessageFactoryInterface $httpMessageFactory,
        private HttpFoundationFactoryInterface $httpFoundationFactory,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private MiddlewareFactory $middlewareFactory,
        private LoggerInterface $logger,
    ) {}

    public function handle(Request $request): Response
    {
        $content = $this->normalizeBody($request->getContent());

        $this->logger->info('MCP HTTP request', [
            'method' => $request->getMethod(),
            'body_bytes' => strlen($content),
            'content_type' => $request->headers->get('Content-Type'),
            'accept' => $request->headers->get('Accept'),
            'has_session' => $request->headers->has('Mcp-Session-Id'),
            'looks_like_json' => $content === '' || str_starts_with(ltrim($content), '{') || str_starts_with(
                ltrim($content),
                '['
            ),
        ]);

        $psrRequest = $this->httpMessageFactory
            ->createRequest($request)
            ->withBody($this->streamFactory->createStream($content));

        $transport = new StreamableHttpTransport(
            $psrRequest,
            $this->responseFactory,
            $this->streamFactory,
            logger: $this->logger,
            middleware: $this->middlewareFactory->create(),
        );

        $psrResponse = $this->server->run($transport);
        $streamed = strtolower($psrResponse->getHeaderLine('Content-Type')) === 'text/event-stream';

        return $this->httpFoundationFactory->createResponse($psrResponse, $streamed);
    }

    private function normalizeBody(string $content): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        // Some proxies forward gzip without decompressing.
        if ($content !== '' && str_starts_with($content, "\x1F\x8B")) {
            $decoded = gzdecode($content);
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        return $content;
    }
}
