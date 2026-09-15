<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Api;

use App\Application\Repository\PushSubscriptionRepositoryInterface;
use App\Entity\PushSubscription;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/push')]
final class PushSubscriptionController extends AbstractController
{
    public function __construct(
        private readonly PushSubscriptionRepositoryInterface $subscriptions,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/subscribe', name: 'api_push_subscribe', methods: ['POST'])]
    public function subscribe(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || count($validator->validate($data, $this->subscribeConstraints())) > 0) {
            return $this->json(['error' => 'Invalid subscription'], Response::HTTP_BAD_REQUEST);
        }

        /** @var array{endpoint: string, keys: array{p256dh: string, auth: string}} $data */
        $endpoint = $data['endpoint'];
        $p256dh = $data['keys']['p256dh'];
        $auth = $data['keys']['auth'];

        $subscription = $this->subscriptions->findByEndpoint($endpoint);
        if ($subscription instanceof PushSubscription) {
            $subscription->updateKeys($p256dh, $auth);
            $this->em->flush();

            return $this->json(['ok' => true]);
        }

        $this->em->persist(new PushSubscription($user, $endpoint, $p256dh, $auth));
        $this->em->flush();

        return $this->json(['ok' => true]);
    }

    #[Route('/unsubscribe', name: 'api_push_unsubscribe', methods: ['POST'])]
    public function unsubscribe(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $constraints = new Assert\Collection(['endpoint' => [new Assert\NotBlank()]]);
        if (!is_array($data) || count($validator->validate($data, $constraints)) > 0) {
            return $this->json(['error' => 'Invalid request'], Response::HTTP_BAD_REQUEST);
        }

        /** @var array{endpoint: string} $data */
        $endpoint = $data['endpoint'];

        $subscription = $this->subscriptions->findByEndpoint($endpoint);
        if ($subscription instanceof PushSubscription && $subscription->getUser()->getId() === $user->getId()) {
            $this->em->remove($subscription);
            $this->em->flush();
        }

        return $this->json(['ok' => true]);
    }

    private function subscribeConstraints(): Assert\Collection
    {
        return new Assert\Collection([
            'endpoint' => [new Assert\NotBlank(), new Assert\Url()],
            'keys' => new Assert\Collection([
                'p256dh' => [new Assert\NotBlank()],
                'auth' => [new Assert\NotBlank()],
            ]),
        ]);
    }
}
