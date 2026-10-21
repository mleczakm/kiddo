<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Repository\SettingRepositoryInterface;
use App\Entity\User;
use Symfony\Component\Uid\Ulid;

final readonly class RecommendationManager
{
    public function __construct(
        private SettingRepositoryInterface $settings,
        private InAppNotificationService $notifications,
    ) {}

    /**
     * @param list<array{id: string, author: string, text: string, source: string, enabled: bool, status: string}> $reviews
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function replace(array $reviews, string $revision): void
    {
        $validated = RecommendationProvider::validate($reviews);
        $this->settings->updateContent(
            RecommendationProvider::SETTING_KEY,
            /** @throws \InvalidArgumentException|\RuntimeException */ static function (array $content) use (
                $validated,
                $revision,
            ): array {
                $current = RecommendationProvider::validate($content['reviews'] ?? []);
                if (RecommendationProvider::revisionOf($current) !== $revision) {
                    throw new \RuntimeException(
                        'Opinie zmieniły się w międzyczasie. Odśwież stronę przed zapisaniem, aby nie nadpisać nowej opinii.',
                    );
                }
                return ['reviews' => $validated];
            },
        );
    }

    /** @throws \InvalidArgumentException */
    public function submit(User $user, string $text): void
    {
        $userId = $user->getId();
        if ($userId === null) {
            throw new \InvalidArgumentException('Opinię może dodać wyłącznie zapisany użytkownik.');
        }
        $id = 'user-' . $userId;
        $review = [
            'id' => $id,
            'author' => $user->getName() !== '' ? $user->getName() : 'Rodzic',
            'text' => $text,
            'source' => 'kiddo',
            'status' => 'draft',
            'enabled' => false,
        ];
        $validated = RecommendationProvider::validate([$review])[0];
        $this->settings->updateContent(
            RecommendationProvider::SETTING_KEY,
            /** @throws \InvalidArgumentException */ function (array $content) use ($id, $validated): array {
                $current = RecommendationProvider::validate($content['reviews'] ?? []);
                foreach ($current as $row) {
                    if ($row['id'] === $id) {
                        throw new \InvalidArgumentException('Twoja opinia została już przesłana. Dziękujemy!');
                    }
                }
                $current[] = $validated;
                $this->notifications->notifyAdmins(
                    'Nowa opinia do zatwierdzenia',
                    $validated['author'] . ' przesyła opinię. Sprawdź ją przed publikacją.',
                    '/admin/ustawienia?tab=recommendations',
                    push: true,
                );
                return ['reviews' => $current];
            },
        );
    }

    /**
     * @param array<string, mixed> $review
     * @throws \InvalidArgumentException
     */
    public function upsert(array $review): string
    {
        $review['id'] ??= (string) new Ulid();
        $validated = RecommendationProvider::validate([$review])[0];
        $this->settings->updateContent(
            RecommendationProvider::SETTING_KEY,
            /** @throws \InvalidArgumentException */ static function (array $content) use ($validated): array {
                $reviews = RecommendationProvider::validate($content['reviews'] ?? []);
                $reviews = array_values(array_filter(
                    $reviews,
                    static fn(array $row): bool => $row['id'] !== $validated['id'],
                ));
                $reviews[] = $validated;
                return ['reviews' => $reviews];
            },
        );
        return $validated['id'];
    }

    /** @throws \InvalidArgumentException */
    public function moderate(string $id, string $action): void
    {
        $this->settings->updateContent(
            RecommendationProvider::SETTING_KEY,
            /** @throws \InvalidArgumentException */ static function (array $content) use ($id, $action): array {
                $reviews = RecommendationProvider::validate($content['reviews'] ?? []);
                foreach ($reviews as $index => $row) {
                    if ($row['id'] !== $id) {
                        continue;
                    }
                    if ($action === 'delete') {
                        unset($reviews[$index]);
                    } else {
                        $reviews[$index]['status'] = 'published';
                        $reviews[$index]['enabled'] = true;
                    }
                    return ['reviews' => array_values($reviews)];
                }
                throw new \InvalidArgumentException('Nie znaleziono opinii.');
            },
        );
    }
}
