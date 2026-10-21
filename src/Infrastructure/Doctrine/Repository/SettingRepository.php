<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Application\Repository\SettingRepositoryInterface;
use App\Entity\Setting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Setting>
 */
class SettingRepository extends ServiceEntityRepository implements SettingRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    #[\Override]
    public function findOneByKey(string $key): ?Setting
    {
        return $this->findOneBy([
            'key' => $key,
        ]);
    }

    /**
     * @param \Closure(array<string, mixed>): array<string, mixed> $update
     * @return array<string, mixed>
     * @throws \Throwable
     */
    #[\Override]
    public function updateContent(string $key, \Closure $update): array
    {
        $em = $this->getEntityManager();
        $connection = $em->getConnection();
        return $connection->transactional(
            /**
             * @throws \Doctrine\DBAL\Exception
             * @throws \Doctrine\ORM\Exception\ORMException
             * @throws \Doctrine\ORM\ORMInvalidArgumentException
             * @throws \Doctrine\ORM\TransactionRequiredException
             */
            function () use ($em, $connection, $key, $update): array {
                // All writers serialize on the key, including when the setting is absent.
                $connection->executeQuery('SELECT pg_advisory_xact_lock(hashtext(:key))', ['key' => $key]);
                $setting = $this->findOneByKey($key);
                if ($setting !== null) {
                    $em->refresh($setting);
                } else {
                    $setting = new Setting();
                    $setting->setKey($key);
                    $em->persist($setting);
                }
                $content = $update($setting->getContent() ?? []);
                $setting->setContent($content);
                $em->flush();
                return $content;
            },
        );
    }
}
