<?php

use App\Models\Secret;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->artisan('migrate:fresh');
});

describe('POST /api/v1/secrets', function () {
    it('creates a secret successfully', function () {
        $response = postJson('/api/v1/secrets', [
            'content' => 'my-secret-password',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'url', 'expires_at'],
            ]);

        assertDatabaseHas('secrets', [
            'id' => $response->json('data.id'),
        ]);
    });

    it('creates a secret with TTL', function () {
        $response = postJson('/api/v1/secrets', [
            'content' => 'my-secret-password',
            'ttl' => 3600,
        ]);

        $response->assertStatus(201);

        $secret = Secret::find($response->json('data.id'));
        expect($secret->ttl)->toBe(3600)
            ->and($secret->expires_at)->not->toBeNull();
    });

    it('encrypts content in database', function () {
        $content = 'my-secret-password';

        $response = postJson('/api/v1/secrets', [
            'content' => $content,
        ]);

        $secret = Secret::find($response->json('data.id'));

        // Content should be encrypted in DB
        expect($secret->encrypted_content)->not->toBe($content)
            // But should decrypt correctly
            ->and($secret->decrypted_content)->toBe($content);
    });

    it('validates required content', function () {
        $response = postJson('/api/v1/secrets', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    });

    it('validates content max length', function () {
        $response = postJson('/api/v1/secrets', [
            'content' => str_repeat('a', 10001),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    });

    it('validates TTL minimum value', function () {
        $response = postJson('/api/v1/secrets', [
            'content' => 'test',
            'ttl' => 30, // Less than 60 seconds
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ttl']);
    });

    it('validates TTL maximum value', function () {
        $response = postJson('/api/v1/secrets', [
            'content' => 'test',
            'ttl' => 999999, // More than 7 days
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ttl']);
    });

    it('returns correct URL format', function () {
        $response = postJson('/api/v1/secrets', [
            'content' => 'test',
        ]);

        $id = $response->json('data.id');
        $expectedUrl = url("/api/v1/secrets/{$id}");

        expect($response->json('data.url'))->toBe($expectedUrl);
    });
});

describe('GET /api/v1/secrets/{id}', function () {
    it('retrieves a secret successfully', function () {
        $content = 'my-secret-password';

        $secret = Secret::create([
            'encrypted_content' => $content,
        ]);

        $response = getJson("/api/v1/secrets/{$secret->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'content' => $content,
                ],
            ]);
    });

    it('deletes secret after retrieval (burn on read)', function () {
        $secret = Secret::create([
            'encrypted_content' => 'test-content',
        ]);

        assertDatabaseHas('secrets', ['id' => $secret->id]);

        getJson("/api/v1/secrets/{$secret->id}");

        assertDatabaseMissing('secrets', ['id' => $secret->id]);
    });

    it('returns 404 for non-existent secret', function () {
        $fakeId = '9d5e8c42-1234-5678-9abc-def012345678';

        $response = getJson("/api/v1/secrets/{$fakeId}");

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Secret not found',
            ]);
    });

    it('returns 404 when trying to read secret twice', function () {
        $secret = Secret::create([
            'encrypted_content' => 'test-content',
        ]);

        // First read - success
        $response1 = getJson("/api/v1/secrets/{$secret->id}");
        $response1->assertStatus(200);

        // Second read - should fail
        $response2 = getJson("/api/v1/secrets/{$secret->id}");
        $response2->assertStatus(404);
    });

    it('returns 410 for expired secret', function () {
        $secret = Secret::create([
            'encrypted_content' => 'test-content',
            'ttl' => 3600,
            'expires_at' => now()->subHour(), // Expired 1 hour ago
        ]);

        $response = getJson("/api/v1/secrets/{$secret->id}");

        $response->assertStatus(410)
            ->assertJson([
                'message' => 'Secret has expired',
            ]);
    });

    it('deletes expired secret when accessed', function () {
        $secret = Secret::create([
            'encrypted_content' => 'test-content',
            'expires_at' => now()->subHour(),
        ]);

        assertDatabaseHas('secrets', ['id' => $secret->id]);

        getJson("/api/v1/secrets/{$secret->id}");

        assertDatabaseMissing('secrets', ['id' => $secret->id]);
    });
});

describe('Health Check', function () {
    it('returns health status', function () {
        $response = getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'timestamp',
            ])
            ->assertJson([
                'status' => 'ok',
            ]);
    });
});
