<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp;

use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Implementation;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\InitializeRequest;
use Mcp\Schema\Result\InitializeResult;
use Mcp\Schema\ServerCapabilities;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Negotiates MCP protocol version for ElevenLabs and other Streamable HTTP clients.
 *
 * The SDK default falls back to 2025-11-25 when no version is configured, which breaks
 * clients that request 2025-03-26. This handler echoes a supported client version.
 *
 * @implements RequestHandlerInterface<InitializeResult>
 */
final readonly class KiddoInitializeHandler implements RequestHandlerInterface
{
    public function __construct(
        #[Autowire('%mcp.app%')]
        private string $app,
        #[Autowire('%mcp.version%')]
        private string $version,
        #[Autowire('%mcp.description%')]
        private ?string $description,
        #[Autowire('%mcp.instructions%')]
        private ?string $instructions,
    ) {}

    public function supports(Request $request): bool
    {
        return $request instanceof InitializeRequest;
    }

    /**
     * @return Response<InitializeResult>
     */
    public function handle(Request $request, SessionInterface $session): Response
    {
        \assert($request instanceof InitializeRequest);

        $session->set('client_info', $request->clientInfo->jsonSerialize());
        $session->set('client_capabilities', $request->capabilities->jsonSerialize());
        $session->set('protocol_version', $request->protocolVersion);

        $negotiated = $this->negotiateProtocolVersion($request->protocolVersion);
        $session->set('negotiated_protocol_version', $negotiated->value);

        return new Response(
            $request->getId(),
            new InitializeResult(
                new ServerCapabilities(
                    tools: true,
                    toolsListChanged: true,
                    resources: true,
                    resourcesSubscribe: true,
                    resourcesListChanged: true,
                    prompts: true,
                    promptsListChanged: true,
                    logging: true,
                    completions: true,
                ),
                new Implementation($this->app, $this->version, $this->description),
                $this->instructions,
                null,
                $negotiated,
            ),
        );
    }

    private function negotiateProtocolVersion(string $clientVersion): ProtocolVersion
    {
        return ProtocolVersion::tryFrom($clientVersion) ?? ProtocolVersion::V2025_03_26;
    }
}
