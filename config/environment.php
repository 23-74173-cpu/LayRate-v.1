<?php

// Standard (optimal) coop conditions for laying hens. The one place these
// numbers are defined, read through EnvironmentStatusService::optimalRange()
// by both:
//   - the IR Reference Card on the Environment page (live DHT22 reading vs.
//     this range), and
//   - the one-line hints under the Alert Thresholds inputs (the value the
//     operator types vs. this range).
//
// This is a fixed reference, not editable in the app. It is separate from the
// alert thresholds the operator sets in the Alert Thresholds dialog
// (Setting::thresholds()): the thresholds decide when an alert fires and stay
// fully manual; this range only gives context. Change the defaults here, or
// override them per farm in .env (then run `php artisan config:clear`).

return [
    'optimal' => [
        'temp_min' => (float) env('OPTIMAL_TEMP_MIN', 18),
        'temp_max' => (float) env('OPTIMAL_TEMP_MAX', 24),
        'hum_min'  => (float) env('OPTIMAL_HUMIDITY_MIN', 50),
        'hum_max'  => (float) env('OPTIMAL_HUMIDITY_MAX', 70),
    ],
];
