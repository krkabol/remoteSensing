<?php

namespace App\Security;

use App\Entity\ApiKey;
use App\Entity\User;
use App\Repository\ApiKeyRepository;
use Doctrine\ORM\EntityManagerInterface;

class ApiKeyService
{
    private const KEY_PREFIX = 'sk_live_';
    private const KEY_LENGTH = 32;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ApiKeyRepository $apiKeyRepository
    ) {}

    /**
     * Generate a new API key for a user
     */
    public function generateKey(User $user, string $name, ?\DateTimeImmutable $expiresAt = null): string
    {
        // Generate a random key
        $rawKey = self::KEY_PREFIX . bin2hex(random_bytes(self::KEY_LENGTH));
        
        // Hash the key for storage
        $hashedKey = hash('sha256', $rawKey);

        // Create and persist the API key entity
        $apiKey = new ApiKey();
        $apiKey->setKeyHash($hashedKey);
        $apiKey->setName($name);
        $apiKey->setExpiresAt($expiresAt);
        $apiKey->setIsActive(true);

        $user->addApiKey($apiKey);
        $this->entityManager->persist($apiKey);
        $this->entityManager->flush();

        // Return the raw key (only shown once)
        return $rawKey;
    }

    /**
     * Validate an API key and return the associated user
     */
    public function validateKey(string $rawKey): ?User
    {
        $hashedKey = hash('sha256', $rawKey);
        
        $apiKey = $this->apiKeyRepository->findActiveByKeyHash($hashedKey);

        if ($apiKey === null) {
            return null;
        }

        // Check if key is expired
        if ($apiKey->isExpired()) {
            $apiKey->setIsActive(false);
            $this->entityManager->flush();
            return null;
        }

        // Update last used timestamp
        $apiKey->setLastUsedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $apiKey->getOwner();
    }

    /**
     * Revoke an API key
     */
    public function revokeKey(int $keyId): bool
    {
        $apiKey = $this->apiKeyRepository->find($keyId);

        if ($apiKey === null) {
            return false;
        }

        $apiKey->setIsActive(false);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Get all active API keys for a user
     *
     * @return ApiKey[]
     */
    public function getUserKeys(User $user): array
    {
        return $this->apiKeyRepository->findByUser($user->getId());
    }

    /**
     * Get the key prefix for identification
     */
    public static function getKeyPrefix(): string
    {
        return self::KEY_PREFIX;
    }
}