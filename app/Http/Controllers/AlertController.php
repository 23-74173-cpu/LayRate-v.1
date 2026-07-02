<?php

namespace App\Http\Controllers;

use App\Models\Alert;
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

    public function table()
    {
        $alerts = Alert::with('cage')
            ->orderByDesc('triggered_at')
            ->paginate(25);

        return view('notifications._table', compact('alerts'));
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
