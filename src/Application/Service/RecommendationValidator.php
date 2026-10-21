<?php

declare(strict_types=1);

namespace App\Application\Service;

final class RecommendationValidator
{
    /**
     * @return array{id: string, author: string, text: string, source: string, enabled: bool, status: string}
     * @throws \InvalidArgumentException
     */
    public static function normalize(mixed $review, int $position): array
    {
        if (
            !is_array($review)
            || !in_array($review['source'] ?? null, ['google', 'facebook', 'kiddo'], true)
            || !is_bool($review['enabled'] ?? null)
        ) {
            throw new \InvalidArgumentException(sprintf(
                'Opinia %d: podaj źródło Google, Facebook lub Kiddo oraz widoczność.',
                $position,
            ));
        }
        $text = [];
        foreach (['author' => 200, 'text' => 10_000] as $field => $limit) {
            $value = $review[$field] ?? null;
            if (!is_string($value) || trim($value) === '' || mb_strlen($value) > $limit) {
                throw new \InvalidArgumentException(sprintf(
                    'Opinia %d: podaj autora (do 200 znaków) i treść (do 10 000 znaków).',
                    $position,
                ));
            }
            $text[$field] = trim($value);
        }
        $status = $review['status'] ?? ($review['source'] === 'kiddo' ? 'draft' : 'published');
        if (!in_array($status, ['draft', 'published'], true)) {
            throw new \InvalidArgumentException('Status opinii musi być draft lub published.');
        }
        return [
            'id' => is_string($review['id'] ?? null)
                ? $review['id']
                : hash('sha256', $text['author'] . "\0" . $text['text']),
            'status' => $status,
            'author' => $text['author'],
            'text' => $text['text'],
            'source' => $review['source'],
            'enabled' => $review['enabled'],
        ];
    }
}
