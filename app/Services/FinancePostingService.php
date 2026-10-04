<?php

namespace App\Services;

use App\Models\FeedBatch;
use App\Models\FinanceTransaction;
use App\Models\PreOrder;
use App\Models\Setting;
use Illuminate\Database\QueryException;

/**
 * One home for finance auto-posting (items 3+4). Called from the pre-order
 * status path and the feed batch controller; amounts reuse EggPricingService
 * rounding discipline (integer cents, half-up). Everything here is
 * idempotent: the unique (source_type, source_id, kind) key means a double
 * click or a retried request can never create a second row — the second
 * writer gets the existing row back.
 *
 * Guarded failures throw \DomainException with a user-facing message; the
 * callers run inside the source's DB transaction, so a posting failure
 * rolls the status/batch change back too.
 */
class FinancePostingService
{
    public const INCOME_CATEGORY = 'Egg Sales';
    public const EXPENSE_CATEGORY = 'Feed Cost';

    /** Cutover date (Y-m-d) or null when auto-posting is OFF. */
    public static function cutover(): ?string
    {
        $value = Setting::get('finance_autopost_start');

        return is_string($value) && $value !== '' ? substr($value, 0, 10) : null;
    }

    public static function autopostOn(): bool
    {
        return self::cutover() !== null;
    }

    // ── Pre-order income ─────────────────────────────────────────────

    public static function postOrderIncome(PreOrder $order, int $userId): FinanceTransaction
    {
        self::guardCategory(self::INCOME_CATEGORY);

        if ($order->total_amount === null) {
            throw new \DomainException(
                'No price recorded for this order — enter the income manually in Finance.'
            );
        }

        $cutover = self::cutover();
        $paidDate = substr((string) $order->paid_at, 0, 10);

        if ($cutover === null || $paidDate < $cutover) {
            throw new \DomainException(
                'Auto-posting is off or this payment predates the cutover — enter the income manually in Finance.'
            );
        }

        return self::firstOrCreateLinked(
            'pre_order',
            $order->id,
            'original',
            fn () => [
                'type' => 'income',
                'category' => self::INCOME_CATEGORY,
                'amount' => EggPricingService::fromCents(
                    EggPricingService::toCents($order->total_amount)
                ),
                'description' => "Pre-order #{$order->id} — {$order->customer_name} ({$order->egg_count} {$order->egg_size} eggs)",
                'date' => $paidDate,
                'cage_id' => null,
                'recorded_by' => $userId,
            ]
        );
    }

    public static function reverseOrderIncome(PreOrder $order, int $userId): ?FinanceTransaction
    {
        $original = self::findLinked('pre_order', $order->id, 'original');

        if ($original === null) {
            return null;
        }

        return self::firstOrCreateLinked(
            'pre_order',
            $order->id,
            'reversal',
            fn () => [
                'type' => $original->type,
                'category' => $original->category,
                'amount' => number_format(-1 * (float) $original->amount, 2, '.', ''),
                'description' => "Reversal of pre-order #{$order->id} income",
                'date' => ReportingDateService::reportingDateString(),
                'cage_id' => $original->cage_id,
                'recorded_by' => $userId,
            ]
        );
    }

    // ── Feed batch expenses ──────────────────────────────────────────

    public static function batchAmountCents(FeedBatch $batch): ?int
    {
        if ($batch->unit_cost === null || $batch->unit_cost === ''
            || $batch->total_quantity_kg === null || $batch->total_quantity_kg === ''
        ) {
            return null;
        }

        return self::mulCents($batch->unit_cost, $batch->total_quantity_kg);
    }

    public static function batchEligible(FeedBatch $batch): bool
    {
        $cutover = self::cutover();

        if ($cutover === null) {
            return false;
        }

        $received = substr((string) $batch->date_received, 0, 10);
        $created = substr((string) $batch->created_at, 0, 10);

        return $received >= $cutover && $created >= $cutover;
    }

    /**
     * Sync a batch's expense row after create/edit. $old is the
     * pre-update [unit_cost, total_quantity_kg, date_received] triple, or
     * null on create. Pre-cutover-dated batches are never posted or altered.
     */
    public static function syncBatchExpense(FeedBatch $batch, ?array $old, int $userId): ?FinanceTransaction
    {
        if (! self::batchEligible($batch)) {
            return null;
        }

        $linked = self::findLinked('feed_batch', $batch->id, 'original');
        $cents = self::batchAmountCents($batch);

        if ($linked === null && $cents === null) {
            return null;
        }

        self::guardCategory(self::EXPENSE_CATEGORY);
        $date = substr((string) $batch->date_received, 0, 10);

        if ($linked === null) {
            if ($cents === null) {
                return null;
            }

            return self::createLinked('feed_batch', $batch->id, 'original', [
                'type' => 'expense',
                'category' => self::EXPENSE_CATEGORY,
                'amount' => EggPricingService::fromCents($cents),
                'description' => "Feed batch {$batch->batch_code} ({$batch->total_quantity_kg} kg)",
                'date' => $date,
                'cage_id' => null,
                'recorded_by' => $userId,
            ]);
        }

        $oldCents = $old === null ? null : self::centsOfTriple($old);

        if ($cents === null) {
            // Cost cleared: the posted expense is no longer true — reverse it.
            return self::reverseBatchExpense($batch, $userId);
        }

        if ($oldCents === null
            || $cents !== $oldCents
            || ($old['date_received'] ?? null) !== $date
        ) {
            $linked->update([
                'amount' => EggPricingService::fromCents($cents),
                'date' => $date,
            ]);
        }

        return $linked->refresh();
    }

    public static function reverseBatchExpense(FeedBatch $batch, int $userId): ?FinanceTransaction
    {
        $original = self::findLinked('feed_batch', $batch->id, 'original');

        if ($original === null) {
            return null;
        }

        return self::firstOrCreateLinked(
            'feed_batch',
            $batch->id,
            'reversal',
            fn () => [
                'type' => $original->type,
                'category' => $original->category,
                'amount' => number_format(-1 * (float) $original->amount, 2, '.', ''),
                'description' => "Reversal of feed batch {$batch->batch_code} expense",
                'date' => ReportingDateService::reportingDateString(),
                'cage_id' => $original->cage_id,
                'recorded_by' => $userId,
            ]
        );
    }

    // ── Internals ────────────────────────────────────────────────────

    protected static function guardCategory(string $category): void
    {
        if (! in_array($category, FinanceTransaction::allCategories(), true)) {
            throw new \DomainException(
                "Cannot auto-post: the '{$category}' category is not configured. An admin must add it first."
            );
        }
    }

    protected static function findLinked(string $type, int $id, string $kind): ?FinanceTransaction
    {
        return FinanceTransaction::where('source_type', $type)
            ->where('source_id', $id)
            ->where('kind', $kind)
            ->first();
    }

    protected static function createLinked(string $type, int $id, string $kind, array $attributes): FinanceTransaction
    {
        return FinanceTransaction::create($attributes + [
            'source_type' => $type,
            'source_id' => $id,
            'kind' => $kind,
        ]);
    }

    protected static function firstOrCreateLinked(string $type, int $id, string $kind, callable $attributes): FinanceTransaction
    {
        $existing = self::findLinked($type, $id, $kind);

        if ($existing !== null) {
            return $existing;
        }

        try {
            return self::createLinked($type, $id, $kind, $attributes());
        } catch (QueryException $e) {
            // Lost a race with another request holding the same unique key.
            $existing = self::findLinked($type, $id, $kind);

            if ($existing === null) {
                throw $e;
            }

            return $existing;
        }
    }

    protected static function centsOfTriple(array $triple): ?int
    {
        $cost = $triple['unit_cost'] ?? null;
        $kg = $triple['total_quantity_kg'] ?? null;

        if ($cost === null || $cost === '' || $kg === null || $kg === '') {
            return null;
        }

        return self::mulCents($cost, $kg);
    }

    /**
     * Exact (cost × kg) in cents, half-up — BCMath on the decimal strings,
     * so no binary float is ever involved (19.99 × 2.5 is exactly 49.975,
     * which floats would read as 49.974999…). Requires ext-bcmath, which
     * the Pi's PHP provides.
     */
    protected static function mulCents(mixed $cost, mixed $kg): int
    {
        $total = bcmul((string) $cost, (string) $kg, 6);

        return (int) bcadd(bcmul($total, '100', 6), '0.5', 0);
    }
}
