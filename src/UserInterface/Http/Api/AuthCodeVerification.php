<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Api;

use App\Application\Chat\ChatTokenManager;
use App\Application\Service\AuthVerificationService;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class AuthCodeVerification
{
    public function __construct(
        private AuthRateLimits $limits,
        private AuthVerificationService $verification,
        private EntityManagerInterface $entityManager,
        private ChatTokenManager $tokenManager,
    ) {}

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \InvalidArgumentException
     * @throws \Random\RandomException
     */
    public function verify(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $user = $this->redeem($request, $validator);

        return $user instanceof User ? $this->successResponse($user, 'Email verified') : $user;
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \InvalidArgumentException
     * @throws \Random\RandomException
     */
    public function login(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $user = $this->redeem($request, $validator);
        if (!$user instanceof User) {
            return $user;
        }

        $user->setLastLoginAt(Clock::get()->now());
        $this->entityManager->flush();

        return $this->successResponse($user, 'Login successful');
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \InvalidArgumentException
     * @throws \Random\RandomException
     */
    private function redeem(Request $request, ValidatorInterface $validator): User|JsonResponse
    {
        $ipLimitResponse = $this->limits->checkIpLimit($request);
        if ($ipLimitResponse !== null) {
            return $ipLimitResponse;
        }

        /** @var mixed $data */
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse([
                'error' => 'Invalid JSON',
            ], 400);
        }

        $constraints = new Assert\Collection([
            'email' => [new Assert\NotBlank(), new Assert\Email()],
            'code' => [new Assert\NotBlank(), new Assert\Length(exactly: 6)],
        ]);

        $violations = $validator->validate($data, $constraints);
        if (count($violations) > 0) {
            return new JsonResponse([
                'error' => 'Validation failed',
            ], 400);
        }

        $email = $this->stringField($data, 'email');
        $code = $this->stringField($data, 'code');
        if ($email === null || $code === null) {
            return new JsonResponse([
                'error' => 'Validation failed',
            ], 400);
        }

        $emailLimitResponse = $this->limits->checkVerificationLimit($email);
        if ($emailLimitResponse !== null) {
            return $emailLimitResponse;
        }

        $user = $this->verification->consume($email, $code);
        if ($user === null) {
            return new JsonResponse([
                'error' => 'Invalid or expired code',
            ], 400);
        }

        return $user;
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \Random\RandomException
     */
    private function successResponse(User $user, string $message): JsonResponse
    {
        $chatToken = $this->tokenManager->mint($user);

        return new JsonResponse([
            'message' => $message,
            'chat_token' => $chatToken,
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * @return array{id: int|null, email: string, name: string}
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function stringField(array $data, string $key): ?string
    {
        return is_string($data[$key] ?? null) ? $data[$key] : null;
    }
}
