<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Setting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Setting>
 */
class SettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function findAllAsMap(): array
    {
        $result = [];
        foreach ($this->findAll() as $setting) {
            $result[$setting->getName()] = $setting->getValue();
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function saveMap(array $values): void
    {
        $em = $this->getEntityManager();
        foreach ($values as $name => $value) {
            $setting = $this->find($name);
            if (null === $setting) {
                $em->persist(new Setting($name, $value));
            } else {
                $setting->setValue($value);
            }
        }
        $em->flush();
    }
}
