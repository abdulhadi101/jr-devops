<?php

use App\Exceptions\SecretExpiredException;
use App\Exceptions\SecretNotFoundException;
use App\Models\Secret;
use App\Repositories\SecretRepositoryInterface;
use App\Services\SecretService;

beforeEach(function () {
    $this->repository = Mockery::mock(SecretRepositoryInterface::class);
    $this->service = new SecretService($this->repository);
});

describe('SecretService::storeSecret', function () {
    it('stores a secret without TTL', function () {
        $content = 'test-content';
        $secret = new Secret(['id' => '123e4567-e89b-12d3-a456-426614174000']);
        $secret->expires_at = null;

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with($content, null)
            ->andReturn($secret);

        $result = $this->service->storeSecret($content);

        expect($result)->toBeInstanceOf(Secret::class)
            ->and($result->id)->toBe($secret->id)
            ->and($result->expires_at)->toBeNull();
    });

    it('stores a secret with TTL', function () {
        $content = 'test-content';
        $ttl = 3600;
        $expiresAt = now()->addSeconds($ttl);

        $secret = new Secret([
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'ttl' => $ttl,
        ]);
        $secret->expires_at = $expiresAt;

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with($content, $ttl)
            ->andReturn($secret);

        $result = $this->service->storeSecret($content, $ttl);

        expect($result)->toBeInstanceOf(Secret::class)
            ->and($result->expires_at)->not->toBeNull();
    });
});

describe('SecretService::retrieveAndBurnSecret', function () {
    it('retrieves and deletes a valid secret', function () {
        $content = 'decrypted-content';
        $secret = Mockery::mock(Secret::class);

        $secret->shouldReceive('getAttribute')
            ->with('decrypted_content')
            ->andReturn($content);

        $secret->shouldReceive('isExpired')
            ->once()
            ->andReturn(false);

        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->with('test-id')
            ->andReturn($secret);

        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with($secret)
            ->andReturn(true);

        $result = $this->service->retrieveAndBurnSecret('test-id');

        expect($result)->toBe($content);
    });

    it('throws exception when secret not found', function () {
        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->with('non-existent')
            ->andReturn(null);

        $this->service->retrieveAndBurnSecret('non-existent');
    })->throws(SecretNotFoundException::class, 'Secret not found');

    it('throws exception and deletes when secret expired', function () {
        $secret = Mockery::mock(Secret::class);

        $secret->shouldReceive('isExpired')
            ->once()
            ->andReturn(true);

        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->andReturn($secret);

        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with($secret)
            ->andReturn(true);

        $this->service->retrieveAndBurnSecret('expired-id');
    })->throws(SecretExpiredException::class, 'Secret has expired');
});

describe('SecretService::cleanupExpiredSecrets', function () {
    it('calls repository to delete expired secrets', function () {
        $this->repository
            ->shouldReceive('deleteExpired')
            ->once()
            ->andReturn(5);

        $result = $this->service->cleanupExpiredSecrets();

        expect($result)->toBe(5);
    });
});
