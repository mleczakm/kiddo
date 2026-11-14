<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthRegistration $registration,
        private readonly AuthCodeRequest $codeRequest,
        private readonly AuthCodeVerification $codeVerification,
    ) {}

    /**
     * @throws \Random\RandomException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(Request $request, ValidatorInterface $validator): JsonResponse
    {
        return $this->registration->handle($request, $validator);
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \InvalidArgumentException
     * @throws \Random\RandomException
     */
    #[Route('/verify', name: 'api_auth_verify', methods: ['POST'])]
    public function verify(Request $request, ValidatorInterface $validator): JsonResponse
    {
        return $this->codeVerification->verify($request, $validator);
    }

    /**
     * @throws \Random\RandomException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    #[Route('/send-code', name: 'api_auth_send_code', methods: ['POST'])]
    public function sendCode(Request $request, ValidatorInterface $validator): JsonResponse
    {
        return $this->codeRequest->handle($request, $validator);
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \InvalidArgumentException
     * @throws \Random\RandomException
     */
    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(Request $request, ValidatorInterface $validator): JsonResponse
    {
        return $this->codeVerification->login($request, $validator);
    }
}
