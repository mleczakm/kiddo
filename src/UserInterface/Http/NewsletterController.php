<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Application\Consent\MarketingConsentManager;
use App\Application\Newsletter\InvalidNewsletterSubscription;
use App\Application\Newsletter\NewsletterSubscriptionRequestParser;
use App\Entity\ConsentSource;
use App\Entity\User;
use App\Infrastructure\Brevo\BrevoNewsletterService;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class NewsletterController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /** @throws \LogicException */
    #[Route('/api/newsletter/subscribe', name: 'newsletter_subscribe', methods: ['POST'])]
    public function subscribe(
        Request $request,
        NewsletterSubscriptionRequestParser $requestParser,
        UserRepository $userRepository,
        BrevoNewsletterService $brevoNewsletterService,
        MarketingConsentManager $marketingConsentManager,
    ): JsonResponse {
        try {
            $input = $requestParser->parse($request);
        } catch (InvalidNewsletterSubscription $exception) {
            return new JsonResponse([
                'error' => $exception->getMessage(),
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        if ($input->spam) {
            return new JsonResponse([
                'success' => true,
            ], JsonResponse::HTTP_OK);
        }

        $email = $input->email;

        $authenticatedUser = $this->getUser();
        $consentingUser =
            $authenticatedUser instanceof User && $authenticatedUser->getEmail() === $email ? $authenticatedUser : null;

        // If the email already belongs to a registered user with an active newsletter
        // subscription in our DB, skip the DOI round-trip.
        // Note: the DOI callback is a plain redirection URL and cannot securely be
        // tied back to a User row, so we intentionally do NOT update the User's
        // newsletterSubscribed flag from this flow — Brevo is the source of truth
        // for guest signups. Existing users manage their preference in the profile.
        $existingUser = $userRepository->findOneBy([
            'email' => $email,
        ]);
        if ($existingUser !== null && $existingUser->isNewsletterSubscribed()) {
            if ($consentingUser !== null) {
                $marketingConsentManager->grant($consentingUser, ConsentSource::NEWSLETTER_FORM);
            }

            return new JsonResponse([
                'message' => 'newsletter.already_subscribed',
            ], JsonResponse::HTTP_OK);
        }

        try {
            $brevoNewsletterService->sendDoubleOptInConfirmation($email, [
                'CONSENT_VERSION' => MarketingConsentManager::VERSION,
                'CONSENT_SOURCE' => ConsentSource::NEWSLETTER_FORM->value,
                'CONSENT_TEXT_SHA256' => hash('sha256', $marketingConsentManager->text()),
            ]);
        } catch (\RuntimeException|TransportExceptionInterface $exception) {
            $this->logger->error('Failed to send Brevo double opt-in confirmation', [
                'email' => $email,
                'exception' => $exception,
            ]);

            return new JsonResponse([
                'error' => 'newsletter.service_error',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($consentingUser !== null) {
            $marketingConsentManager->grant($consentingUser, ConsentSource::NEWSLETTER_FORM);
        }

        return new JsonResponse([
            'message' => 'newsletter.confirmation_sent',
        ], JsonResponse::HTTP_OK);
    }
}
