<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Api;

use App\Application\Service\AuthVerificationService;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class AuthCodeRequest
{
    public function __construct(
        private UserRepository $userRepository,
        private AuthRateLimits $limits,
        private AuthVerificationService $verification,
    ) {}

    /**
     * @throws \Random\RandomException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    public function handle(Request $request, ValidatorInterface $validator): JsonResponse
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
        ]);

        $violations = $validator->validate($data, $constraints);
        if (count($violations) > 0) {
            return new JsonResponse([
                'error' => 'Validation failed',
            ], 400);
        }

        $email = $this->stringField($data, 'email');
        if ($email === null) {
            return new JsonResponse([
                'error' => 'Validation failed',
            ], 400);
        }

        $emailLimitResponse = $this->limits->checkEmailLimit($email);
        if ($emailLimitResponse !== null) {
            return $emailLimitResponse;
        }

        $user = $this->userRepository->findOneBy([
            'email' => $email,
        ]);
        if (!$user) {
            return new JsonResponse([
                'error' => 'User not found',
            ], 404);
        }

        $this->verification->issue($user->getEmail());

        return new JsonResponse([
            'message' => 'Verification code sent',
        ]);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function stringField(array $data, string $key): ?string
    {
        return is_string($data[$key] ?? null) ? $data[$key] : null;
    }
}
