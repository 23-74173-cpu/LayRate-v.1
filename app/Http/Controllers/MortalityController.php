<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\MortalityLog;
use Illuminate\Http\Request;

class MortalityController extends Controller
{
    public function index()
    {
        $cages = Cage::orderBy('cage_code')->get();
        $logs  = MortalityLog::with('cage')
            ->orderByDesc('log_date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $todayTotal = MortalityLog::whereDate('log_date', today())->sum('count');

        $todayByCage = MortalityLog::with('cage')
            ->whereDate('log_date', today())
            ->get()
            ->groupBy(fn($l) => $l->cage->cage_code)
            ->map(fn($g) => $g->sum('count'));

        return view('mortality', compact('cages', 'logs', 'todayTotal', 'todayByCage'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cage_id'  => 'required|exists:cages,id',
            'log_date' => 'required|date',
            'count'    => 'required|integer|min:1',
            'reason'   => 'required|in:' . implode(',', MortalityLog::REASONS),
            'notes'    => 'nullable|string|max:1000',
        ]);

        MortalityLog::create($data);

        return redirect()->route('mortality.index')
            ->with('success', 'Mortality record saved.');
    }

    public function destroy(MortalityLog $mortalityLog)
    {
        $mortalityLog->delete();

        return redirect()->route('mortality.index')
            ->with('success', 'Record deleted.');
    }
}
