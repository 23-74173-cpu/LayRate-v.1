<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Shared record-table filtering (one consistent system for every table).
 *
 * Each controller declares an allow-list spec — canonical param => rule —
 * using ONLY the standard parameter names:
 *   cage_id (alias: cage), size (alias: egg_size),
 *   from / to (aliases: date_from / date_to, Y-m-d),
 *   q (alias: search, text), status, breed, read, sort, page.
 * Old alias names keep working so bookmarked links do not break; the
 * canonical name always wins when both are present.
 *
 * Rule shapes:
 *   ['type' => 'id', 'column' => 'cage_id', 'label' => 'Cage']
 *   ['type' => 'enum', 'column' => 'egg_size', 'options' => ['small' => 'Small', ...], 'label' => 'Size']
 *   ['type' => 'multi', 'column' => 'logged_via', 'options' => [...], 'label' => 'Logged Via'] (checkbox group → whereIn)
 *   ['type' => 'date', 'column' => 'harvested_date', 'bound' => 'min'|'max', 'label' => 'From']
 *   ['type' => 'text', 'columns' => ['batch_code'], 'relation' => [['cage', 'cage_code']], 'label' => 'Search']
 *   ['type' => 'callback', 'options' => ['fresh' => 'Fresh', ...], 'label' => 'Freshness',
 *    'handler' => fn ($query, $value) => ...]
 *
 * Conventions (never concatenated into SQL — every value goes through the
 * query builder as a bound parameter):
 * - extractRecordFilters(): read + validate the query string, return only
 *   active filters keyed by canonical name. Unknown params are ignored.
 *   A From-after-To range is dropped (the UI blocks it with an inline error
 *   before any request is sent).
 * - applyRecordFilters(): add the wheres. Eager loads stay the
 *   controller's job (declare them as today) so filtering never adds N+1.
 * - describeRecordFilters(): chip descriptors [param, label, display] for
 *   x-filter-chips, plus the result-line counts.
 * - resolveTablePerPage(): optional ?per_page override, hard-capped at 100.
 *   There is no rows-per-page selector in the UI; this is only a guard.
 */
trait FiltersRecords
{
    /**
     * Canonical param => alias names (old names keep working).
     */
    public static function filterParamAliases(): array
    {
        return [
            'cage_id' => ['cage'],
            'size'    => ['egg_size'],
            'from'    => ['date_from'],
            'to'      => ['date_to'],
            'q'       => ['search'],
        ];
    }

    /**
     * Read and validate the query string against $spec.
     *
     * @return array canonical param => validated value (active filters only)
     */
    protected function extractRecordFilters(Request $request, array $spec): array
    {
        $aliases = static::filterParamAliases();
        $filters = [];

        foreach ($spec as $canonical => $rule) {
            $names = array_merge([$canonical], $aliases[$canonical] ?? []);
            $raw = null;
            foreach ($names as $name) {
                $v = $request->query($name);
                if ($v !== null && $v !== '' && $v !== []) {
                    $raw = $v;
                    break;
                }
            }
            if ($raw === null) {
                continue;
            }
            // Multi-value (checkbox groups): keep only known options.
            if (($rule['type'] ?? null) === 'multi') {
                $keys = array_keys($rule['options'] ?? []);
                $vals = [];
                foreach ((array) $raw as $v) {
                    if (is_string($v) && in_array($v, $keys, true) && ! in_array($v, $vals, true)) {
                        $vals[] = $v;
                    }
                }
                if (empty($vals)) {
                    continue;
                }
                $filters[$canonical] = $vals;
                continue;
            }
            if (is_array($raw)) {
                continue;
            }
            $value = $this->validateFilterValue((string) $raw, $rule);
            if ($value === null) {
                continue;
            }
            $filters[$canonical] = $value;
        }

        // From-after-To can only arrive via a hand-built URL (the UI blocks
        // it inline). Drop the range rather than querying an empty set.
        if (isset($filters['from'], $filters['to']) && $filters['from'] > $filters['to']) {
            unset($filters['from'], $filters['to']);
        }

        return $filters;
    }

    /**
     * @return string|int|null validated value, null when invalid
     */
    private function validateFilterValue(string $raw, array $rule): string|int|null
    {
        $type = $rule['type'] ?? null;

        if (isset($rule['options']) && ($type === 'enum' || $type === 'callback')) {
            return array_key_exists($raw, $rule['options']) ? $raw : null;
        }

        return match ($type) {
            'id' => ctype_digit($raw) ? (int) $raw : null,
            'date' => $this->validateFilterDate($raw),
            'text' => ($v = trim(mb_substr($raw, 0, 100))) !== '' ? $v : null,
            'callback' => $raw !== '' ? $raw : null,
            default => null,
        };
    }

    private function validateFilterDate(string $raw): ?string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
            return null;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $raw : null;
    }

    /**
     * Add the allow-listed wheres to $query.
     */
    protected function applyRecordFilters($query, array $filters, array $spec)
    {
        foreach ($filters as $param => $value) {
            $rule = $spec[$param] ?? null;
            if (! $rule) {
                continue;
            }
            match ($rule['type']) {
                'id', 'enum' => $query->where($rule['column'], $value),
                'multi' => $query->whereIn($rule['column'], (array) $value),
                'date' => $query->where(
                    $rule['column'],
                    ($rule['bound'] ?? 'min') === 'max' ? '<=' : '>=',
                    $value
                ),
                'text' => $query->where(function ($q) use ($rule, $value) {
                    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value) . '%';
                    foreach ((array) ($rule['columns'] ?? []) as $col) {
                        $q->orWhere($col, 'LIKE', $like);
                    }
                    foreach ((array) ($rule['relation'] ?? []) as [$rel, $col]) {
                        $q->orWhereHas($rel, fn ($r) => $r->where($col, 'LIKE', $like));
                    }
                }),
                'callback' => $rule['handler']($query, $value, $filters),
                default => null,
            };
        }

        return $query;
    }

    /**
     * Chip descriptors for x-filter-chips: [param, label, display].
     */
    protected function describeRecordFilters(array $filters, array $spec): array
    {
        $chips = [];
        foreach ($filters as $param => $value) {
            $rule = $spec[$param] ?? null;
            if (! $rule) {
                continue;
            }
            if (is_array($value)) {
                $display = implode(', ', array_map(
                    fn ($v) => $rule['options'][$v] ?? $v,
                    $value
                ));
            } else {
                $display = $rule['options'][$value]
                    ?? (in_array($param, ['from', 'to'], true) ? $this->displayFilterDate((string) $value) : $value);
            }
            $chips[] = [
                'param' => $param,
                'label' => $rule['label'] ?? $param,
                'display' => $display,
            ];
        }

        return $chips;
    }

    private function displayFilterDate(string $ymd): string
    {
        $t = \DateTimeImmutable::createFromFormat('Y-m-d', $ymd);
        return $t ? $t->format('m/d/Y') : $ymd;
    }

    /**
     * Optional ?per_page override, hard-capped at 100. No UI exposes it.
     */
    protected function resolveTablePerPage(Request $request, int $default): int
    {
        $perPage = (int) $request->query('per_page', $default);
        if ($perPage < 1) {
            return $default;
        }

        return min($perPage, 100);
    }
}
