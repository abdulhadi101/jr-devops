<?php

namespace App\Repositories;

use App\Models\Secret;

interface SecretRepositoryInterface
{
    /**
     * Create a new secret.
     */
    public function create(string $content, ?int $ttl): Secret;

    /**
     * Find a secret by ID.
     */
    public function findById(string $id): ?Secret;

    /**
     * Delete a secret.
     */
    public function delete(Secret $secret): bool;

    /**
     * Delete expired secrets (cleanup job).
     */
    public function deleteExpired(): int;
}
