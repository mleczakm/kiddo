<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Api;

use App\Application\Service\AuthVerificationService;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class AuthRegistration
{
    public function __construct(
        private UserRepository $userRepository,
        private AuthRateLimits $limits,
        private AuthVerificationService $verification,
        private EntityManagerInterface $entityManager,
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
            'name' => [new Assert\NotBlank(), new Assert\Length(min: 2, max: 255)],
            'phone' => new Assert\Optional([new Assert\NotBlank()]),
        ]);

        $violations = $validator->validate($data, $constraints);
        if (count($violations) > 0) {
            return new JsonResponse([
                'error' => 'Validation failed',
                'violations' => (string) $violations,
            ], 400);
        }

        $email = $this->stringField($data, 'email');
        $name = $this->stringField($data, 'name');
        if ($email === null || $name === null) {
            return new JsonResponse([
                'error' => 'Validation failed',
            ], 400);
        }

        if ($this->userRepository->findOneBy([
            'email' => $email,
        ])) {
            return new JsonResponse([
                'error' => 'Email already registered',
            ], 409);
        }

        $emailLimitResponse = $this->limits->checkEmailLimit($email);
        if ($emailLimitResponse !== null) {
            return $emailLimitResponse;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setName($name);
        $user->setRoles(['ROLE_USER']);

        $phoneField = $this->stringField($data, 'phone');
        if ($phoneField !== null && $phoneField !== '') {
            try {
                $phone = PhoneNumberUtil::getInstance()->parse($phoneField, 'PL');
                $user->setPhone($phone);
            } catch (NumberParseException) {
                return new JsonResponse([
                    'error' => 'Invalid phone number',
                ], 400);
            }
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->verification->issue($user->getEmail());

        return new JsonResponse([
            'message' => 'Registration successful. Please verify your email.',
            'user_id' => $user->getId(),
            'requires_verification' => true,
        ], 201);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function stringField(array $data, string $key): ?string
    {
        return is_string($data[$key] ?? null) ? $data[$key] : null;
    }
}
