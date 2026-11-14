<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp;

use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\AI\McpBundle\Http\MiddlewareFactory;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

final readonly class McpHttpTransportFactory
{
    public function __construct(
        private HttpMessageFactoryInterface $httpMessageFactory,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private MiddlewareFactory $middlewareFactory,
    ) {}

    /** @throws \InvalidArgumentException */
    public function create(Request $request, string $content, LoggerInterface $logger): StreamableHttpTransport
    {
        $psrRequest = $this->httpMessageFactory
            ->createRequest($request)
            ->withBody($this->streamFactory->createStream($content));

        return new StreamableHttpTransport(
            $psrRequest,
            $this->responseFactory,
            $this->streamFactory,
            logger: $logger,
            middleware: $this->middlewareFactory->create(),
        );
    }
}
