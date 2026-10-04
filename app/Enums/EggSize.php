<?php

namespace App\Enums;

/**
 * Single source of truth for egg sizes.
 *
 * Code values are stored in the database (egg_size columns); labels are for
 * display. Display order is small, medium, large, xl, jumbo everywhere —
 * never alphabetical. `unsorted` exists only in stock contexts, never in
 * pre-orders or egg logging.
 */
enum EggSize: string
{
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';
    case Xl = 'xl';
    case Jumbo = 'jumbo';
    case Unsorted = 'unsorted';

    public function label(): string
    {
        return match ($this) {
            self::Small => 'Small',
            self::Medium => 'Medium',
            self::Large => 'Large',
            self::Xl => 'XL',
            self::Jumbo => 'Jumbo',
            self::Unsorted => 'Unsorted',
        };
    }

    /** Display order position (small → jumbo). Unsorted always sorts last. */
    public function order(): int
    {
        return match ($this) {
            self::Small => 0,
            self::Medium => 1,
            self::Large => 2,
            self::Xl => 3,
            self::Jumbo => 4,
            self::Unsorted => 99,
        };
    }

    /** Setting key holding this size's average egg weight in grams. */
    public function weightKey(): ?string
    {
        return match ($this) {
            self::Small => 'egg_weight_small',
            self::Medium => 'egg_weight_medium',
            self::Large => 'egg_weight_large',
            self::Xl => 'egg_weight_xl',
            self::Jumbo => 'egg_weight_jumbo',
            self::Unsorted => null,
        };
    }

    /** Sizes customers can order / that get logged (no unsorted). */
    public static function saleValues(): array
    {
        return ['small', 'medium', 'large', 'xl', 'jumbo'];
    }

    /** Sizes that can sit in stock (sale sizes plus unsorted). */
    public static function stockValues(): array
    {
        return ['small', 'medium', 'large', 'xl', 'jumbo', 'unsorted'];
    }

    /** Display label for a stored size code; falls back to ucfirst for unknowns. */
    public static function labelFor(string $size): string
    {
        return self::tryFrom($size)?->label() ?? ucfirst($size);
    }

    /** XL go-live date (YYYY-MM-DD) from settings, or null when unset. */
    public static function xlGoLive(): ?string
    {
        return \App\Models\Setting::get('egg_size_xl_start');
    }

    /**
     * Whether a date range starting at $from (null = all time) includes
     * dates before the XL go-live. Drives the "XL counted under Large"
     * historical notes — never shown otherwise.
     */
    public static function rangeIncludesPreXl(?string $from): bool
    {
        $goLive = self::xlGoLive();

        if (! $goLive) {
            return false;
        }

        return $from === null || $from < $goLive;
    }

    /** Sort an array of size codes into display order. */
    public static function sortValues(array $values): array
    {
        $order = ['small' => 0, 'medium' => 1, 'large' => 2, 'xl' => 3, 'jumbo' => 4, 'unsorted' => 99];
        usort($values, fn ($a, $b) => ($order[$a] ?? 50) <=> ($order[$b] ?? 50));

        return array_values($values);
    }
}
