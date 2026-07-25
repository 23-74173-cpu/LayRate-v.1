<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function storeFarmLayout(Request $request)
    {
        $data = $request->validate([
            'rows' => 'required|integer|min:1|max:50',
            'cols' => 'required|integer|min:1|max:50',
        ]);

        Setting::set('farm_grid_rows', $data['rows']);
        Setting::set('farm_grid_cols', $data['cols']);

        if ($request->expectsJson() || $request->isJson()) {
            return response()->json(['success' => true, 'rows' => (int) $data['rows'], 'cols' => (int) $data['cols']]);
        }

        return redirect()->route('dashboard')->with('success', 'Farm layout configured.');
    }
}
