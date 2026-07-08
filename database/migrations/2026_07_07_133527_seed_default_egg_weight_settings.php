<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Seed default egg-weight settings used by the FCR calculator.
     * Using a migration (rather than only the seeder) ensures existing
     * deployments also get these keys on migrate.
     */
    public function up(): void
    {
        $eggWeights = [
            'egg_weight_small'     => ['value' => 50, 'label' => 'Average Egg Weight — Small (g)'],
            'egg_weight_medium'    => ['value' => 58, 'label' => 'Average Egg Weight — Medium (g)'],
            'egg_weight_large'     => ['value' => 65, 'label' => 'Average Egg Weight — Large (g)'],
            'egg_weight_jumbo'     => ['value' => 73, 'label' => 'Average Egg Weight — Jumbo (g)'],
            'egg_weight_fallback'  => ['value' => 60, 'label' => 'Average Egg Weight — Fallback (g)'],
        ];

        foreach ($eggWeights as $key => $meta) {
            Setting::firstOrCreate(
                ['key' => $key],
                ['value' => $meta['value'], 'label' => $meta['label']]
            );
        }
    }

    public function down(): void
    {
        Setting::whereIn('key', [
            'egg_weight_small',
            'egg_weight_medium',
            'egg_weight_large',
            'egg_weight_jumbo',
            'egg_weight_fallback',
        ])->delete();
    }
};
