<?php

namespace App\Repositories;

use App\Models\Secret;
use Carbon\Carbon;

class SecretRepository implements SecretRepositoryInterface
{
    /**
     * Create a new secret.
     */
    public function create(string $content, ?int $ttl): Secret
    {
        $expiresAt = $ttl ? Carbon::now()->addSeconds($ttl) : null;

        return Secret::create([
            'encrypted_content' => $content,
            'ttl' => $ttl,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Find a secret by ID.
     */
    public function findById(string $id): ?Secret
    {
        return Secret::find($id);
    }

    /**
     * Delete a secret.
     */
    public function delete(Secret $secret): bool
    {
        return $secret->delete();
    }

    /**
     * Delete expired secrets (cleanup job).
     */
    public function deleteExpired(): int
    {
        return Secret::where('expires_at', '<=', Carbon::now())->delete();
    }
}
