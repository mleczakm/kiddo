<?php

declare(strict_types=1);

namespace App\Infrastructure\Brevo;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class BrevoNewsletterService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(BREVO_API_KEY)%')]
        private string $apiKey,
        #[Autowire('%env(int:BREVO_NEWSLETTER_LIST_ID)%')]
        private int $newsletterListId,
        #[Autowire('%env(int:BREVO_DOI_TEMPLATE_ID)%')]
        private int $doiTemplateId,
        #[Autowire('%env(BREVO_DOI_REDIRECTION_URL)%')]
        private string $doiRedirectionUrl,
    ) {}

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->newsletterListId > 0;
    }

    /**
     * Add or update a contact in Brevo (for logged-in users, no DOI required).
     *
     * @param array<string, mixed> $attributes Additional contact attributes
     */
    public function addOrUpdateContact(string $email, ?string $name = null, array $attributes = []): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Brevo is not configured (BREVO_API_KEY / BREVO_NEWSLETTER_LIST_ID)');
        }

        $data = [
            'email' => $email,
            'listIds' => [$this->newsletterListId],
            'updateEnabled' => true,
        ];

        if ($name !== null) {
            $data['attributes'] = array_merge($attributes, [
                'FIRSTNAME' => $name,
            ]);
        } elseif (!empty($attributes)) {
            $data['attributes'] = $attributes;
        }

        $this->httpClient->request('POST', 'https://api.brevo.com/v3/contacts', [
            'headers' => [
                'api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => $data,
        ]);
    }

    /**
     * Remove a contact from the newsletter list.
     */
    public function removeContactFromList(string $email): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Brevo is not configured (BREVO_API_KEY / BREVO_NEWSLETTER_LIST_ID)');
        }

        $this->httpClient->request('POST', sprintf('https://api.brevo.com/v3/contacts/%s/removeList', $email), [
            'headers' => [
                'api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'listIds' => [$this->newsletterListId],
            ],
        ]);
    }

    /**
     * Send Double Opt-In confirmation email (for homepage guests).
     */
    public function sendDoubleOptInConfirmation(string $email): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException(
                'Brevo is not configured (BREVO_API_KEY / BREVO_NEWSLETTER_LIST_ID / BREVO_DOI_TEMPLATE_ID)',
            );
        }

        $this->httpClient->request('POST', 'https://api.brevo.com/v3/doubleOptInConfirmations', [
            'headers' => [
                'api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'email' => $email,
                'includeListIds' => [$this->newsletterListId],
                'templateId' => $this->doiTemplateId,
                'redirectionUrl' => $this->doiRedirectionUrl,
            ],
        ]);
    }
}
