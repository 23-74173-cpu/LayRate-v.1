<?php

namespace App\Console\Commands;

use App\Http\Controllers\ForecastController;
use App\Jobs\GenerateForecastJob;
use App\Models\Cage;
use App\Models\Forecast;
use App\Models\ForecastRun;
use App\Models\User;
use App\Services\ForecastGenerationService;
use App\Services\ReportingDateService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RegenerateCleanedForecasts extends Command
{
    protected $signature = 'forecast:regenerate-cleaned {--all : Regenerate all existing future scopes} {--dry-run : Validate and list ranges without changing predictions} {--start-date= : Restore the original starting date when retrying an incomplete range}';
    protected $description = 'Archive future forecasts affected by corrected production and regenerate their saved ranges';

    public function handle(ForecastGenerationService $service): int
    {
        if (! $this->option('all')) { $this->error('Specify --all to select existing future forecasts across all scopes.'); return self::FAILURE; }
        if (ForecastRun::whereIn('status', ['queued', 'running'])->exists()) { $this->error('A forecast run is active; wait for it to finish.'); return self::FAILURE; }
        $admin = User::where('role', 'admin')->where('is_active', true)->firstOrFail();
        $groups = Forecast::issuedInAdvance()->where('target_date', '>', ReportingDateService::reportingDateString())
            ->selectRaw('cage_id, breed, MIN(target_date) as first_date, MAX(target_date) as last_date, COUNT(*) as total')
            ->groupBy('cage_id', 'breed')->get();
        $ranges = [];
        foreach ($groups as $group) {
            $cage = $group->cage_id ? Cage::findOrFail($group->cage_id) : null;
            $scope = $cage ? 'cage' : ($group->breed ? 'breed' : 'farm');
            if ($this->option('start-date')) {
                validator(['start' => $this->option('start-date')], ['start' => 'date_format:Y-m-d'])->validate();
                $group->first_date = $this->option('start-date');
            }
            $horizon = (int) Carbon::parse($group->first_date)->diffInDays(Carbon::parse($group->last_date)) + 1;
            $data = $service->dataSufficiency($scope, $cage?->cage_code, $group->breed);
            $label = $cage?->cage_code ?? $group->breed ?? 'Whole Farm';
            $this->line("{$label}: {$group->first_date} to {$group->last_date}; {$data['current_count']} valid history days");
            if (! $data['has_enough'] || $horizon > 30) { $this->error('This range cannot be regenerated. No forecasts were reset.'); return self::FAILURE; }
            $ranges[] = [$scope, $cage, $group->breed, $group->first_date, $horizon];
        }
        if ($this->option('dry-run') || empty($ranges)) return self::SUCCESS;
        Auth::login($admin);
        foreach ($ranges as [$scope, $cage, $breed, $start, $horizon]) {
            $request = Request::create('/forecast/clear', 'POST', ['scope' => $scope, 'cage' => $cage?->cage_code, 'breed' => $breed, 'start_date' => $start, 'horizon' => $horizon]);
            $request->headers->set('Accept', 'application/json');
            $request->setUserResolver(fn () => $admin);
            $response = app(ForecastController::class)->clear($request);
            if ($response->getStatusCode() !== 200) { $this->error($response->getContent()); return self::FAILURE; }
            $run = ForecastRun::create(['user_id' => $admin->id, 'scope' => $scope, 'cage_id' => $cage?->id, 'cage_code' => $cage?->cage_code, 'breed' => $breed, 'horizon' => $horizon, 'start_date' => $start, 'status' => 'queued', 'redirect_params' => ['scope' => $scope, 'cage' => $cage?->cage_code, 'breed' => $breed, 'horizon' => $horizon, 'forecast_view' => 'saved']]);
            $this->line("Generating run {$run->id} for ".($cage?->cage_code ?? $breed ?? 'Whole Farm').'…');
            (new GenerateForecastJob($run->id, $scope, $cage?->cage_code ?? 'ALL', $cage?->id, $breed, $horizon, $start, []))->handle($service);
            $run->refresh();
            if ($run->status !== 'completed') { $this->error($run->error_message ?? 'Generation failed; archived forecasts were retained.'); return self::FAILURE; }
            $count = $service->savedForecasts($cage, $breed, $start, $horizon)->count();
            if ($count !== $horizon) { $this->error('The run did not save every requested day.'); return self::FAILURE; }
            $this->info("Run {$run->id} completed: {$count} new predictions.");
        }
        return self::SUCCESS;
    }
}
