<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Secret extends Model
{
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'encrypted_content',
        'ttl',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'ttl' => 'integer',
    ];

    /**
     * Encrypt content before saving to database.
     */
    public function setEncryptedContentAttribute(string $value): void
    {
        $this->attributes['encrypted_content'] = Crypt::encryptString($value);
    }

    /**
     * Decrypt content when retrieving from database.
     */
    public function getDecryptedContentAttribute(): string
    {
        return Crypt::decryptString($this->encrypted_content);
    }

    /**
     * Check if the secret has expired.
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }
}
