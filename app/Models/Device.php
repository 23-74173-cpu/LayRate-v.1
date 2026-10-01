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
     *
     * The key embeds this device's id (lr_<id>_<random>) so DeviceAuth can
     * look the device up by primary key and verify a single bcrypt hash,
     * instead of loading every active device and hashing against each one
     * per request — see DeviceAuth::resolveDevice() for why that mattered.
     * The id prefix is a lookup hint, not a secret; the random suffix is
     * still what's actually being verified. Requires $this->id to already
     * exist, i.e. call this on a persisted model.
     */
    public function generateApiKey(): string
    {
        $plain = 'lr_' . $this->id . '_' . Str::random(40);

        $this->forceFill([
            'api_key_hash'   => Hash::make($plain),
            'api_key_lookup' => self::lookupFor($plain),
        ])->save();

        return $plain;
    }

    /**
     * Verify a plain API key against the stored hash.
     */
    public function verifyApiKey(string $plainKey): bool
    {
        return Hash::check($plainKey, $this->api_key_hash);
    }

    /**
     * SHA-256 of a plain key, stored in api_key_lookup so DeviceAuth can find
     * the device with one indexed lookup instead of a bcrypt check on every
     * sensor reading. Safe for these keys because they are 40 random
     * characters (a fast hash is only a problem for guessable passwords).
     */
    public static function lookupFor(string $plainKey): string
    {
        return hash('sha256', $plainKey);
    }

    /**
     * Store the fast lookup for a key that just passed verifyApiKey().
     * Lets keys issued before api_key_lookup existed switch to the fast
     * path on their first request, with no change on the device side.
     */
    public function rememberApiKeyLookup(string $plainKey): void
    {
        $this->forceFill(['api_key_lookup' => self::lookupFor($plainKey)])->saveQuietly();
    }

    protected static function booted(): void
    {
        // Any change to the key hash (new key, revoked key) drops the old
        // fast lookup, so a replaced key can never keep working through it.
        static::saving(function (Device $device) {
            if ($device->isDirty('api_key_hash') && ! $device->isDirty('api_key_lookup')) {
                $device->api_key_lookup = null;
            }
        });
    }
}
