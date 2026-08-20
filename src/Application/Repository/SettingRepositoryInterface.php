<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\Setting;

/**
 * @extends RepositoryInterface<Setting>
 */
interface SettingRepositoryInterface extends RepositoryInterface
{
    public function findOneByKey(string $key): ?Setting;
}
