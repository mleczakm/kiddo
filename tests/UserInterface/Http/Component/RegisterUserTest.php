<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\ConsentType;
use App\Entity\File;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentType;
use App\Entity\LegalDocumentVersion;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\UserConsentRepository;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use App\UserInterface\Http\Component\RegisterUser;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class RegisterUserTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testSubmittingWithNewsletterCheckedSubscribesUser(): void
    {
        $client = static::createClient();

        $testComponent = $this->createLiveComponent(name: RegisterUser::class, client: $client);
        $testComponent->submitForm([
            'form' => [
                'name' => 'Alice',
                'email' => 'brand.new@example.com',
                'newsletterSubscribed' => '1',
            ],
        ], action: 'save');

        $user = $this->findUserByEmail('brand.new@example.com');
        static::assertNotNull($user);
        static::assertTrue($user->isNewsletterSubscribed());
        static::assertNotNull($user->getNewsletterConsentDate());

        $consents = $this->consentRepository()->findHistoryForUser($user);
        static::assertCount(1, $consents);
        static::assertSame(ConsentType::MARKETING_EMAIL, $consents[0]->getType());
    }

    public function testSubmittingWithoutNewsletterDoesNotSubscribe(): void
    {
        $client = static::createClient();

        $testComponent = $this->createLiveComponent(name: RegisterUser::class, client: $client);
        $testComponent->submitForm([
            'form' => [
                'name' => 'Bob',
                'email' => 'no.newsletter@example.com',
            ],
        ], action: 'save');

        $user = $this->findUserByEmail('no.newsletter@example.com');
        static::assertNotNull($user);
        static::assertFalse($user->isNewsletterSubscribed());
        static::assertNull($user->getNewsletterConsentDate());
    }

    public function testRegistrationIsRejectedWithoutRequiredLegalAcceptance(): void
    {
        $client = static::createClient();
        $this->publishRegistrationDocuments();

        $testComponent = $this->createLiveComponent(name: RegisterUser::class, client: $client);
        static::assertStringContainsString('name="form[acceptTerms]"', $testComponent->render()->toString());

        try {
            $testComponent->submitForm([
                'form' => [
                    'name' => 'Caroline',
                    'email' => 'missing.consent@example.com',
                ],
            ], action: 'save');
            static::fail('Registration without required legal acceptance should fail validation.');
        } catch (UnprocessableEntityHttpException $exception) {
            static::assertSame('Form validation failed in component.', $exception->getMessage());
        }

        static::assertNull($this->findUserByEmail('missing.consent@example.com'));
    }

    public function testRegistrationRecordsCurrentTermsAndPrivacyVersions(): void
    {
        $client = static::createClient();
        $this->publishRegistrationDocuments();

        $testComponent = $this->createLiveComponent(name: RegisterUser::class, client: $client);
        $testComponent->submitForm([
            'form' => [
                'name' => 'Daniel',
                'email' => 'accepted.terms@example.com',
                'acceptTerms' => '1',
            ],
        ], action: 'save');

        $user = $this->findUserByEmail('accepted.terms@example.com');
        static::assertNotNull($user);
        $consents = $this->consentRepository()->findHistoryForUser($user);
        static::assertCount(2, $consents);
        static::assertEqualsCanonicalizing(
            [ConsentType::APP_TERMS, ConsentType::PRIVACY],
            array_map(static fn($consent): ConsentType => $consent->getType(), $consents),
        );
        foreach ($consents as $consent) {
            static::assertNotNull($consent->getDocumentVersion());
        }
    }

    private function findUserByEmail(string $email): ?User
    {
        $repository = self::getContainer()->get(UserRepository::class);

        return $repository->findOneBy([
            'email' => $email,
        ]);
    }

    private function consentRepository(): UserConsentRepository
    {
        /** @var UserConsentRepository */
        return self::getContainer()->get(UserConsentRepository::class);
    }

    private function publishRegistrationDocuments(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $publisher = new User(sprintf('publisher-%s@example.test', bin2hex(random_bytes(4))), 'Publisher');
        $entityManager->persist($publisher);

        foreach ([LegalDocumentType::APP_TERMS, LegalDocumentType::PRIVACY] as $type) {
            $contents = sprintf('%s registration document', $type->value);
            $file = new File(
                sprintf('%s.pdf', $type->value),
                'application/pdf',
                \strlen($contents),
                hash('sha256', $contents),
                base64_encode($contents),
            );
            $document = new LegalDocument($type);
            new LegalDocumentVersion($document, $file, new \DateTimeImmutable('-1 day'), $publisher);
            $entityManager->persist($file);
            $entityManager->persist($document);
        }

        $entityManager->flush();
    }
}
