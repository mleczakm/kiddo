<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Repository\SettingRepositoryInterface;

final readonly class RecommendationProvider
{
    public const string SETTING_KEY = 'recommendations';

    public function __construct(
        private SettingRepositoryInterface $settings,
    ) {}

    /** @return list<array{author: string, text: string, source: string, enabled: bool}> */
    public function getAll(): array
    {
        $content = $this->settings->findOneByKey(self::SETTING_KEY)?->getContent();

        try {
            return self::validate($content['reviews'] ?? []);
        } catch (\InvalidArgumentException) {
            return [];
        }
    }

    /** @return list<array{author: string, text: string, source: string, enabled: bool}> */
    public function getPublished(): array
    {
        return array_values(array_filter($this->getAll(), static fn(array $review): bool => $review['enabled']));
    }

    /**
     * @return list<array{author: string, text: string, source: string, enabled: bool}>
     * @throws \InvalidArgumentException
     */
    public static function validate(mixed $reviews): array
    {
        if (!is_array($reviews) || !array_is_list($reviews)) {
            throw new \InvalidArgumentException('Opinie muszą być listą.');
        }

        $validated = [];
        foreach ($reviews as $index => $review) {
            if (
                !is_array($review)
                || !in_array($review['source'] ?? null, ['google', 'facebook'], true)
                || !is_bool($review['enabled'] ?? null)
            ) {
                throw new \InvalidArgumentException(sprintf(
                    'Opinia %d: podaj autora (do 200 znaków), treść (do 10 000 znaków) i źródło Google lub Facebook.',
                    $index + 1,
                ));
            }

            $text = [];
            foreach (['author' => 200, 'text' => 10_000] as $field => $limit) {
                $value = $review[$field] ?? null;
                if (!is_string($value) || trim($value) === '' || mb_strlen($value) > $limit) {
                    throw new \InvalidArgumentException(sprintf(
                        'Opinia %d: podaj autora (do 200 znaków) i treść (do 10 000 znaków).',
                        $index + 1,
                    ));
                }
                $text[$field] = trim($value);
            }

            $validated[] = [
                'author' => $text['author'],
                'text' => $text['text'],
                'source' => $review['source'],
                'enabled' => $review['enabled'],
            ];
        }

        return $validated;
    }
}
