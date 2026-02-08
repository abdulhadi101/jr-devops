<?php

use App\Models\Secret;
use App\Repositories\SecretRepository;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

beforeEach(function () {
    $this->artisan('migrate:fresh');
    $this->repository = new SecretRepository;
});

describe('SecretRepository::create', function () {
    it('creates a secret without TTL', function () {
        $content = 'test-content';

        $secret = $this->repository->create($content, null);

        expect($secret)->toBeInstanceOf(Secret::class)
            ->and($secret->id)->not->toBeNull()
            ->and($secret->ttl)->toBeNull()
            ->and($secret->expires_at)->toBeNull();

        assertDatabaseHas('secrets', [
            'id' => $secret->id,
            'ttl' => null,
        ]);
    });

    it('creates a secret with TTL', function () {
        $content = 'test-content';
        $ttl = 3600;

        $secret = $this->repository->create($content, $ttl);

        expect($secret->ttl)->toBe($ttl)
            ->and($secret->expires_at)->not->toBeNull()
            ->and($secret->expires_at->isFuture())->toBeTrue();
    });

    it('calculates correct expiration time', function () {
        $ttl = 3600;
        $expectedExpiry = now()->addSeconds($ttl);

        $secret = $this->repository->create('content', $ttl);

        expect($secret->expires_at->timestamp)
            ->toBeGreaterThanOrEqual($expectedExpiry->timestamp - 1)
            ->toBeLessThanOrEqual($expectedExpiry->timestamp + 1);
    });
});

describe('SecretRepository::findById', function () {
    it('finds an existing secret', function () {
        $secret = Secret::create([
            'encrypted_content' => 'test-content',
        ]);

        $found = $this->repository->findById($secret->id);

        expect($found)->toBeInstanceOf(Secret::class)
            ->and($found->id)->toBe($secret->id);
    });

    it('returns null for non-existent secret', function () {
        $result = $this->repository->findById('non-existent-id');

        expect($result)->toBeNull();
    });
});

describe('SecretRepository::delete', function () {
    it('deletes a secret', function () {
        $secret = Secret::create([
            'encrypted_content' => 'test-content',
        ]);

        assertDatabaseHas('secrets', ['id' => $secret->id]);

        $result = $this->repository->delete($secret);

        expect($result)->toBeTrue();
        assertDatabaseMissing('secrets', ['id' => $secret->id]);
    });
});

describe('SecretRepository::deleteExpired', function () {
    it('deletes only expired secrets', function () {
        // Create expired secret
        $expired = Secret::create([
            'encrypted_content' => 'expired',
            'expires_at' => now()->subHour(),
        ]);

        // Create valid secret
        $valid = Secret::create([
            'encrypted_content' => 'valid',
            'expires_at' => now()->addHour(),
        ]);

        // Create secret without expiration
        $noExpiry = Secret::create([
            'encrypted_content' => 'no-expiry',
            'expires_at' => null,
        ]);

        $deleted = $this->repository->deleteExpired();

        expect($deleted)->toBe(1);

        assertDatabaseMissing('secrets', ['id' => $expired->id]);
        assertDatabaseHas('secrets', ['id' => $valid->id]);
        assertDatabaseHas('secrets', ['id' => $noExpiry->id]);
    });

    it('returns zero when no expired secrets exist', function () {
        Secret::create([
            'encrypted_content' => 'valid',
            'expires_at' => now()->addHour(),
        ]);

        $deleted = $this->repository->deleteExpired();

        expect($deleted)->toBe(0);
    });

    it('deletes multiple expired secrets', function () {
        Secret::create([
            'encrypted_content' => 'expired1',
            'expires_at' => now()->subDay(),
        ]);

        Secret::create([
            'encrypted_content' => 'expired2',
            'expires_at' => now()->subHour(),
        ]);

        Secret::create([
            'encrypted_content' => 'expired3',
            'expires_at' => now()->subMinute(),
        ]);

        $deleted = $this->repository->deleteExpired();

        expect($deleted)->toBe(3);
    });
});
