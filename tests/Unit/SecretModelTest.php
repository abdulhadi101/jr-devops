<?php

use App\Models\Secret;
use Illuminate\Support\Facades\Crypt;

beforeEach(function () {
    $this->artisan('migrate:fresh');
});

describe('Secret Model', function () {
    it('uses UUID as primary key', function () {
        $secret = Secret::create([
            'encrypted_content' => 'test-content',
        ]);

        expect($secret->id)->toBeString()
            ->and(strlen($secret->id))->toBe(36); // UUID length
    });

    it('encrypts content when setting', function () {
        $plaintext = 'my-secret-password';

        $secret = Secret::create([
            'encrypted_content' => $plaintext,
        ]);

        // Verify the encrypted content in raw database is not plaintext
        $rawData = \DB::table('secrets')->where('id', $secret->id)->first();
        expect($rawData->encrypted_content)->not->toBe($plaintext)
            // Should start with encrypted data prefix
            ->and($rawData->encrypted_content)->toContain('eyJpdiI6');
    });

    it('decrypts content when getting', function () {
        $plaintext = 'my-secret-password';

        $secret = Secret::create([
            'encrypted_content' => $plaintext,
        ]);

        // Refresh from database
        $secret = Secret::find($secret->id);

        expect($secret->decrypted_content)->toBe($plaintext);
    });

    it('handles encryption and decryption correctly', function () {
        $originalContent = 'This is a very secret message!';

        $secret = Secret::create([
            'encrypted_content' => $originalContent,
        ]);

        // Verify it's encrypted in the database
        $rawData = \DB::table('secrets')->where('id', $secret->id)->first();
        expect($rawData->encrypted_content)->not->toBe($originalContent);

        // Verify it decrypts correctly
        $decrypted = Crypt::decryptString($rawData->encrypted_content);
        expect($decrypted)->toBe($originalContent);

        // Verify accessor works
        expect($secret->decrypted_content)->toBe($originalContent);
    });

    it('casts expires_at to datetime', function () {
        $expiresAt = now()->addHour();

        $secret = Secret::create([
            'encrypted_content' => 'test',
            'expires_at' => $expiresAt,
        ]);

        expect($secret->expires_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    it('casts ttl to integer', function () {
        $secret = Secret::create([
            'encrypted_content' => 'test',
            'ttl' => '3600',
        ]);

        expect($secret->ttl)->toBeInt()
            ->and($secret->ttl)->toBe(3600);
    });
});

describe('Secret::isExpired', function () {
    it('returns false when expires_at is null', function () {
        $secret = Secret::create([
            'encrypted_content' => 'test',
            'expires_at' => null,
        ]);

        expect($secret->isExpired())->toBeFalse();
    });

    it('returns false when expires_at is in the future', function () {
        $secret = Secret::create([
            'encrypted_content' => 'test',
            'expires_at' => now()->addHour(),
        ]);

        expect($secret->isExpired())->toBeFalse();
    });

    it('returns true when expires_at is in the past', function () {
        $secret = Secret::create([
            'encrypted_content' => 'test',
            'expires_at' => now()->subHour(),
        ]);

        expect($secret->isExpired())->toBeTrue();
    });

    it('returns true when expires_at is exactly now', function () {
        $secret = Secret::create([
            'encrypted_content' => 'test',
            'expires_at' => now()->subSecond(),
        ]);

        expect($secret->isExpired())->toBeTrue();
    });
});
