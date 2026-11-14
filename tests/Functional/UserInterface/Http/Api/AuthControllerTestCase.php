<?php

declare(strict_types=1);

namespace App\Tests\Functional\UserInterface\Http\Api;

use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use App\UserInterface\Http\Api\AuthController;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Zenstruck\Mailer\Test\InteractsWithMailer;

/**
 * Calls AuthController directly (not through the HTTP test client): the client's kernel.terminate
 * cycle resets the in-memory rate-limiter/verification-code cache pools between requests in the
 * test env, which would wipe state a multi-step register -> verify flow depends on.
 */
abstract class AuthControllerTestCase extends WebTestCase
{
    use InteractsWithMailer;

    protected AuthController $controller;

    protected ValidatorInterface $validator;

    protected CacheItemPoolInterface $appCache;

    protected CacheItemPoolInterface $rateLimiterCache;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        static::bootKernel();
        $container = static::getContainer();
        $controller = $container->get(AuthController::class);
        static::assertInstanceOf(AuthController::class, $controller);
        $this->controller = $controller;
        $validator = $container->get(ValidatorInterface::class);
        static::assertInstanceOf(ValidatorInterface::class, $validator);
        $this->validator = $validator;
        $appCache = $container->get('cache.app');
        static::assertInstanceOf(CacheItemPoolInterface::class, $appCache);
        $this->appCache = $appCache;
        $rateLimiterCache = $container->get('cache.rate_limiter');
        static::assertInstanceOf(CacheItemPoolInterface::class, $rateLimiterCache);
        $this->rateLimiterCache = $rateLimiterCache;
        $this->appCache->clear();
        $this->rateLimiterCache->clear();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->appCache->clear();
        $this->rateLimiterCache->clear();

        parent::tearDown();
    }

    protected function findUser(string $email): ?User
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        return $userRepository->findOneBy([
            'email' => $email,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function register(array $payload): JsonResponse
    {
        return $this->controller->register($this->jsonRequest($payload), $this->validator);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function verify(array $payload): JsonResponse
    {
        return $this->controller->verify($this->jsonRequest($payload), $this->validator);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function login(array $payload): JsonResponse
    {
        return $this->controller->login($this->jsonRequest($payload), $this->validator);
    }

    protected function sendCode(string $email): JsonResponse
    {
        return $this->controller->sendCode($this->jsonRequest([
            'email' => $email,
        ]), $this->validator);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function jsonRequest(array $payload): Request
    {
        return Request::create(
            uri: '/api/auth/_test',
            method: 'POST',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function decode(JsonResponse $response): array
    {
        /** @var array<string, mixed> */
        return json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Matches the first run of 6 digits, so callers must pass the plain-text body
     * first: the HTML body's inline CSS can contain an all-numeric 6-digit hex
     * color (e.g. #583818) ahead of the real code.
     */
    protected function extractCode(string $body): string
    {
        self::assertMatchesRegularExpression('/\d{6}/', $body);
        $matches = [];
        preg_match('/\d{6}/', $body, $matches);

        return $matches[0] ?? '';
    }
}
