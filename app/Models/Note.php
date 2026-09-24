<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class Note extends Model
{
    protected $fillable = ['body', 'category', 'cage_id'];

    // The section a note belongs to. Notes typed inside another section
    // (mortality, egg logging, feed, hens) are saved here under that
    // section's category; the rest are entered on the Notes page.
    public const CATEGORIES = ['General', 'Production', 'Mortality', 'Hens', 'Feed', 'Environment', 'Reports'];

    public const DEFAULT_CATEGORY = 'General';

    // Same limit as the Notes page form, so a saved note can always be edited there.
    public const MAX_LENGTH = 2000;

    protected $attributes = [
        'category' => self::DEFAULT_CATEGORY,
    ];

    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }

    /**
     * Saved notes offered by the "Use a saved note" picker in a section.
     */
    public static function suggestionsFor(string $category, int $limit = 50): Collection
    {
        return static::where('category', $category)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'body']);
    }

    /**
     * Copies a note typed in another section into the central Notes list.
     *
     * The same text in the same category is stored once, so picking a saved
     * note again (or typing a common note) does not create duplicates. Never
     * throws: the record the note was typed on (mortality log, egg log, ...)
     * is already saved, and a notes problem must not undo or block that.
     */
    public static function saveFromSection(?string $body, string $category, ?int $cageId = null): ?self
    {
        $body = trim((string) $body);

        if ($body === '' || mb_strlen($body) > self::MAX_LENGTH || ! in_array($category, self::CATEGORIES, true)) {
            return null;
        }

        try {
            return static::firstOrCreate(
                ['body' => $body, 'category' => $category],
                ['cage_id' => $cageId]
            );
        } catch (\Throwable $e) {
            Log::warning("Could not save {$category} note to the Notes list: " . $e->getMessage());

            return null;
        }
    }
}
