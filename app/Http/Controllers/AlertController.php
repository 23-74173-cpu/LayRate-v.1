<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index()
    {
        $alerts = Alert::with('cage')
            ->orderByDesc('triggered_at')
            ->paginate(25);

        return view('notifications.index', compact('alerts'));
    }

    public function table(Request $request)
    {
        $alertsPaginator = Alert::with('cage')
            ->orderByDesc('triggered_at')
            ->paginate(20)
            ->withQueryString();

        $groups = [
            'Eggs'        => collect(),
            'Temperature' => collect(),
            'Humidity'    => collect(),
            'Other'       => collect(),
        ];

        $eggTypes = ['mortality_spike', 'stock_depletion', 'occupancy_mismatch', 'low_stock_feed', 'low_stock_eggs'];
        $tempTypes = ['temperature_low', 'temperature_high'];
        $humTypes = ['humidity_low', 'humidity_high'];

        // Group by category within the current page — the paginator itself
        // still tracks total/prev/next across the full, ungrouped result set.
        foreach ($alertsPaginator as $alert) {
            $type = $alert->alert_type;
            if (in_array($type, $eggTypes)) {
                $groups['Eggs']->push($alert);
            } elseif (in_array($type, $tempTypes)) {
                $groups['Temperature']->push($alert);
            } elseif (in_array($type, $humTypes)) {
                $groups['Humidity']->push($alert);
            } else {
                $groups['Other']->push($alert);
            }
        }

        $groups = array_filter($groups, fn($g) => $g->isNotEmpty());

        return view('notifications._table', ['groups' => $groups, 'alertsPaginator' => $alertsPaginator]);
    }

    public function acknowledgeModal(Request $request)
    {
        $ids = $request->input('ids', []);
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        $acknowledged = session()->get('alerts_acknowledged_ids', []);
        $acknowledged = array_values(array_unique(array_merge($acknowledged, $ids)));

        session()->put('alerts_acknowledged_ids', $acknowledged);

        return response()->json(['ok' => true, 'acknowledged' => $acknowledged]);
    }

    public function apiIndex(Request $request): JsonResponse
    {
        $query = Alert::with('cage:cages.id,cage_code')
            ->orderByDesc('triggered_at');

        if (!$request->has('all')) {
            $query->where('is_read', false);
        }

        if ($request->has('limit')) {
            $query->limit(min((int) $request->limit, 100));
        }

        $alerts = $query->get();

        return response()->json([
            'data' => $alerts->map(fn (Alert $a) => [
                'id' => $a->id,
                'cage_code' => $a->cage?->cage_code,
                'alert_type' => $a->alert_type,
                'message' => $a->message,
                'is_read' => $a->is_read,
                'triggered_at' => $a->triggered_at->toIso8601String(),
            ]),
            'total' => $alerts->count(),
        ]);
    }

    public function apiMarkRead(Alert $alert): JsonResponse
    {
        $alert->update(['is_read' => true]);

        return response()->json([
            'message' => 'Alert marked as read.',
            'data' => [
                'id' => $alert->id,
                'is_read' => true,
            ],
        ]);
    }

    public function markRead(Alert $alert)
    {
        $alert->update(['is_read' => true]);
        return back()->with('success', 'Alert marked as read.');
    }

    public function markAllRead()
    {
        Alert::where('is_read', false)->update(['is_read' => true]);
        return back()->with('success', 'All alerts marked as read.');
    }
}
