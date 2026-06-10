<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Http\Request;

class AlertController extends Controller
{
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
