<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\ProductionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EggLoggingController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();

        $cages = Cage::with([
            'latestProduction',
            'hens' => fn($q) => $q->where('is_active', 1),
        ])->where('is_active', 1)->orderBy('cage_code')->get()
          ->map(function ($cage) use ($today) {
              $cage->today_egg_count = ProductionLog::where('cage_id', $cage->id)
                  ->where('log_date', $today)
                  ->value('egg_count') ?? 0;
              return $cage;
          });

        $logs = ProductionLog::with(['cage', 'overriddenBy'])
            ->orderByDesc('log_date')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('egg-logging', compact('cages', 'logs'));
    }

    public function verifyOverride(Request $request)
    {
        $data = $request->validate([
            'cage_id'  => 'required|exists:cages,id',
            'pin'      => 'nullable|string',
            'password' => 'nullable|string',
        ]);

        $user = auth()->user();

        if ($user->override_pin_hash !== null) {
            if (! $data['pin'] || ! Hash::check($data['pin'], $user->override_pin_hash)) {
                return response()->json(['ok' => false, 'error' => 'Incorrect PIN.'], 422);
            }
        } else {
            if (! $data['password'] || ! Hash::check($data['password'], $user->password)) {
                return response()->json(['ok' => false, 'error' => 'Incorrect password.'], 422);
            }
        }

        session()->put("override_verified.{$data['cage_id']}", now()->timestamp);

        return response()->json([
            'ok'              => true,
            'needs_pin_setup' => $user->override_pin_hash === null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'log_date'  => 'required|date',
            'cage_id'   => 'required|exists:cages,id',
            'egg_count' => 'required|integer|min:0',
            'hen_count' => 'required|integer|min:1',
            'notes'     => 'nullable|string',
        ]);

        $cage = Cage::find($data['cage_id']);
        $existing = ProductionLog::where('cage_id', $data['cage_id'])
            ->where('log_date', $data['log_date'])
            ->first();

        $payload = [
            'egg_count'   => $data['egg_count'],
            'hen_count'   => $data['hen_count'],
            'hdep'        => round(($data['egg_count'] / $data['hen_count']) * 100, 2),
            'recorded_by' => auth()->id(),
            'notes'       => $data['notes'] ?? 'Manual entry',
        ];

        if ($cage->has_sensor) {
            $valueChanged = ! $existing || (int) $existing->egg_count !== (int) $data['egg_count'];
            $verifiedAt   = session("override_verified.{$data['cage_id']}");
            $stillValid   = $verifiedAt && (now()->timestamp - $verifiedAt) <= 600;

            if ($valueChanged && ! $stillValid) {
                return back()->withInput()->withErrors([
                    'egg_count' => "This cage's reading is sensor-locked. Use the override option to change it.",
                ]);
            }

            if ($valueChanged) {
                $payload['overridden_by_user_id'] = auth()->id();
                $payload['overridden_at']          = now();
                session()->forget("override_verified.{$data['cage_id']}");
            }
        }

        ProductionLog::updateOrCreate(
            ['cage_id' => $data['cage_id'], 'log_date' => $data['log_date']],
            $payload
        );

        return redirect()->route('egg-logging')->with('success', 'Production log saved.');
    }

    public function destroy(ProductionLog $productionLog)
    {
        $productionLog->delete();
        return redirect()->route('egg-logging')->with('success', 'Log deleted.');
    }
}
