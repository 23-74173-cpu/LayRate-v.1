<?php

// Default page sizes for the system's record tables. The one place these
// numbers are defined — every paginated record table (stocks batches, recent
// logs, pre-orders, finance transactions, mortality logs, egg logging
// history, feed batches and logs, notifications, hardware, environment logs,
// notes, report previews, mortality/culling/removal records) reads its page
// size from here, so changing it later is one edit.
//
// 'per_page' is the standard size (10 rows). 'per_page_chickens' is a
// separate key (10 cage groups) for the hens inventory list, where each
// "row" is a cage card with all of its per-hen rows, checkboxes and action
// buttons — kept independent so the two sizes can diverge again later.
//
// Deliberately NOT covered: dashboard widgets and KPI/chart queries, modal
// and popup lists, dropdowns, "top N" lists, CSV/Excel/print exports, the
// mobile-app API endpoints (including the separate mobile-api service), and
// anything where a small size is structural (e.g. report section pagers).

return [
    'per_page' => 10,

    'per_page_chickens' => 10,
];
