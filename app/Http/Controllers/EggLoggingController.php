<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\ProductionLog;
use Illuminate\Http\Request;

class EggLoggingController extends Controller
{
    public function index()
    {
        $cages = Cage::with([
            'latestProduction',
            'hens' => fn($q) => $q->where('is_active', 1),
        ])->where('is_active', 1)->orderBy('cage_code')->get();

        $logs = ProductionLog::with('cage')
            ->orderByDesc('log_date')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('egg-logging', compact('cages', 'logs'));
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

        $hdep = round(($data['egg_count'] / $data['hen_count']) * 100, 2);

        ProductionLog::updateOrCreate(
            ['cage_id' => $data['cage_id'], 'log_date' => $data['log_date']],
            [
                'egg_count'   => $data['egg_count'],
                'hen_count'   => $data['hen_count'],
                'hdep'        => $hdep,
                'recorded_by' => 1,
                'notes'       => $data['notes'] ?? 'Manual entry',
            ]
        );

        return redirect()->route('egg-logging')->with('success', 'Production log saved.');
    }

    public function destroy(ProductionLog $productionLog)
    {
        $productionLog->delete();
        return redirect()->route('egg-logging')->with('success', 'Log deleted.');
    }
}
