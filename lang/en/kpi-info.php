<?php

/*
|--------------------------------------------------------------------------
| KPI Card Explanations
|--------------------------------------------------------------------------
|
| Short plain-language explanations for every KPI card, shown in the card's
| info popover. Kept here (rather than inline) so wording stays consistent
| and proofreadable in one place. Each entry: what it measures, how it is
| calculated (matching the real code), and the period/filters it respects.
| Benchmarks appear only where the app already defines thresholds.
|
*/

return [

    'hens' => [
        'total' => 'Live hens currently in active cages. Counted from hen records marked active. Follows the cage filter.',
    ],

    'production' => [
        'hdep-today' => 'Hen-day egg production for today, as a percentage. Eggs collected today divided by hens in active cages, times one hundred. Follows the cage filter and covers today only. When today differs from yesterday, a pill shows the change.',
        'eggs-today' => 'Eggs collected today across active cages. The sum of today\u{2019}s production logs. Follows the cage filter and covers today only. When today differs from yesterday, a pill shows the change.',
        'lifetime-eggs' => 'Every egg ever logged, across all cages and all time. A running sum of production logs. When eggs were added today, a pill shows how many.',
    ],

    'environment' => [
        'avg-temp' => 'Average coop temperature right now. The mean of the latest reading from each cage with a sensor. Status colors follow your alert thresholds.',
        'avg-humidity' => 'Average coop humidity right now. The mean of the latest reading from each cage with a sensor. Status colors follow your alert thresholds.',
        'active-sensors' => 'Cages with a recent temperature or humidity reading. If this number drops, check sensor power and placement.',
    ],

    'feed' => [
        'avg-cp' => 'Average crude protein across feed batches for the week. The mean of batch protein percentages. Batches at or above 17.5 percent show green in the batch list.',
        'avg-per-cage-day' => 'Average feed eaten per cage per day over the last 7 days. Total feed used, divided by active cages and by 7 days.',
        'total-week' => 'Feed consumed in the last 7 days, across all cages. The sum of consumption logs. When feed was logged today, a pill shows today\u{2019}s share.',
        'cost-month' => 'Feed money spent this month. Consumption multiplied by each batch\u{2019}s unit cost. Shows a dash when no batch costs are recorded.',
        'fcr-current' => 'Feed conversion for this period: kilograms of feed per kilogram of egg mass. Lower is better. Under 2.5 is good and above 4 needs attention, per your FCR settings.',
        'fcr-consumed' => 'Total feed behind the FCR table below. The sum of feed kilograms over the periods shown.',
        'fcr-egg-mass' => 'Estimated egg mass behind the FCR table. Egg counts converted with your per-size weights, or the fallback weight where sizes were not logged.',
    ],

    'flock' => [
        'mortality-today' => 'Hens lost today. The sum of today\u{2019}s mortality records. Any number above zero is worth checking against causes.',
        'livability' => 'Share of the starting flock still alive. Live hens divided by hens ever placed, as a percentage.',
        'mortality-yesterday' => 'Hens lost yesterday. The sum of yesterday\u{2019}s mortality records.',
    ],

    'hens-page' => [
        'deaths-today' => 'Hens that died today. The sum of today\u{2019}s mortality records across all cages.',
        'livability-rate' => 'Share of all hens ever placed that are still alive. Live hens divided by total hens placed.',
        'total-mortality' => 'Every recorded hen death, all time. A running sum of mortality records.',
    ],

    'mortality' => [
        'cage-today' => 'Deaths recorded in this cage today. The sum of today\u{2019}s mortality records for the cage.',
    ],

    'eggs' => [
        'lifetime-total' => 'Every egg ever logged. A running sum of production logs since day one.',
        'timeline-records' => 'Daily log entries behind the timeline chart. The count of log days aggregated below.',
        'active-cages' => 'Cages contributing production records. The count of active cages in the breakdown.',
        'size-records' => 'Eggs logged with a recorded size. The sum of size-classified entries.',
    ],

    'stocks' => [
        'size-stock' => 'Harvested eggs of this size currently on hand. Pool stock minus fulfilled orders. The subtext shows trays and available eggs.',
        'sold' => 'Eggs sold through fulfilled pre-orders. The sum of fulfilled order quantities.',
    ],

    'analytics' => [
        'scope-cage' => 'The cage these charts cover, or all cages. Follows the cage selector above the charts.',
        'scope-breed' => 'The breed in the selected cage, or mixed when all cages are selected.',
        'avg-hdep' => 'Average hen-day production over the selected period. The mean of daily HDEP values.',
        'best-day' => 'The highest single-day HDEP in the selected period.',
        'worst-day' => 'Lowest single-day HDEP in the selected period. A sharp dip is worth checking against feed, water, and heat notes.',
        'flock-age' => 'Average age of hens in the selected cage, in weeks. Shows a dash when no single cage is selected.',
    ],

    'finance' => [
        'income' => 'Recorded farm income. The sum of income entries under the current filters. Totals always match the listed table.',
        'expenses' => 'Recorded farm expenses. The sum of expense entries under the current filters.',
        'net' => 'Income minus expenses under the current filters. A negative number means expenses ran higher.',
    ],

    'forecast' => [
        'weeks-in-month' => 'Calendar weeks touching the displayed month. Partial edge weeks are counted.',
        'days-in-month' => 'Calendar days in the displayed month.',
        'forecast-days' => 'Days with a forecast value in the displayed month. Days with no history to project from are skipped.',
        'current-week' => 'Which week of the month today falls in.',
    ],

    'hardware' => [
        'active' => 'Devices currently active. The count of hardware entries with active status.',
        'breakbeam' => 'Working egg-counter sensors. Active devices of type IR breakbeam.',
        'dht22' => 'Working climate sensors. Active devices of type DHT22.',
        'faulty' => 'Devices flagged faulty. Any number above zero is worth inspecting.',
    ],

    'preorders' => [
        'size-availability' => 'Sellable eggs of this size. Produced minus committed to open orders. A shortfall line appears when orders exceed stock.',
    ],

    'egg-logging' => [
        'eggs-today' => 'Eggs logged today across all active cages. The sum of the egg counts in the Cage Overview below, and it updates live as slots are logged or sensors report.',
        'hdep-today' => 'Hen-day egg production for today, as a percentage. Eggs logged today divided by the hens placed in active cages, times one hundred. Updates live with the egg count.',
        'slots-logged' => 'Cage slots with an egg log for today, out of all slots in active cages. The subtext shows how many are still left to log.',
        'cages-complete' => 'Active cages where every slot has been logged today. A cage counts once all of its slots have a log for today.',
    ],

    'cages' => [
        'active' => 'Cages currently in use, out of every cage set up on the farm. Inactive cages are kept for history but take no hens.',
        'hens-housed' => 'Active hens placed in active cages, out of the total hen capacity of those cages. The subtext shows how full the farm is.',
        'open-spaces' => 'Hen spaces still free in active cages: capacity minus hens placed. The subtext counts slots that are completely empty.',
        'sensor-coverage' => 'Slots in active cages covered by an active IR breakbeam sensor (directly or through a multi-slot sensor), out of all slots in active cages.',
    ],

    'recent-logs' => [
        'records' => 'Egg log records that match the filters above. With no filters, every record ever logged.',
        'eggs' => 'Total eggs across the records that match the filters above.',
        'sensor-share' => 'Share of the matching records that came from the IR sensors instead of manual entry.',
        'overridden' => 'Matching records where a person corrected a sensor count with a verified override.',
    ],

    'calendar' => [
        'month-total' => 'Eggs collected in the month shown on the calendar. Follows the cage selected in the calendar.',
        'daily-average' => 'Average eggs per logged day in the month shown: the month total divided by the days that have at least one log.',
        'best-day' => 'The day with the most eggs collected in the month shown, and its total.',
        'days-logged' => 'Days in the month shown that have at least one egg log, out of the days so far (or the whole month, for past months).',
    ],

    'notes' => [
        'total' => 'Every note saved, from this page and from the notes typed in other sections.',
        'this-week' => 'Notes added in the last 7 days, today included.',
        'top-section' => 'The section with the most notes, and how many it has.',
        'cage-linked' => 'Notes tied to a specific cage, out of all notes.',
    ],

    'profile' => [
        'egg-logs' => 'Daily egg log entries recorded under your account.',
        'eggs-total' => 'Eggs across all entries recorded under your account.',
        'feed-logs' => 'Feed log entries recorded under your account.',
        'mortality-logs' => 'Mortality records recorded under your account.',
    ],

];
