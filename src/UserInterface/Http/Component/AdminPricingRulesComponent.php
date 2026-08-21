<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Repository\ActivityLogRepositoryInterface;
use App\Application\Repository\LessonRepositoryInterface;
use App\Application\Repository\PricingRuleRepositoryInterface;
use App\Application\Repository\UserRepositoryInterface;
use App\Application\Service\ActivityLogger;
use App\Application\Service\Pricing\PriceQuoter;
use App\Domain\Commerce\Pricing\AdjustmentType;
use App\Domain\Commerce\Pricing\PriceQuote;
use App\Domain\Commerce\Pricing\PricingRule;
use App\Domain\Commerce\Pricing\PromotionCode;
use App\Entity\ActivityType;
use App\Entity\Lesson;
use App\Entity\TicketType;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Uid\Ulid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Admin CRUD for PricingRule, behind the pricing_admin flag (Stage 9 of the
 * commerce rollout plan). "Deleting" a rule only ever disables it - there is
 * no hard-delete action - and every create/update/disable writes an
 * ActivityLog entry with a human-readable diff.
 */
#[AsLiveComponent('AdminPricingRules', template: 'components/AdminPricingRulesComponent.html.twig')]
class AdminPricingRulesComponent extends AbstractController
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    #[LiveProp(writable: true)]
    public bool $isModalOpen = false;

    #[LiveProp]
    public ?string $editingRuleId = null;

    #[LiveProp(writable: true)]
    public string $name = '';

    #[LiveProp(writable: true)]
    public string $status = PricingRule::STATUS_ACTIVE;

    #[LiveProp(writable: true)]
    public string $adjustmentType = 'fixed_amount_off';

    #[LiveProp(writable: true)]
    public ?string $adjustmentValue = null;

    #[LiveProp(writable: true)]
    public string $priority = '0';

    #[LiveProp(writable: true)]
    public bool $stackable = true;

    #[LiveProp(writable: true)]
    public ?string $exclusivityGroup = null;

    #[LiveProp(writable: true)]
    public ?string $userId = null;

    #[LiveProp(writable: true)]
    public ?string $seriesId = null;

    #[LiveProp(writable: true)]
    public ?string $lessonId = null;

    #[LiveProp(writable: true)]
    public ?string $ticketType = null;

    #[LiveProp(writable: true)]
    public ?string $promotionCode = null;

    #[LiveProp(writable: true)]
    public ?string $validFrom = null;

    #[LiveProp(writable: true)]
    public ?string $validUntil = null;

    #[LiveProp(writable: true)]
    public ?string $usageLimit = null;

    #[LiveProp(writable: true)]
    public ?string $perUserLimit = null;

    #[LiveProp(writable: true)]
    public ?string $previewUserSearch = null;

    #[LiveProp(writable: true)]
    public ?string $previewUserId = null;

    #[LiveProp(writable: true)]
    public ?string $previewLessonSearch = null;

    #[LiveProp(writable: true)]
    public ?string $previewLessonId = null;

    #[LiveProp(writable: true)]
    public ?string $previewTicketType = null;

    #[LiveProp(writable: true)]
    public ?string $previewDate = null;

    public function __construct(
        private readonly PricingRuleRepositoryInterface $pricingRuleRepository,
        private readonly ActivityLogRepositoryInterface $activityLogRepository,
        private readonly ActivityLogger $activityLogger,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepositoryInterface $userRepository,
        private readonly LessonRepositoryInterface $lessonRepository,
        private readonly PriceQuoter $priceQuoter,
    ) {}

    /**
     * @return list<PricingRule>
     */
    public function getRules(): array
    {
        return $this->pricingRuleRepository->findAllForAdmin();
    }

    /**
     * @return list<array{title: string, summary: ?string, createdAt: \DateTimeImmutable}>
     */
    public function getHistory(): array
    {
        if ($this->editingRuleId === null) {
            return [];
        }

        return array_map(static fn($log): array => [
            'title' => $log->getTitle(),
            'summary' => $log->getSummary(),
            'createdAt' => $log->getCreatedAt(),
        ], $this->activityLogRepository->findByPricingRuleId($this->editingRuleId));
    }

    /**
     * Non-blocking notice: other active, non-stackable rules already sharing
     * the exclusivity group the form currently has entered.
     */
    public function getExclusivityWarning(): ?string
    {
        $group = $this->exclusivityGroup !== null ? trim($this->exclusivityGroup) : null;
        if ($group === null || $group === '' || $this->stackable) {
            return null;
        }

        $others = array_filter(
            $this->pricingRuleRepository->findActive(),
            fn(PricingRule $r): bool => (
                !$r->stackable
                && $r->exclusivityGroup === $group
                && (string) $r->id !== $this->editingRuleId
            ),
        );

        if ($others === []) {
            return null;
        }

        $names = array_map(static fn(PricingRule $r): string => $r->name, $others);

        return sprintf(
            'Ta grupa wykluczania („%s”) jest już używana przez %d aktywną regułę/reguły: %s. Tylko jedna reguła z danej grupy zastosuje się na raz.',
            $group,
            count($names),
            implode(', ', $names),
        );
    }

    #[LiveAction]
    public function openCreateModal(): void
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGE_PRICING');

        $this->editingRuleId = null;
        $this->name = '';
        $this->status = PricingRule::STATUS_ACTIVE;
        $this->adjustmentType = AdjustmentType::FIXED_AMOUNT_OFF->value;
        $this->adjustmentValue = null;
        $this->priority = '0';
        $this->stackable = true;
        $this->exclusivityGroup = null;
        $this->userId = null;
        $this->seriesId = null;
        $this->lessonId = null;
        $this->ticketType = null;
        $this->promotionCode = null;
        $this->validFrom = null;
        $this->validUntil = null;
        $this->usageLimit = null;
        $this->perUserLimit = null;
        $this->isModalOpen = true;
    }

    #[LiveAction]
    public function edit(#[LiveArg] string $id): void
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGE_PRICING');

        $rule = $this->pricingRuleRepository->find(Ulid::fromString($id));
        if (!$rule instanceof PricingRule) {
            return;
        }

        $this->editingRuleId = (string) $rule->id;
        $this->name = $rule->name;
        $this->status = $rule->status;
        $this->adjustmentType = $rule->adjustmentType->value;
        $this->adjustmentValue = (string) $rule->adjustmentValue;
        $this->priority = (string) $rule->priority;
        $this->stackable = $rule->stackable;
        $this->exclusivityGroup = $rule->exclusivityGroup;
        $this->userId = $rule->userId !== null ? (string) $rule->userId : null;
        $this->seriesId = $rule->seriesId !== null ? (string) $rule->seriesId : null;
        $this->lessonId = $rule->lessonId !== null ? (string) $rule->lessonId : null;
        $this->ticketType = $rule->ticketType;
        $this->promotionCode = $rule->promotionCode;
        $this->validFrom = $rule->validFrom?->format('Y-m-d\TH:i');
        $this->validUntil = $rule->validUntil?->format('Y-m-d\TH:i');
        $this->usageLimit = $rule->usageLimit !== null ? (string) $rule->usageLimit : null;
        $this->perUserLimit = $rule->perUserLimit !== null ? (string) $rule->perUserLimit : null;
        $this->isModalOpen = true;
    }

    #[LiveAction]
    public function closeModal(): void
    {
        $this->isModalOpen = false;
    }

    #[LiveAction]
    public function save(): void
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGE_PRICING');

        $name = trim($this->name);
        if ($name === '') {
            $this->addFlash('error', 'Nazwa reguły jest wymagana.');
            return;
        }

        $adjustmentType = AdjustmentType::tryFrom($this->adjustmentType);
        if ($adjustmentType === null) {
            $this->addFlash('error', 'Nieprawidłowy typ korekty.');
            return;
        }

        $adjustmentValue = $this->parseInt($this->adjustmentValue);
        if ($adjustmentValue === null) {
            $this->addFlash('error', 'Wartość korekty jest wymagana i musi być liczbą całkowitą.');
            return;
        }

        $promotionCode =
            $this->promotionCode !== null && trim($this->promotionCode) !== ''
                ? PromotionCode::normalize($this->promotionCode)
                : null;

        try {
            $validFrom = $this->parseDate($this->validFrom);
            $validUntil = $this->parseDate($this->validUntil);
        } catch (\Exception) {
            $this->addFlash('error', 'Nieprawidłowy format daty.');
            return;
        }

        if ($validFrom !== null && $validUntil !== null && $validFrom > $validUntil) {
            $this->addFlash('error', 'Data początkowa nie może być późniejsza niż data końcowa.');
            return;
        }

        if ($this->editingRuleId !== null) {
            $this->updateExisting($name, $adjustmentType, $adjustmentValue, $promotionCode, $validFrom, $validUntil);
        } else {
            $this->createNew($name, $adjustmentType, $adjustmentValue, $promotionCode, $validFrom, $validUntil);
        }

        $this->isModalOpen = false;
    }

    #[LiveAction]
    public function disable(#[LiveArg] string $id): void
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGE_PRICING');
        $this->setStatus(
            $id,
            PricingRule::STATUS_DISABLED,
            ActivityType::PRICING_RULE_DISABLED,
            'Wyłączono regułę cenową „%s”.',
        );
    }

    #[LiveAction]
    public function enable(#[LiveArg] string $id): void
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGE_PRICING');
        $this->setStatus(
            $id,
            PricingRule::STATUS_ACTIVE,
            ActivityType::PRICING_RULE_UPDATED,
            'Włączono regułę cenową „%s”.',
        );
    }

    private function setStatus(string $id, string $status, ActivityType $activityType, string $titleFormat): void
    {
        $rule = $this->pricingRuleRepository->find(Ulid::fromString($id));
        if (!$rule instanceof PricingRule || $rule->status === $status) {
            return;
        }

        $rule->status = $status;
        $this->entityManager->flush();

        /** @var ?User $actor */
        $actor = $this->getUser();
        $this->activityLogger->log(
            type: $activityType,
            title: sprintf($titleFormat, $rule->name),
            subject: $actor,
            context: [
                'pricingRuleId' => (string) $rule->id,
            ],
        );

        $this->addFlash('success', 'Zapisano zmianę statusu reguły.');
    }

    private function createNew(
        string $name,
        AdjustmentType $adjustmentType,
        int $adjustmentValue,
        ?string $promotionCode,
        ?\DateTimeImmutable $validFrom,
        ?\DateTimeImmutable $validUntil,
    ): void {
        $rule = new PricingRule(
            id: new Ulid(),
            name: $name,
            adjustmentType: $adjustmentType,
            adjustmentValue: $adjustmentValue,
            priority: $this->parseInt($this->priority) ?? 0,
            stackable: $this->stackable,
            exclusivityGroup: $this->nullableTrim($this->exclusivityGroup),
            userId: $this->parseInt($this->userId),
            seriesId: $this->parseUlid($this->seriesId),
            lessonId: $this->parseUlid($this->lessonId),
            ticketType: $this->nullableTrim($this->ticketType),
            promotionCode: $promotionCode,
            validFrom: $validFrom,
            validUntil: $validUntil,
            usageLimit: $this->parseInt($this->usageLimit),
            perUserLimit: $this->parseInt($this->perUserLimit),
            status: $this->status,
        );

        $this->entityManager->persist($rule);
        $this->entityManager->flush();

        /** @var ?User $actor */
        $actor = $this->getUser();
        $this->activityLogger->log(
            type: ActivityType::PRICING_RULE_CREATED,
            title: sprintf('Utworzono regułę cenową „%s”.', $rule->name),
            subject: $actor,
            context: [
                'pricingRuleId' => (string) $rule->id,
            ],
        );

        $this->addFlash('success', 'Reguła cenowa została utworzona.');
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotFields(PricingRule $rule): array
    {
        return [
            'Nazwa' => $rule->name,
            'Status' => $rule->status,
            'Typ korekty' => $rule->adjustmentType->value,
            'Wartość korekty' => $rule->adjustmentValue,
            'Priorytet' => $rule->priority,
            'Łączenie z innymi' => $rule->stackable ? 'tak' : 'nie',
            'Grupa wykluczania' => $rule->exclusivityGroup,
            'ID użytkownika' => $rule->userId,
            'ID cyklu' => $rule->seriesId !== null ? (string) $rule->seriesId : null,
            'ID zajęć' => $rule->lessonId !== null ? (string) $rule->lessonId : null,
            'Typ biletu' => $rule->ticketType,
            'Kod promocyjny' => $rule->promotionCode,
            'Ważna od' => $rule->validFrom?->format('Y-m-d H:i'),
            'Ważna do' => $rule->validUntil?->format('Y-m-d H:i'),
            'Limit użyć' => $rule->usageLimit,
            'Limit na użytkownika' => $rule->perUserLimit,
        ];
    }

    private function updateExisting(
        string $name,
        AdjustmentType $adjustmentType,
        int $adjustmentValue,
        ?string $promotionCode,
        ?\DateTimeImmutable $validFrom,
        ?\DateTimeImmutable $validUntil,
    ): void {
        $rule = $this->pricingRuleRepository->find(Ulid::fromString($this->editingRuleId ?? ''));
        if (!$rule instanceof PricingRule) {
            $this->addFlash('error', 'Nie znaleziono reguły do edycji.');
            return;
        }

        $before = $this->snapshotFields($rule);

        $rule->name = $name;
        $rule->status = $this->status;
        $rule->adjustmentType = $adjustmentType;
        $rule->adjustmentValue = $adjustmentValue;
        $rule->priority = $this->parseInt($this->priority) ?? 0;
        $rule->stackable = $this->stackable;
        $rule->exclusivityGroup = $this->nullableTrim($this->exclusivityGroup);
        $rule->userId = $this->parseInt($this->userId);
        $rule->seriesId = $this->parseUlid($this->seriesId);
        $rule->lessonId = $this->parseUlid($this->lessonId);
        $rule->ticketType = $this->nullableTrim($this->ticketType);
        $rule->promotionCode = $promotionCode;
        $rule->validFrom = $validFrom;
        $rule->validUntil = $validUntil;
        $rule->usageLimit = $this->parseInt($this->usageLimit);
        $rule->perUserLimit = $this->parseInt($this->perUserLimit);

        $after = $this->snapshotFields($rule);

        $this->entityManager->flush();

        $changedLines = [];
        foreach ($before as $label => $oldValue) {
            $newValue = $after[$label];
            if ($oldValue === $newValue) {
                continue;
            }

            $changedLines[] = sprintf(
                '%s: „%s” → „%s”',
                $label,
                $oldValue !== null && $oldValue !== '' ? (string) $oldValue : '—',
                $newValue !== null && $newValue !== '' ? (string) $newValue : '—',
            );
        }

        /** @var ?User $actor */
        $actor = $this->getUser();
        $this->activityLogger->log(
            type: ActivityType::PRICING_RULE_UPDATED,
            title: sprintf('Zaktualizowano regułę cenową „%s”.', $rule->name),
            subject: $actor,
            summary: $changedLines === [] ? null : implode("\n", $changedLines),
            context: [
                'pricingRuleId' => (string) $rule->id,
            ],
        );

        $this->addFlash('success', 'Reguła cenowa została zaktualizowana.');
    }

    /**
     * @return array<array{id: int, name: string}>
     */
    public function getPreviewUserResults(): array
    {
        if ($this->previewUserSearch === null || mb_strlen(trim($this->previewUserSearch)) < 2) {
            return [];
        }

        return array_map(static fn(User $u): array => [
            'id' => $u->getId() ?? 0,
            'name' => $u->getName(),
        ], $this->userRepository->findForAutocomplete($this->previewUserSearch));
    }

    /**
     * @return array<array{id: string, title: string}>
     */
    public function getPreviewLessonResults(): array
    {
        if ($this->previewLessonSearch === null || mb_strlen(trim($this->previewLessonSearch)) < 2) {
            return [];
        }

        return array_map(static fn(Lesson $l): array => [
            'id' => (string) $l->getId(),
            'title' => $l->getMetadata()->title,
        ], $this->lessonRepository->findByMetadataTitlePrefix($this->previewLessonSearch));
    }

    #[LiveAction]
    public function selectPreviewUser(#[LiveArg] string $id, #[LiveArg] string $name): void
    {
        $this->previewUserId = $id;
        $this->previewUserSearch = $name;
    }

    #[LiveAction]
    public function selectPreviewLesson(#[LiveArg] string $id, #[LiveArg] string $title): void
    {
        $this->previewLessonId = $id;
        $this->previewLessonSearch = $title;
    }

    public function getPreviewLessonTicketTypes(): array
    {
        $lesson = $this->previewLessonId !== null
            ? $this->lessonRepository->find($this->parseUlid($this->previewLessonId) ?? new Ulid())
            : null;

        if (!$lesson instanceof Lesson) {
            return [];
        }

        return array_map(static fn($o) => $o->type->value, iterator_to_array($lesson->getTicketOptions()));
    }

    public function getPreviewQuote(): ?PriceQuote
    {
        if ($this->previewLessonId === null || $this->previewTicketType === null || $this->previewTicketType === '') {
            return null;
        }

        $lesson = $this->lessonRepository->find($this->parseUlid($this->previewLessonId) ?? new Ulid());
        if (!$lesson instanceof Lesson) {
            return null;
        }

        try {
            $ticketOption = $lesson->getMatchingTicketOption($this->previewTicketType);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $at = $this->parseDate($this->previewDate) ?? new \DateTimeImmutable();
        $userId = $this->parseInt($this->previewUserId);

        return $this->priceQuoter->quote($userId, $lesson, $this->previewTicketType, $ticketOption->price, $at);
    }

    private function parseInt(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function parseUlid(?string $value): ?Ulid
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Ulid::fromString(trim($value));
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function nullableTrim(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return new \DateTimeImmutable($value);
    }

    /**
     * @return list<string>
     */
    public function getTicketTypeOptions(): array
    {
        return array_map(static fn(TicketType $t): string => $t->value, TicketType::cases());
    }

    /**
     * @return list<string>
     */
    public function getAdjustmentTypeOptions(): array
    {
        return array_map(static fn(AdjustmentType $t): string => $t->value, AdjustmentType::cases());
    }
}
