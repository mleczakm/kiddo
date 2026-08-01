<?php

declare(strict_types=1);

namespace App\Application\Telegram;

use App\Application\Chat\ChatActor;
use App\Application\Chat\ChatToolRegistry;
use App\Entity\User;

/**
 * Thin Telegram → ChatToolRegistry adapter (slash commands + a few Polish phrases).
 */
final readonly class TelegramCommandRouter
{
    public function __construct(
        private ChatToolRegistry $registry,
        private TelegramLinkServiceInterface $linkService,
    ) {}

    public function handle(string $telegramChatId, string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return 'Napisz /pomoc aby zobaczyć dostępne komendy.';
        }

        if (str_starts_with($text, '/start')) {
            return "Cześć! Połącz konto Kiddo:\n/polacz email@example.com\n/kod 123456\n\nPotem: /zajecia, /rezerwacje, /pomoc";
        }

        if (str_starts_with($text, '/pomoc') || str_starts_with($text, '/help')) {
            return implode("\n", [
                'Komendy:',
                '/polacz email — wyśle kod na e-mail',
                '/kod 123456 — potwierdź połączenie',
                '/rozlacz — odłącz Telegram',
                '/zajecia [fraza] — nadchodzące zajęcia',
                '/rezerwacje — Twoje rezerwacje',
                '/karnety — karnety',
                '/ja — profil',
                'Admin: /dzisiaj, /przelewy',
            ]);
        }

        if (preg_match('/^\/polacz(?:\s+(\S+))?/iu', $text, $m) === 1) {
            $email = $m[1] ?? '';
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return 'Użycie: /polacz email@example.com';
            }
            $this->linkService->startLink($telegramChatId, $email);

            return 'Jeśli konto istnieje, wysłaliśmy kod na e-mail. Wpisz: /kod XXXXXX';
        }

        if (preg_match('/^\/kod(?:\s+(\d{6}))?/u', $text, $m) === 1) {
            $code = $m[1] ?? '';
            if ($code === '') {
                return 'Użycie: /kod 123456';
            }
            $user = $this->linkService->confirmLink($telegramChatId, $code);
            if ($user === null) {
                return 'Nieprawidłowy lub wygasły kod.';
            }

            return sprintf('Połączono z kontem %s. Możesz używać /zajecia i /rezerwacje.', $user->getEmail());
        }

        $user = $this->linkService->findLinkedUser($telegramChatId);
        if (! $user instanceof User) {
            return 'Najpierw połącz konto: /polacz email@example.com';
        }

        if (str_starts_with($text, '/rozlacz')) {
            $this->linkService->unlink($user);

            return 'Odłączono Telegram od konta Kiddo.';
        }

        $actor = new ChatActor($user, array_values($user->getRoles()));

        if (str_starts_with($text, '/ja') || preg_match('/^(kim jestem|profil)$/iu', $text) === 1) {
            return $this->call($actor, 'user.me', []);
        }

        if (str_starts_with($text, '/zajecia') || preg_match('/^(zajęcia|zajecia|co jest)/iu', $text) === 1) {
            $query = null;
            if (str_starts_with($text, '/zajecia')) {
                $query = trim(substr($text, strlen('/zajecia')));
            }
            $args = [
                'week' => new \DateTimeImmutable('today')
                    ->format('Y-m-d'),
                'limit' => 10,
            ];
            if ($query !== null && $query !== '') {
                $args['query'] = $query;
            }

            return $this->call($actor, 'user.list_upcoming_lessons', $args);
        }

        if (str_starts_with($text, '/rezerwacje') || preg_match('/^moje rezerwacje$/iu', $text) === 1) {
            return $this->call($actor, 'user.list_bookings', []);
        }

        if (str_starts_with($text, '/karnety')) {
            return $this->call($actor, 'user.list_carnets', []);
        }

        if (str_starts_with($text, '/dzisiaj')) {
            if (! $actor->isAdmin()) {
                return 'Komenda tylko dla admina.';
            }

            return $this->call($actor, 'admin.today_schedule', []);
        }

        if (str_starts_with($text, '/przelewy')) {
            if (! $actor->isAdmin()) {
                return 'Komenda tylko dla admina.';
            }

            return $this->call($actor, 'admin.list_unmatched_transfers', []);
        }

        return 'Nie rozumiem. Napisz /pomoc — pełna rozmowa NL jest w czacie na stronie Kiddo.';
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function call(ChatActor $actor, string $tool, array $arguments): string
    {
        $result = $this->registry->call($tool, $actor, $arguments);
        $summary = $result->summary;
        if (! $result->ok && $result->error !== null) {
            return 'Błąd: ' . $result->error;
        }

        $extra = '';
        if (isset($result->data['lessons']) && is_array($result->data['lessons'])) {
            $lines = [];
            foreach (array_slice($result->data['lessons'], 0, 8) as $lesson) {
                if (! is_array($lesson)) {
                    continue;
                }
                $title = is_string($lesson['title'] ?? null) ? $lesson['title'] : '?';
                $scheduleRaw = $lesson['schedule'] ?? null;
                $scheduleLabel = is_string($scheduleRaw)
                    ? new \DateTimeImmutable($scheduleRaw)
                        ->format('d.m H:i')
                    : '?';
                $spots = $lesson['available_spots'] ?? null;
                $spotsLabel = is_int($spots) || is_string($spots) ? (string) $spots : '?';
                $lines[] = sprintf('• %s — %s (wolne: %s)', $title, $scheduleLabel, $spotsLabel);
            }
            if ($lines !== []) {
                $extra = "\n" . implode("\n", $lines);
            }
        }

        return $summary . $extra;
    }
}
