<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Infrastructure\Brevo\BrevoNewsletterService;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class NewsletterController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    #[Route('/api/newsletter/subscribe', name: 'newsletter_subscribe', methods: ['POST'])]
    public function subscribe(
        Request $request,
        ValidatorInterface $validator,
        UserRepository $userRepository,
        BrevoNewsletterService $brevoNewsletterService,
    ): JsonResponse {
        $content = $request->getContent();
        if ($content === '') {
            return new JsonResponse(
                [
                    'error' => 'newsletter.email_required',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($content, true);
        if (! is_array($data)) {
            return new JsonResponse(
                [
                    'error' => 'newsletter.email_required',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        // Honeypot check - if the honeypot field is filled, silently succeed.
        if (! empty($data['website'] ?? '')) {
            return new JsonResponse([
                'success' => true,
            ], JsonResponse::HTTP_OK);
        }

        $email = $data['email'] ?? null;

        if (! is_string($email)) {
            return new JsonResponse(
                [
                    'error' => 'newsletter.email_required',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $email = mb_strtolower(trim($email));

        $violations = $validator->validate($email, [new Assert\NotBlank(), new Assert\Email()]);

        if (count($violations) > 0) {
            return new JsonResponse(
                [
                    'error' => 'newsletter.email_invalid',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

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
            return new JsonResponse(
                [
                    'message' => 'newsletter.already_subscribed',
                ],
                JsonResponse::HTTP_OK
            );
        }

        try {
            $brevoNewsletterService->sendDoubleOptInConfirmation($email);
        } catch (\RuntimeException|TransportExceptionInterface $exception) {
            $this->logger->error('Failed to send Brevo double opt-in confirmation', [
                'email' => $email,
                'exception' => $exception,
            ]);

            return new JsonResponse(
                [
                    'error' => 'newsletter.service_error',
                ],
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return new JsonResponse(
            [
                'message' => 'newsletter.confirmation_sent',
            ],
            JsonResponse::HTTP_OK
        );
    }
}
