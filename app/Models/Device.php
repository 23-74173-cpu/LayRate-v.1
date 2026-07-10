<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Device extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'api_key_hash', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function hardwareItems(): HasMany
    {
        return $this->hasMany(HardwareItem::class);
    }

    /**
     * Generate a new plain API key, store its hash, and return the plain key.
     * The plain key is shown only once.
     */
    public function generateApiKey(): string
    {
        $plain = 'lr_' . Str::random(40);

        $this->update(['api_key_hash' => Hash::make($plain)]);

        return $plain;
    }

    /**
     * Verify a plain API key against the stored hash.
     */
    public function verifyApiKey(string $plainKey): bool
    {
        return Hash::check($plainKey, $this->api_key_hash);
    }
}
