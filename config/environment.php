<?php

// Standard (optimal) coop conditions for laying hens, shown on the
// Environment page's IR Reference Card next to the current readings.
//
// This is a fixed reference, separate from the alert thresholds the admin
// sets in the Alert Thresholds dialog (Setting::thresholds()): the
// thresholds decide when an alert fires, this range is what the hens are
// most comfortable in. Change the defaults here, or override them per farm
// in .env (then run `php artisan config:clear`).

return [
    'optimal' => [
        'temp_min' => (float) env('OPTIMAL_TEMP_MIN', 18),
        'temp_max' => (float) env('OPTIMAL_TEMP_MAX', 24),
        'hum_min'  => (float) env('OPTIMAL_HUM_MIN', 50),
        'hum_max'  => (float) env('OPTIMAL_HUM_MAX', 70),
    ],
];
