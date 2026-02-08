<?php

namespace App\Services;

use App\Exceptions\SecretExpiredException;
use App\Exceptions\SecretNotFoundException;
use App\Models\Secret;
use App\Repositories\SecretRepositoryInterface;

class SecretService
{
    public function __construct(
        private SecretRepositoryInterface $repository
    ) {}

    /**
     * Store a new secret.
     */
    public function storeSecret(string $content, ?int $ttl = null): Secret
    {
        return $this->repository->create($content, $ttl);
    }

    /**
     * Retrieve a secret and immediately delete it (burn after reading).
     *
     * @throws SecretNotFoundException
     * @throws SecretExpiredException
     */
    public function retrieveAndBurnSecret(string $id): string
    {
        $secret = $this->repository->findById($id);

        if (! $secret) {
            throw new SecretNotFoundException('Secret not found');
        }

        if ($secret->isExpired()) {
            // Delete expired secret before throwing exception
            $this->repository->delete($secret);
            throw new SecretExpiredException('Secret has expired');
        }

        // Get decrypted content before deleting
        $content = $secret->decrypted_content;

        // Delete the secret (burn after reading)
        $this->repository->delete($secret);

        return $content;
    }

    /**
     * Clean up expired secrets from the database.
     */
    public function cleanupExpiredSecrets(): int
    {
        return $this->repository->deleteExpired();
    }
}
