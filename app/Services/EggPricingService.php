<?php

namespace App\Services;

use App\Enums\EggSize;

/**
 * Single server home of the egg-pricing rule (mirrored in JS inside the
 * pre-orders Add modal — keep the two in sync, same branches, same order).
 *
 *   trays = floor(count / traySize); loose = count mod traySize
 *   total = trays × tray price + loose part
 *
 * The loose part is loose × piece price when a piece price is set, otherwise
 * a single half-up rounding of (loose × tray price / tray size) at the end —
 * shown as an amount, never as a rounded per-piece figure, so the lines
 * always add up exactly. With no tray price there is no total at all —
 * never 0.00, never partial. All math in integer cents.
 */
class EggPricingService
{
    public static function totalFor(string $size, int $count, mixed $trayPrice, mixed $piecePrice, int $traySize = 30): ?array
    {
        if ($count < 1 || $trayPrice === null || $trayPrice === '') {
            return null;
        }

        $trayCents = self::toCents($trayPrice);
        $trays = intdiv($count, $traySize);
        $loose = $count % $traySize;

        // One loose figure, used for both display and the total, so the
        // breakdown always sums to the total: loose × set piece price, else
        // integer-only half-up of (loose × tray / traySize) — no floats:
        // floor((2 × loose × tray + traySize) / (2 × traySize)).
        $pieceCents = ($piecePrice === null || $piecePrice === '') ? null : self::toCents($piecePrice);
        $derived = $loose > 0 && $pieceCents === null;
        $looseCents = $loose === 0
            ? 0
            : ($pieceCents !== null
                ? $loose * $pieceCents
                : intdiv(2 * $loose * $trayCents + $traySize, 2 * $traySize));

        return [
            'size' => $size,
            'count' => $count,
            'trays' => $trays,
            'loose' => $loose,
            'tray_cents' => $trayCents,
            'piece_cents' => $pieceCents,
            'loose_cents' => $looseCents,
            'derived_piece' => $derived,
            'tray_size' => $traySize,
            'total_cents' => $trays * $trayCents + $looseCents,
        ];
    }

    /** Snapshot columns for a pre-order row, or all-nulls when unpriced. */
    public static function snapshotFor(string $size, int $count, mixed $trayPrice, mixed $piecePrice, int $traySize = 30): array
    {
        $calc = self::totalFor($size, $count, $trayPrice, $piecePrice, $traySize);

        if ($calc === null) {
            return [
                'unit_price_tray' => null,
                'unit_price_piece' => null,
                'total_amount' => null,
                'tray_size' => null,
            ];
        }

        return [
            'unit_price_tray' => self::fromCents($calc['tray_cents']),
            'unit_price_piece' => $calc['piece_cents'] !== null ? self::fromCents($calc['piece_cents']) : null,
            'total_amount' => self::fromCents($calc['total_cents']),
            'tray_size' => $traySize,
        ];
    }

    public static function toCents(mixed $amount): int
    {
        return (int) round((float) $amount * 100, 0, PHP_ROUND_HALF_UP);
    }

    public static function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /** "4 trays × 190.00 + 15 pcs = 95.00 (tray price / 30 per egg)" style breakdown. */
    public static function breakdown(array $calc): string
    {
        $parts = [];

        if ($calc['trays'] > 0) {
            $parts[] = $calc['trays'] . ' ' . ($calc['trays'] === 1 ? 'tray' : 'trays')
                . ' × ' . self::fromCents($calc['tray_cents']);
        }

        if ($calc['loose'] > 0) {
            if ($calc['derived_piece']) {
                $parts[] = $calc['loose'] . ' pcs = ' . self::fromCents($calc['loose_cents'])
                    . ' (tray price / ' . $calc['tray_size'] . ' per egg)';
            } else {
                $parts[] = $calc['loose'] . ' pcs × ' . self::fromCents($calc['piece_cents']);
            }
        }

        return implode(' + ', $parts);
    }
}
