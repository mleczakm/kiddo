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

    /** @return list<array{id: string, author: string, text: string, source: string, enabled: bool, status: string}> */
    public function getAll(): array
    {
        $content = $this->settings->findOneByKey(self::SETTING_KEY)?->getContent();

        try {
            return self::validate($content['reviews'] ?? []);
        } catch (\InvalidArgumentException) {
            return [];
        }
    }

    /** @return list<array{id: string, author: string, text: string, source: string, enabled: bool, status: string}> */
    public function getPublished(): array
    {
        return array_values(array_filter(
            $this->getAll(),
            static fn(array $review): bool => $review['enabled'] && $review['status'] === 'published',
        ));
    }

    /**
     * @return list<array{id: string, author: string, text: string, source: string, enabled: bool, status: string}>
     * @throws \InvalidArgumentException
     */
    public static function validate(mixed $reviews): array
    {
        if (!is_array($reviews) || !array_is_list($reviews)) {
            throw new \InvalidArgumentException('Opinie muszą być listą.');
        }
        $validated = [];
        foreach ($reviews as $index => $review) {
            $validated[] = RecommendationValidator::normalize($review, $index + 1);
        }
        if (count(array_unique(array_column($validated, 'id'))) !== count($validated)) {
            throw new \InvalidArgumentException('Każda opinia musi mieć inny identyfikator.');
        }
        return $validated;
    }

    /** @return list<array{id: string, author: string, text: string, source: string, enabled: bool, status: string}> */
    public function search(string $query = '', ?string $status = null): array
    {
        $query = mb_strtolower(trim($query));
        return array_values(array_filter(
            $this->getAll(),
            static fn(array $row): bool => (
                ($status === null || $row['status'] === $status)
                && ($query === '' || str_contains(mb_strtolower($row['author'] . ' ' . $row['text']), $query))
            ),
        ));
    }

    public function revision(): string
    {
        return self::revisionOf($this->getAll());
    }

    /** @param list<array{id: string, author: string, text: string, source: string, enabled: bool, status: string}> $reviews */
    public static function revisionOf(array $reviews): string
    {
        return hash('sha256', serialize($reviews));
    }
}
