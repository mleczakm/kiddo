<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Api;

use App\Application\Chat\ChatTokenManager;
use App\Application\Service\AuthVerificationService;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\Clock;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ChatTokenManager $tokenManager,
        #[Autowire(service: 'limiter.auth_email_limiter')]
        private readonly RateLimiterFactory $authEmailRateLimiter,
        #[Autowire(service: 'limiter.auth_ip_limiter')]
        private readonly RateLimiterFactory $authIpRateLimiter,
        private readonly AuthVerificationService $verification,
    ) {}

    /**
     * @throws \Random\RandomException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $ipLimitResponse = $this->checkIpLimit($request);
        if ($ipLimitResponse !== null) {
            return $ipLimitResponse;
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json([
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
            return $this->json([
                'error' => 'Validation failed',
                'violations' => (string) $violations,
            ], 400);
        }

        $email = $this->stringField($data, 'email');
        $name = $this->stringField($data, 'name');
        if ($email === null || $name === null) {
            return $this->json([
                'error' => 'Validation failed',
            ], 400);
        }

        if ($this->userRepository->findOneBy([
            'email' => $email,
        ])) {
            return $this->json([
                'error' => 'Email already registered',
            ], 409);
        }

        $emailLimitResponse = $this->checkEmailLimit($email);
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
                return $this->json([
                    'error' => 'Invalid phone number',
                ], 400);
            }
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->verification->issue($user->getEmail());

        return $this->json([
            'message' => 'Registration successful. Please verify your email.',
            'user_id' => $user->getId(),
            'requires_verification' => true,
        ], 201);
    }

    /** @throws \Psr\Cache\InvalidArgumentException */
    #[Route('/verify', name: 'api_auth_verify', methods: ['POST'])]
    public function verify(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $ipLimitResponse = $this->checkIpLimit($request);
        if ($ipLimitResponse !== null) {
            return $ipLimitResponse;
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json([
                'error' => 'Invalid JSON',
            ], 400);
        }

        $constraints = new Assert\Collection([
            'email' => [new Assert\NotBlank(), new Assert\Email()],
            'code' => [new Assert\NotBlank(), new Assert\Length(exactly: 6)],
        ]);

        $violations = $validator->validate($data, $constraints);
        if (count($violations) > 0) {
            return $this->json([
                'error' => 'Validation failed',
            ], 400);
        }

        $email = $this->stringField($data, 'email');
        $code = $this->stringField($data, 'code');
        if ($email === null || $code === null) {
            return $this->json([
                'error' => 'Validation failed',
            ], 400);
        }

        $user = $this->verification->consume($email, $code);
        if ($user === null) {
            return $this->json([
                'error' => 'Invalid or expired code',
            ], 400);
        }

        $chatToken = $this->tokenManager->mint($user);

        return $this->json([
            'message' => 'Email verified',
            'chat_token' => $chatToken,
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * @throws \Random\RandomException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    #[Route('/send-code', name: 'api_auth_send_code', methods: ['POST'])]
    public function sendCode(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json([
                'error' => 'Invalid JSON',
            ], 400);
        }

        $constraints = new Assert\Collection([
            'email' => [new Assert\NotBlank(), new Assert\Email()],
        ]);

        $violations = $validator->validate($data, $constraints);
        if (count($violations) > 0) {
            return $this->json([
                'error' => 'Validation failed',
            ], 400);
        }

        $email = $this->stringField($data, 'email');
        if ($email === null) {
            return $this->json([
                'error' => 'Validation failed',
            ], 400);
        }

        $emailLimitResponse = $this->checkEmailLimit($email);
        if ($emailLimitResponse !== null) {
            return $emailLimitResponse;
        }

        $user = $this->userRepository->findOneBy([
            'email' => $email,
        ]);
        if (!$user) {
            return $this->json([
                'error' => 'User not found',
            ], 404);
        }

        $this->verification->issue($user->getEmail());

        return $this->json([
            'message' => 'Verification code sent',
        ]);
    }

    /** @throws \Psr\Cache\InvalidArgumentException */
    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $ipLimitResponse = $this->checkIpLimit($request);
        if ($ipLimitResponse !== null) {
            return $ipLimitResponse;
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json([
                'error' => 'Invalid JSON',
            ], 400);
        }

        $constraints = new Assert\Collection([
            'email' => [new Assert\NotBlank(), new Assert\Email()],
            'code' => [new Assert\NotBlank(), new Assert\Length(exactly: 6)],
        ]);

        $violations = $validator->validate($data, $constraints);
        if (count($violations) > 0) {
            return $this->json([
                'error' => 'Validation failed',
            ], 400);
        }

        $email = $this->stringField($data, 'email');
        $code = $this->stringField($data, 'code');
        if ($email === null || $code === null) {
            return $this->json([
                'error' => 'Validation failed',
            ], 400);
        }

        $user = $this->verification->consume($email, $code);
        if ($user === null) {
            return $this->json([
                'error' => 'Invalid or expired code',
            ], 400);
        }

        $user->setLastLoginAt(Clock::get()->now());
        $this->entityManager->flush();

        $chatToken = $this->tokenManager->mint($user);

        return $this->json([
            'message' => 'Login successful',
            'chat_token' => $chatToken,
            'user' => $this->userPayload($user),
        ]);
    }

    private function checkEmailLimit(string $email): ?JsonResponse
    {
        $limiter = $this->authEmailRateLimiter->create($email);
        $limit = $limiter->consume(1);
        if (!$limit->isAccepted()) {
            return $this->json([
                'error' => 'Too many code requests. Please try again later.',
                'retry_after' => $limit->getRetryAfter()->getTimestamp() - time(),
            ], 429);
        }

        return null;
    }

    private function checkIpLimit(Request $request): ?JsonResponse
    {
        $limiter = $this->authIpRateLimiter->create($request->getClientIp());
        $limit = $limiter->consume(1);
        if (!$limit->isAccepted()) {
            return $this->json([
                'error' => 'Too many attempts. Please try again later.',
                'retry_after' => $limit->getRetryAfter()->getTimestamp() - time(),
            ], 429);
        }

        return null;
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
