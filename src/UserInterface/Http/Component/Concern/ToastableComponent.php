<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component\Concern;

use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\ComponentToolsTrait;

/**
 * Lets a LiveComponent raise an app-wide toast from a LiveAction.
 *
 * Emits a bubbling `toast` browser event ({message, level}) that the shared
 * `toast` Stimulus controller (mounted in both frontend and admin bases via
 * `partials/_toasts.html.twig`) renders. `$message` is treated as a
 * translation key and resolved here, so callers pass keys, not literals —
 * matching the `addFlash('success', 'some.key')` convention already used
 * across the codebase.
 */
trait ToastableComponent
{
    use ComponentToolsTrait;

    private ?TranslatorInterface $toastTranslator = null;

    #[Required]
    public function setToastTranslator(TranslatorInterface $translator): void
    {
        $this->toastTranslator = $translator;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws \InvalidArgumentException
     */
    private function toast(string $message, string $level = 'success', array $params = []): void
    {
        $text = $this->toastTranslator?->trans($message, $params) ?? $message;

        $this->dispatchBrowserEvent('toast', [
            'message' => $text,
            'level' => $level,
        ]);
    }
}
