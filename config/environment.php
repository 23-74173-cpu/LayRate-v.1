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
    // How often a DHT22 reading is stored, per cage, in seconds. The Arduino
    // sends one every 2 seconds (~43,000 rows per sensor per day), far more
    // than coop temperature/humidity need, and the table size was what made
    // the Dashboard and Environment pages slower every day. Readings in
    // between are still checked for alerts and still count as the sensor's
    // heartbeat; they just aren't saved. The fan's AUTO mode runs on the
    // Arduino's own readings, so it is not affected. 0 = store every reading.
    'dht22_save_interval_seconds' => (int) env('DHT22_SAVE_INTERVAL_SECONDS', 30),

    'optimal' => [
        'temp_min' => (float) env('OPTIMAL_TEMP_MIN', 18),
        'temp_max' => (float) env('OPTIMAL_TEMP_MAX', 24),
        'hum_min'  => (float) env('OPTIMAL_HUMIDITY_MIN', 50),
        'hum_max'  => (float) env('OPTIMAL_HUMIDITY_MAX', 70),
    ],
];
