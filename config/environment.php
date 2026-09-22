<?php

// Fixed "optimal" reference range for layer hen coop conditions — NOT the
// operator-configured alert threshold (see Setting::thresholds(), edited via
// the Alert Thresholds modal). This is a separate, non-editable reference
// used only to give the operator context: how does their configured
// threshold, or the live sensor reading, compare to a generally recommended
// range. Single source of truth for both the threshold-input helper text
// and the IR Reference Card.

return [
    'optimal_temp_min' => env('OPTIMAL_TEMP_MIN', 18.0),
    'optimal_temp_max' => env('OPTIMAL_TEMP_MAX', 24.0),
    'optimal_humidity_min' => env('OPTIMAL_HUMIDITY_MIN', 50.0),
    'optimal_humidity_max' => env('OPTIMAL_HUMIDITY_MAX', 70.0),
];
