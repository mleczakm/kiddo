<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Entity\Post;

final readonly class PostSeoEditor
{
    /** @throws \InvalidArgumentException */
    public function updateSeo(Post $post, PostSeoInput $seo, PostSocialInput $social): void
    {
        $seoTitle = $this->nullableTrim($seo->seoTitle);
        $seoDescription = $this->nullableTrim($seo->seoDescription);
        $canonicalUrl = $this->nullableTrim($seo->canonicalUrl);
        $socialTitle = $this->nullableTrim($social->socialTitle);
        $socialDescription = $this->nullableTrim($social->socialDescription);

        $this->assertMaxLength($seoTitle, 70, 'SEO title cannot exceed 70 characters.');
        $this->assertMaxLength($seoDescription, 170, 'SEO description cannot exceed 170 characters.');
        $this->assertMaxLength($socialTitle, 70, 'Social title cannot exceed 70 characters.');
        $this->assertMaxLength($socialDescription, 200, 'Social description cannot exceed 200 characters.');
        if ($canonicalUrl !== null) {
            $this->assertAbsoluteHttpsUrl($canonicalUrl);
        }

        $post->seo->updateSearch($seoTitle, $seoDescription, $canonicalUrl, $seo->robotsIndex, $seo->robotsFollow);
        $post->seo->updateSocial($socialTitle, $socialDescription);
        $post->markUpdated();
    }

    /** @throws \InvalidArgumentException */
    private function assertMaxLength(?string $value, int $max, string $message): void
    {
        if ($value !== null && mb_strlen($value) > $max) {
            throw new \InvalidArgumentException($message);
        }
    }

    /** @throws \InvalidArgumentException */
    private function assertAbsoluteHttpsUrl(string $url): void
    {
        if (!str_starts_with($url, 'https://') || filter_var($url, \FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('Canonical URL must be an absolute https URL.');
        }
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);
        return $value === '' ? null : $value;
    }
}
