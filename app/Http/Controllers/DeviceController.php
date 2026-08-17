<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DeviceController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        // api_key_hash is NOT NULL + unique, so the record needs a throwaway
        // placeholder hash to satisfy the constraint on insert — the real,
        // id-prefixed key (see Device::generateApiKey()) can only be built
        // once the row exists and $device->id is known, so it's generated
        // immediately after and overwrites this placeholder before anything
        // reads it.
        $device = Device::create([
            'name' => $data['name'],
            'api_key_hash' => Hash::make(Str::random(40)),
        ]);

        $plainKey = $device->generateApiKey();

        return redirect()->route('hardware.index')
            ->with('success', "Device {$device->name} created. Copy the key now — it will not be shown again.")
            ->with('new_device_key', $plainKey)
            ->with('new_device_id', $device->id);
    }

    public function regenerateKey(Device $device)
    {
        $plainKey = $device->generateApiKey();

        return redirect()->route('hardware.index')
            ->with('success', "API key regenerated for {$device->name}. Copy the key now — it will not be shown again.")
            ->with('new_device_key', $plainKey)
            ->with('new_device_id', $device->id);
    }

    public function destroy(Device $device)
    {
        $name = $device->name;
        $device->delete();

        return redirect()->route('hardware.index')
            ->with('success', "Device {$name} removed.");
    }
}
