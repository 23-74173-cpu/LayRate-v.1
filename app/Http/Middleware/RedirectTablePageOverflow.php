<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bookmarked or stale ?page=N links would otherwise render an empty table
 * with no way back (e.g. page 380 of a table that now shows 25 rows per
 * page has no page 380 anymore). When a GET request renders a view whose
 * paginator sits past its last page, redirect once to that paginator's last
 * page, keeping every other query-string parameter (filters, tabs, sort).
 *
 * Single shared place for all record tables — no controller touches this.
 * Only acts on rendered views; JSON/fetch/POST/redirect responses pass
 * through untouched.
 */
class RedirectTablePageOverflow
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('get')) {
            return $response;
        }

        $original = $response->original ?? null;
        if (! $original instanceof View) {
            return $response;
        }

        foreach ($this->paginatorsIn($original->getData()) as $paginator) {
            if ($paginator->total() > 0 && $paginator->currentPage() > $paginator->lastPage()) {
                return redirect()->to(
                    $request->fullUrlWithQuery([$paginator->getPageName() => $paginator->lastPage()])
                );
            }
        }

        return $response;
    }

    /**
     * @return iterable<LengthAwarePaginator>
     */
    private function paginatorsIn(array $data): iterable
    {
        foreach ($data as $value) {
            if ($value instanceof LengthAwarePaginator) {
                yield $value;
            } elseif (is_array($value)) {
                foreach ($value as $nested) {
                    if ($nested instanceof LengthAwarePaginator) {
                        yield $nested;
                    }
                }
            }
        }
    }
}
