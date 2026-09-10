<?php

declare(strict_types=1);

namespace App\Application\Newsletter;

use App\Application\Consent\MarketingConsentManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class NewsletterSubscriptionRequestParser
{
    public function __construct(
        private ValidatorInterface $validator,
        private MarketingConsentManager $marketingConsentManager,
    ) {}

    /** @throws InvalidNewsletterSubscription */
    public function parse(Request $request): NewsletterSubscriptionInput
    {
        $content = $request->getContent();
        if ($content === '') {
            throw new InvalidNewsletterSubscription('newsletter.email_required');
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new InvalidNewsletterSubscription('newsletter.email_required');
        }

        if (($data['website'] ?? '') !== '') {
            return new NewsletterSubscriptionInput('', spam: true);
        }

        if (!array_key_exists('email', $data) || !is_string($data['email'])) {
            throw new InvalidNewsletterSubscription('newsletter.email_required');
        }

        $email = mb_strtolower(trim($data['email']));
        $violations = $this->validator->validate($email, [new Assert\NotBlank(), new Assert\Email()]);
        if (count($violations) > 0) {
            throw new InvalidNewsletterSubscription('newsletter.email_invalid');
        }

        if (
            $this->marketingConsentManager->isEnabled()
            && (
                ($data['consent'] ?? null) !== true
                || ($data['consentVersion'] ?? null) !== MarketingConsentManager::VERSION
            )
        ) {
            throw new InvalidNewsletterSubscription('newsletter.consent_required');
        }

        return new NewsletterSubscriptionInput($email);
    }
}
