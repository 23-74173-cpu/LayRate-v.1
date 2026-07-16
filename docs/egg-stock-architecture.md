# Egg Stock Architecture

## 1. Overview

The egg management pipeline flows: **Egg Logging** → **Egg Size Logs** → **Stock Pool** → **Stock Batches** → **Pre-Orders**.

Eggs are first logged against a cage/slot via the Egg Logging UI, which records a total `egg_count` in `production_logs`. A size breakdown (Small/Medium/Large/Jumbo) is optional at this stage. When provided, each size bucket is written to `egg_size_logs` rows linked to the production log. When omitted, a single `egg_size_logs` row with `egg_size = 'unsorted'` is created automatically.

The **stock pool** is the number of eggs available to stock (or pre-order), computed per size as: `sum(egg_size_logs.count) - sum(egg_stock_batches.count) - sum(pre_orders.egg_count WHERE status = 'pending')`. This ensures eggs cannot be stocked or pre-ordered beyond what was actually produced — a preventative measure against inventory drift where stock records exceed real production.

Key files:
- `app/Http/Controllers/EggLoggingController.php` — egg logging CRUD
- `app/Http/Controllers/EggStockController.php` — stock batch CRUD + classification
- `app/Http/Controllers/PreOrderController.php` — pre-order CRUD
- `app/Models/EggStockBatch.php` — pool queries (`getAvailablePool`, `getAvailablePoolForSize`, `createWithinPool`, `updateWithinPool`)
- `app/Models/PreOrder.php` — pre-order pool queries (`createWithinPool`, `updateWithinPool`)
- `resources/views/eggs/stocks.blade.php` — stock management UI

## 2. The "Unsorted" Pool Concept

### Why it exists

When eggs are logged without a size breakdown, there is no `egg_size_logs` row for any specific size (Small/Medium/Large/Jumbo). Without a size, they would be invisible to the stock system — `getAvailablePoolForSize()` only reads from `egg_size_logs`, so unsized eggs would never appear in any pool and could never be stocked.

The "unsorted" pool (`egg_size = 'unsorted'`) solves this: every production log creates at least one `egg_size_logs` row, even if no explicit sizes are given. These eggs accumulate in the unsorted pool and are available to stock as "Unsorted" egg stock batches.

### Two-tier design (optional classification)

A deliberate decision was made to keep size breakdown **optional at logging time** to avoid workflow friction for farms that do not grade eggs during collection. Classification into real sizes instead happens later at **stocking time** via the **Classify Unsorted to Sizes** section in the Add Stock Batch modal:

1. Select `Egg Size = Unsorted` in the modal.
2. Check **Classify Unsorted to Sizes**.
3. Enter breakouts for Small, Medium, Large, Jumbo (must sum to the total count).
4. The server deducts from `egg_size_logs(unsorted)` rows (oldest first) and creates one `egg_size_logs` row per classified size plus one `egg_stock_batch` per size — all in a single `DB::transaction()`.

### Alternatives considered and rejected

| Alternative | Reason rejected |
|---|---|
| Mandatory size breakdown at logging | Adds friction to the egg logging workflow; not all farms grade at collection |
| Auto-default unsized eggs to one size (e.g. Medium) | Fabricates data — eggs of unknown size should be tracked as unknown |
| Proportionally distribute unsized eggs across sizes based on historical distribution | Fabricates data with no actual grading information; adds complexity |
| A separate "general" pool | Early prototype used the name "general" — renamed to "unsorted" for clarity that these are eggs awaiting classification, not a catch-all default |

## 3. Pool Scoping: Per-Cage vs. Farm-Wide

The pool calculation can be scoped either **per-cage** or **farm-wide**, controlled by the **Source Cage** dropdown in the Add Stock / pre-order modals.

- **All Cages (farm-wide)**: Sums `egg_size_logs.count`, `egg_stock_batches.count`, and `pre_orders.egg_count` across the entire farm. The `$cageId` parameter is `null`.
- **Specific cage**: Filters each table to entries associated with that cage via the `production_logs → cage_slots → cage_id` join path. The `$cageId` parameter is the cage ID.

Implementation in `EggStockBatch::getAvailablePoolForSize()`:
```
$logged  = EggSizeLog::where('egg_size', $size)
             ->when($cageId, fn($q) => $q->whereHas('productionLog.cageSlot', fn($sq) => $sq->where('cage_id', $cageId)))
             ->sum('count');
$stocked = EggStockBatch::where('egg_size', $size)
             ->when($cageId, fn($q) => $q->where('cage_id', $cageId))
             ->sum('count');
// + pre_orders for farm-wide scope
```

This was a deliberate fix for an earlier inconsistency where the Source Cage dropdown appeared to filter the pool but actually showed farm-wide numbers regardless of selection.

## 4. Pool Locking / Concurrency Safety

To prevent race conditions where concurrent stock creation, pre-order creation, or unsorted-classification requests could over-commit the same eggs, all pool-modifying operations use `lockForUpdate()` (SELECT ... FOR UPDATE) within a `DB::transaction()`.

The following tables are locked in the critical section of `EggStockBatch::getAvailablePoolForSize(lockForUpdate: true)`:

| Table | Lock condition |
|---|---|
| `egg_stock_batches` | Always — same egg_size |
| `egg_size_logs` | Always — same egg_size (± cage filter) |
| `pre_orders` | Only when farm-wide scope (no cage_id) — same egg_size + status = 'pending' |

Callers that use this locking:
- `EggStockBatch::createWithinPool()` (stock batch creation)
- `EggStockBatch::updateWithinPool()` (stock batch edit — re-checks if size or count increased)
- `PreOrder::createWithinPool()` (pre-order creation)
- `PreOrder::updateWithinPool()` (pre-order edit)

The `storeClassified()` method in `EggStockController` runs inside its own `DB::transaction()` and uses atomic `decrement()` on `egg_size_logs` rows. The `lockForUpdate` on `egg_size_logs` in `getAvailablePoolForSize` ensures that concurrent classification and stock-creation operations are serialized — the classification's decrement will block until the stock-creation transaction completes, and vice versa.

### History

Pool locking was added incrementally:
1. Initially `egg_stock_batches` and `pre_orders` were locked in `getAvailablePoolForSize()` (first implementation).
2. `egg_size_logs` locking was added later (`EggStockBatch.php:28-32`) after being flagged as a gap in a pre-push safety review — a concurrent `storeClassified()` call could decrement `egg_size_logs` between the pool check and the row insert, causing a small over-commit.

## 5. Authorization Decisions

- **Stock batch deletion** (`EggStockController::destroy()`) requires the `admin` middleware, matching the pattern used by `EggLoggingController::destroy()` and `MortalityController::destroy()`. Stock batches are production data and should not be deletable by operators.
- Stock batch **creation** and **editing** remain accessible to all authenticated users (both `admin` and `operator` roles), as these are day-to-day inventory operations.
- **Pre-order deletion** does not currently have admin middleware applied, consistent with some other non-production-data destroy routes in the codebase (feed batch, hardware, notes). If PreOrders grow in significance, this should be re-evaluated.

## 6. Known Follow-Ups / Not Yet Addressed

- **`storeClassified()` does not lock `egg_size_logs` rows before reading** — it reads unsorted records without `lockForUpdate`, then decrements atomically. While the atomic `decrement` and the DB-level `unsigned` constraint prevent negative counts, there is a theoretical race where two concurrent classification operations could both read the same unsorted records and attempt to decrement more than available. The `unsigned` constraint would trigger a MySQL out-of-range error on the second decrement, rolling back that transaction. This is safe but not elegant — if the pattern proves problematic, add `lockForUpdate` to the read in `storeClassified()`.
- **No automated alert generation for low stock** — the system does not warn when a size's pool drops below a configurable threshold.
- **No stock batch aging / expiry logic** — the `freshness_status` attribute exists but no automatic flagging or removal of old stock.
- **`BackfillUnsortedSizeLogs` command** (`app/Console/Commands/BackfillUnsortedSizeLogs.php`) is a one-time migration tool for existing deployments. Run `php artisan layrate:backfill-unsorted-size-logs` after deploying the egg_size_logs migration to bring historical production logs into the pool system.
