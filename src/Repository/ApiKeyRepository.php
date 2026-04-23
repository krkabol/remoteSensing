<?php

namespace App\Repository;

use App\Entity\ApiKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiKey>
 */
class ApiKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiKey::class);
    }

    public function findByKeyHash(string $keyHash): ?ApiKey
    {
        return $this->findOneBy(['keyHash' => $keyHash]);
    }

    /**
     * @return ApiKey[]
     */
    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.owner = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('k.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveByKeyHash(string $keyHash): ?ApiKey
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.keyHash = :keyHash')
            ->andWhere('k.isActive = :isActive')
            ->setParameter('keyHash', $keyHash)
            ->setParameter('isActive', true)
            ->getQuery()
            ->getOneOrNullResult();
    }
}