<?php

/**
 * Feed Conversion Ratio (FCR) threshold configuration.
 *
 * FCR = kg feed consumed ÷ kg egg mass produced.
 * These are standard layer-hen benchmark defaults. Expected FCR varies
 * significantly by breed, flock age, feed formulation, and management.
 *
 * NOTE: Pre-lay pullets, molting flocks, or very young hens may show
 * artificially high FCR values that reflect low egg mass rather than
 * poor feed efficiency. The farm manager should review and adjust these
 * thresholds based on their specific flock profile.
 *
 * Threshold bands:
 *   Good     ≤  good_threshold          — efficient conversion
 *   Warning   good_threshold <  ≤  warning_threshold  — monitor closely
 *   Critical  > warning_threshold        — investigate feed or flock health
 */
return [
    'good_threshold'    => 2.5,
    'warning_threshold' => 4.0,
];
