<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    protected $fillable = ['body', 'category', 'cage_id'];

    public const CATEGORIES = ['Mortality', 'Environment', 'Production', 'Reports', 'General'];

    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }

    // Shared entry point for other modules (Mortality, later Environment/
    // Production/Reports) to save a note into the central list under their
    // own category, instead of each controller duplicating creation logic.
    // firstOrCreate so re-selecting an existing note via autofill doesn't
    // spawn a duplicate row.
    public static function createFromModule(string $body, string $category, ?int $cageId = null): self
    {
        return static::firstOrCreate([
            'body'     => $body,
            'category' => $category,
        ], [
            'cage_id' => $cageId,
        ]);
    }
}
