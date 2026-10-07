<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Api;

use App\Application\Service\IncomingBankMailImporterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class BankMailWebhookController
{
    public function __construct(
        private IncomingBankMailImporterInterface $importer,
        private BankMailWebhookRequestValidator $requestValidator,
        private string $bankMailAddress,
        #[\SensitiveParameter]
        private string $bankMailWebhookSecret,
        private string $bankMailFrom,
    ) {}

    /** @throws \InvalidArgumentException */
    #[Route('/api/bank-mail', name: 'api_bank_mail', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        if ($this->bankMailAddress === '' || $this->bankMailFrom === '' || strlen($this->bankMailWebhookSecret) < 32) {
            return $this->response('Bank mail is not configured', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        try {
            $message = $this->requestValidator->validate(
                $request,
                $this->bankMailAddress,
                $this->bankMailFrom,
                $this->bankMailWebhookSecret,
            );
        } catch (BankMailWebhookRequestException $exception) {
            return $this->response($exception->getMessage(), (int) $exception->getCode());
        }

        $this->importer->import($message->id, $message->subject, $message->content, $message->receivedAt);

        return $this->response('', Response::HTTP_NO_CONTENT);
    }

    /** @throws \InvalidArgumentException */
    private function response(string $content, int $status): Response
    {
        return new Response($content, $status, ['Cache-Control' => 'no-store']);
    }
}
