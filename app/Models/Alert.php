<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;

class Alert extends Model
{
    public $timestamps = false;

    protected $fillable = ['cage_id', 'alert_type', 'message', 'is_read', 'triggered_at', 'dedup_key', 'alert_day'];

    protected $casts = [
        'triggered_at' => 'datetime',
        'created_at'   => 'datetime',
        'is_read'      => 'boolean',
        'alert_day'    => 'date',
    ];

    /**
     * The dedup identity for "one alert per (scope, type[, size]) per reporting
     * day". Stable string so it can be both a fast-path EXISTS predicate and a
     * real UNIQUE index column:
     *   "{cage_id|0}:{alert_type}[:{size}]"  e.g. "7:mortality_spike", "0:low_stock_eggs:medium".
     */
    public static function dedupKey(?int $cageId, string $type, ?string $scope = null): string
    {
        return ($cageId ?? 0) . ':' . $type . ($scope !== null ? ':' . $scope : '');
    }

    /**
     * Create an alert, treating a UNIQUE(dedup_key, alert_day) violation as
     * "already created today" instead of an error. The application-level
     * EXISTS check stays as the fast path; this is the DB-level backstop.
     */
    public static function createDeduped(array $attributes): ?self
    {
        try {
            return self::create($attributes);
        } catch (QueryException $e) {
            $message = strtolower($e->getMessage());
            $isUnique = str_contains($message, 'unique constraint')   // SQLite
                || str_contains($message, 'duplicate entry')          // MySQL
                || ($e->errorInfo[1] ?? null) === 1062;               // MySQL errno

            if ($isUnique) {
                return null;
            }

            throw $e;
        }
    }

    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }
}